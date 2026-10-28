<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Logs;

use Chkltlabs\Porthole\Support\Redactor;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class LogFiles
{
    public function __construct(private Redactor $redactor) {}

    /**
     * Absolute, real path to storage/logs. Creates the directory when missing
     * so listing stays empty instead of failing on a fresh app.
     */
    public function root(): string
    {
        $path = storage_path('logs');

        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new UnsafeLogPath('Unable to access storage/logs.');
        }

        $real = realpath($path);

        if ($real === false || ! is_dir($real)) {
            throw new UnsafeLogPath('Unable to resolve storage/logs.');
        }

        return $real;
    }

    /**
     * @return list<array{path: string, size: int, modified_at: string|null}>
     */
    public function list(): array
    {
        $root = $this->root();
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
            ),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD,
        );

        /** @var SplFileInfo $info */
        foreach ($iterator as $info) {
            if (! $info->isFile() || $info->isLink()) {
                continue;
            }

            $absolute = $info->getPathname();
            $real = realpath($absolute);

            if ($real === false || ! $this->isInsideRoot($real, $root)) {
                continue;
            }

            if (! $this->isLogFileName($real)) {
                continue;
            }

            $files[] = [
                'path' => $this->relativePath($real, $root),
                'size' => $info->getSize(),
                'modified_at' => date('c', $info->getMTime()) ?: null,
            ];
        }

        usort(
            $files,
            static fn (array $left, array $right): int => strcmp($left['path'], $right['path']),
        );

        return $files;
    }

    /**
     * @return array{path: string, mode: string, size: int, truncated: bool, content: string}
     */
    public function read(string $path, string $mode = 'tail', mixed $lines = null): array
    {
        $absolute = $this->resolve($path);
        $lineCount = LogLimit::lines($lines);
        $byteCap = LogLimit::bytes();
        $mode = $mode === 'head' ? 'head' : 'tail';
        $size = filesize($absolute);

        if ($size === false) {
            throw new LogFileNotFound('Unable to read log file size.');
        }

        [$content, $truncated] = $mode === 'head'
            ? $this->readHead($absolute, $lineCount, $byteCap)
            : $this->readTail($absolute, $lineCount, $byteCap);

        return [
            'path' => $this->relativePath($absolute, $this->root()),
            'mode' => $mode,
            'size' => $size,
            'truncated' => $truncated,
            'content' => $this->redactor->scrub($content),
        ];
    }

    /**
     * @return array{query: string, matches: list<array{path: string, line: int, text: string}>, truncated: bool}
     */
    public function search(string $query, ?string $path = null, mixed $limit = null): array
    {
        $needle = trim($query);

        if ($needle === '') {
            throw new UnsafeLogPath('Search query is required.');
        }

        $matchCap = LogLimit::searchMatches($limit);
        $byteCap = LogLimit::bytes();
        $matches = [];
        $truncated = false;
        $targets = $path === null || $path === ''
            ? array_column($this->list(), 'path')
            : [$path];

        foreach ($targets as $relative) {
            $absolute = $this->resolve((string) $relative);
            $handle = fopen($absolute, 'rb');

            if ($handle === false) {
                throw new LogFileNotFound('Unable to open log file for search.');
            }

            $lineNumber = 0;
            $bytesRead = 0;

            try {
                while (($line = fgets($handle)) !== false) {
                    $lineNumber++;
                    $bytesRead += strlen($line);

                    if ($bytesRead > $byteCap) {
                        $truncated = true;
                        break;
                    }

                    if (! str_contains($line, $needle)) {
                        continue;
                    }

                    $matches[] = [
                        'path' => $this->relativePath($absolute, $this->root()),
                        'line' => $lineNumber,
                        'text' => $this->redactor->scrub(rtrim($line, "\r\n")),
                    ];

                    if (count($matches) >= $matchCap) {
                        $truncated = true;
                        break 2;
                    }
                }
            } finally {
                fclose($handle);
            }
        }

        return [
            'query' => $needle,
            'matches' => $matches,
            'truncated' => $truncated,
        ];
    }

    /**
     * @param  list<string>  $paths
     * @return array{deleted: list<string>, missing: list<string>}
     */
    public function delete(array $paths): array
    {
        $deleted = [];
        $missing = [];

        foreach ($paths as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            try {
                $absolute = $this->resolve($path);
            } catch (LogFileNotFound) {
                $missing[] = str_replace('\\', '/', ltrim(trim($path), '/'));

                continue;
            }

            $relative = $this->relativePath($absolute, $this->root());

            if (! unlink($absolute)) {
                throw new UnsafeLogPath('Unable to delete log file: '.$relative);
            }

            $deleted[] = $relative;
        }

        return [
            'deleted' => $deleted,
            'missing' => $missing,
        ];
    }

    /**
     * Resolve a caller-supplied relative path to a real .log file under storage/logs.
     */
    public function resolve(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));

        if ($normalized === '' || str_contains($normalized, "\0")) {
            throw new UnsafeLogPath('Log path is required.');
        }

        if (str_starts_with($normalized, '/') || preg_match('#^[a-zA-Z]:/#', $normalized) === 1) {
            throw new UnsafeLogPath('Log path must stay under storage/logs.');
        }

        $normalized = ltrim($normalized, '/');

        $segments = array_values(array_filter(
            explode('/', $normalized),
            static fn (string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            throw new UnsafeLogPath('Log path is required.');
        }

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new UnsafeLogPath('Log path must stay under storage/logs.');
            }
        }

        $relative = implode('/', $segments);

        if (! $this->isLogFileName($relative)) {
            throw new UnsafeLogPath('Only .log files under storage/logs are allowed.');
        }

        $root = $this->root();
        $candidate = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (! file_exists($candidate)) {
            throw new LogFileNotFound('Log file not found: '.$relative);
        }

        $real = realpath($candidate);

        if ($real === false || ! is_file($real) || is_link($candidate)) {
            throw new UnsafeLogPath('Log path must stay under storage/logs.');
        }

        if (! $this->isInsideRoot($real, $root)) {
            throw new UnsafeLogPath('Log path must stay under storage/logs.');
        }

        if (! $this->isLogFileName($real)) {
            throw new UnsafeLogPath('Only .log files under storage/logs are allowed.');
        }

        return $real;
    }

    private function isInsideRoot(string $absolute, string $root): bool
    {
        return $absolute === $root
            || str_starts_with($absolute, $root.DIRECTORY_SEPARATOR);
    }

    private function isLogFileName(string $path): bool
    {
        return str_ends_with(strtolower($path), '.log');
    }

    private function relativePath(string $absolute, string $root): string
    {
        $relative = substr($absolute, strlen($root) + 1);

        return str_replace('\\', '/', $relative === false ? '' : $relative);
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function readHead(string $absolute, int $lineCount, int $byteCap): array
    {
        $handle = fopen($absolute, 'rb');

        if ($handle === false) {
            throw new LogFileNotFound('Unable to open log file.');
        }

        $lines = [];
        $bytes = 0;
        $truncated = false;

        try {
            while (($line = fgets($handle)) !== false) {
                $bytes += strlen($line);

                if ($bytes > $byteCap) {
                    $truncated = true;
                    break;
                }

                $lines[] = rtrim($line, "\r\n");

                if (count($lines) >= $lineCount) {
                    if (! feof($handle)) {
                        $truncated = true;
                    }

                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        return [implode("\n", $lines), $truncated];
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function readTail(string $absolute, int $lineCount, int $byteCap): array
    {
        $size = filesize($absolute);

        if ($size === false) {
            throw new LogFileNotFound('Unable to read log file size.');
        }

        if ($size === 0) {
            return ['', false];
        }

        $handle = fopen($absolute, 'rb');

        if ($handle === false) {
            throw new LogFileNotFound('Unable to open log file.');
        }

        try {
            $window = min($size, max($byteCap * 2, $lineCount * 512));
            $offset = $size - $window;
            fseek($handle, $offset);
            $chunk = stream_get_contents($handle);

            if ($chunk === false) {
                throw new LogFileNotFound('Unable to read log file.');
            }

            if ($offset > 0) {
                $newline = strpos($chunk, "\n");
                $chunk = $newline === false ? $chunk : substr($chunk, $newline + 1);
            }

            $allLines = preg_split("/\r\n|\n|\r/", $chunk) ?: [];

            if ($allLines !== [] && end($allLines) === '') {
                array_pop($allLines);
            }

            $truncated = $offset > 0 || count($allLines) > $lineCount;
            $selected = array_slice($allLines, -$lineCount);
            $content = implode("\n", $selected);

            if (strlen($content) > $byteCap) {
                $truncated = true;
                $content = substr($content, -$byteCap);
                $firstNewline = strpos($content, "\n");

                if ($firstNewline !== false) {
                    $content = substr($content, $firstNewline + 1);
                }
            }

            return [$content, $truncated];
        } finally {
            fclose($handle);
        }
    }
}
