<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Tools\Logs;

use Chkltlabs\Porthole\Logs\LogFiles;
use Chkltlabs\Porthole\Logs\UnsafeLogPath;
use Chkltlabs\Porthole\Tools\Concerns\AuthorizesPorthole;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsDestructive]
#[IsIdempotent]
class DeleteLogFilesTool extends Tool
{
    use AuthorizesPorthole;

    protected string $name = 'logs-delete';

    protected string $description = 'Delete one or more .log files under storage/logs. Does not append or edit log contents. Registered only when porthole.destructive_enabled is true.';

    public function shouldRegister(): bool
    {
        return config('porthole.destructive_enabled') === true;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewLogs')) {
            return $denied;
        }

        if ($denied = $this->destructiveDenied()) {
            return $denied;
        }

        $validated = $request->validate([
            'paths' => ['required', 'array', 'min:1', 'max:50'],
            'paths.*' => ['required', 'string', 'max:500'],
        ]);

        /** @var list<string> $paths */
        $paths = array_values(array_map(
            static fn (mixed $path): string => (string) $path,
            $validated['paths'],
        ));

        try {
            return Response::structured(app(LogFiles::class)->delete($paths));
        } catch (UnsafeLogPath $exception) {
            return Response::error($exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'paths' => $schema->array()
                ->description('Relative .log paths under storage/logs to delete, such as laravel.log.')
                ->items($schema->string())
                ->required(),
        ];
    }
}
