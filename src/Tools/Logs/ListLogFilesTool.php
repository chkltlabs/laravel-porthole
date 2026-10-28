<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Tools\Logs;

use Chkltlabs\Porthole\Logs\LogFiles;
use Chkltlabs\Porthole\Logs\UnsafeLogPath;
use Chkltlabs\Porthole\Tools\Concerns\AuthorizesPorthole;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListLogFilesTool extends Tool
{
    use AuthorizesPorthole;

    protected string $name = 'logs-list';

    protected string $description = 'List .log files under storage/logs, including Laravel single, daily, and nested channel files.';

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewLogs')) {
            return $denied;
        }

        try {
            return Response::structured([
                'files' => app(LogFiles::class)->list(),
            ]);
        } catch (UnsafeLogPath $exception) {
            return Response::error($exception->getMessage());
        }
    }
}
