<?php

declare(strict_types=1);

use Chkltlabs\Porthole\Logs\LogFileNotFound;
use Chkltlabs\Porthole\Logs\LogFiles;
use Chkltlabs\Porthole\Logs\UnsafeLogPath;
use Chkltlabs\Porthole\Support\Redactor;
use Chkltlabs\Porthole\Tests\TestCase;
use Illuminate\Support\Facades\File;

uses(TestCase::class);

beforeEach(function () {
    File::cleanDirectory(storage_path('logs'));
    config([
        'porthole.logs.lines' => 5,
        'porthole.logs.lines_max' => 20,
        'porthole.logs.bytes_max' => 2048,
        'porthole.logs.search_matches' => 5,
        'porthole.logs.search_matches_max' => 10,
    ]);
});

afterEach(function () {
    File::cleanDirectory(storage_path('logs'));
});

function logFiles(): LogFiles
{
    return new LogFiles(new Redactor(maxStringLength: 500));
}

function writeLog(string $relative, string $contents): string
{
    $path = storage_path('logs/'.$relative);
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);

    return $path;
}

it('lists single, daily, nested, and custom channel log files', function () {
    writeLog('laravel.log', "single\n");
    writeLog('laravel-2026-10-28.log', "daily\n");
    writeLog('worker/queue.log', "nested\n");
    writeLog('custom-channel.log', "custom\n");
    File::put(storage_path('logs/notes.txt'), 'ignored');
    File::put(storage_path('logs/.gitignore'), '*');

    $files = array_column(logFiles()->list(), 'path');

    expect($files)->toBe([
        'custom-channel.log',
        'laravel-2026-10-28.log',
        'laravel.log',
        'worker/queue.log',
    ]);
});

it('reads the head and tail with line caps', function () {
    $lines = collect(range(1, 12))->map(fn (int $n): string => "line-{$n}")->implode("\n");
    writeLog('laravel.log', $lines."\n");

    $head = logFiles()->read('laravel.log', 'head', 3);
    $tail = logFiles()->read('laravel.log', 'tail', 3);

    expect($head['mode'])->toBe('head')
        ->and($head['content'])->toBe("line-1\nline-2\nline-3")
        ->and($head['truncated'])->toBeTrue()
        ->and($tail['mode'])->toBe('tail')
        ->and($tail['content'])->toBe("line-10\nline-11\nline-12")
        ->and($tail['truncated'])->toBeTrue();
});

it('searches across listed log files with a match cap', function () {
    writeLog('laravel.log', "alpha\nbeta secret\ngamma\n");
    writeLog('worker/app.log', "secret in nested\nother\n");

    $result = logFiles()->search('secret', null, 10);

    expect($result['matches'])->toHaveCount(2)
        ->and($result['matches'][0]['path'])->toBe('laravel.log')
        ->and($result['matches'][0]['line'])->toBe(2)
        ->and($result['matches'][1]['path'])->toBe('worker/app.log');
});

it('deletes only resolved log files and reports missing paths', function () {
    writeLog('laravel.log', "keep me briefly\n");
    writeLog('old.log', "delete me\n");

    $result = logFiles()->delete(['old.log', 'gone.log']);

    expect($result['deleted'])->toBe(['old.log'])
        ->and($result['missing'])->toBe(['gone.log'])
        ->and(file_exists(storage_path('logs/old.log')))->toBeFalse()
        ->and(file_exists(storage_path('logs/laravel.log')))->toBeTrue();
});

it('refuses path traversal and non-log files', function (string $path) {
    writeLog('laravel.log', "ok\n");
    File::put(storage_path('logs/notes.txt'), 'nope');
    File::ensureDirectoryExists(storage_path('framework'));
    File::put(storage_path('framework/secret.log'), "outside\n");

    expect(fn () => logFiles()->resolve($path))->toThrow(UnsafeLogPath::class);
})->with([
    '../framework/secret.log',
    'laravel.log/../../framework/secret.log',
    '/etc/passwd',
    'notes.txt',
    'laravel.log.bak',
    './laravel.log',
]);

it('refuses absolute paths and missing files distinctly', function () {
    expect(fn () => logFiles()->resolve('/tmp/evil.log'))->toThrow(UnsafeLogPath::class)
        ->and(fn () => logFiles()->resolve('missing.log'))->toThrow(LogFileNotFound::class);
});

it('refuses symlink escape from storage/logs', function () {
    File::ensureDirectoryExists(storage_path('framework'));
    $outside = storage_path('framework/escape.log');
    File::put($outside, "escaped\n");
    $link = storage_path('logs/linked.log');

    if (! @symlink($outside, $link)) {
        $this->markTestSkipped('Unable to create symlink in this environment.');
    }

    expect(fn () => logFiles()->resolve('linked.log'))->toThrow(UnsafeLogPath::class)
        ->and(array_column(logFiles()->list(), 'path'))->not->toContain('linked.log');
});

it('does not append or rewrite log contents through delete', function () {
    $path = writeLog('laravel.log', "original body\n");
    $before = file_get_contents($path);

    logFiles()->delete(['laravel.log']);

    expect($before)->toBe("original body\n")
        ->and(file_exists($path))->toBeFalse();
});
