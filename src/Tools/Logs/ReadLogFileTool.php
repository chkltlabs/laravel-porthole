<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Tools\Logs;

use Chkltlabs\Porthole\Logs\LogFileNotFound;
use Chkltlabs\Porthole\Logs\LogFiles;
use Chkltlabs\Porthole\Logs\LogLimit;
use Chkltlabs\Porthole\Logs\UnsafeLogPath;
use Chkltlabs\Porthole\Tools\Concerns\AuthorizesPorthole;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ReadLogFileTool extends Tool
{
    use AuthorizesPorthole;

    protected string $name = 'logs-read';

    protected string $description = 'Read the head or tail of a .log file under storage/logs. Line and byte counts are capped so large files are not dumped whole.';

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewLogs')) {
            return $denied;
        }

        $maxLines = LogLimit::lines(config('porthole.logs.lines_max'));

        $validated = $request->validate([
            'path' => ['required', 'string', 'max:500'],
            'mode' => ['nullable', 'in:head,tail'],
            'lines' => ['nullable', 'integer', 'min:1', 'max:'.$maxLines],
        ]);

        try {
            return Response::structured(app(LogFiles::class)->read(
                (string) $validated['path'],
                (string) ($validated['mode'] ?? 'tail'),
                $validated['lines'] ?? null,
            ));
        } catch (LogFileNotFound|UnsafeLogPath $exception) {
            return Response::error($exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description('Relative path under storage/logs, such as laravel.log or laravel-2026-10-28.log.')->required(),
            'mode' => $schema->string()->description('Read from the start (head) or end (tail). Defaults to tail.')->enum(['head', 'tail']),
            'lines' => $schema->integer()->description('Maximum lines to return. The package caps this value.'),
        ];
    }
}
