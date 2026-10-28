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
class SearchLogFilesTool extends Tool
{
    use AuthorizesPorthole;

    protected string $name = 'logs-search';

    protected string $description = 'Search .log files under storage/logs for a literal string. Results and bytes scanned are capped.';

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewLogs')) {
            return $denied;
        }

        $maxMatches = LogLimit::searchMatches(config('porthole.logs.search_matches_max'));

        $validated = $request->validate([
            'query' => ['required', 'string', 'max:500'],
            'path' => ['nullable', 'string', 'max:500'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.$maxMatches],
        ]);

        try {
            return Response::structured(app(LogFiles::class)->search(
                (string) $validated['query'],
                isset($validated['path']) ? (string) $validated['path'] : null,
                $validated['limit'] ?? null,
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
            'query' => $schema->string()->description('Literal text to find in log lines.')->required(),
            'path' => $schema->string()->description('Optional relative path under storage/logs. Omit to search every listed .log file.'),
            'limit' => $schema->integer()->description('Maximum matches to return. The package caps this value.'),
        ];
    }
}
