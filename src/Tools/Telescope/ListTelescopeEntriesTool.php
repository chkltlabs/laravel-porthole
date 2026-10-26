<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Tools\Telescope;

use Chkltlabs\Porthole\Support\InstalledPackage;
use Chkltlabs\Porthole\Support\ResultLimit;
use Chkltlabs\Porthole\Telescope\TelescopeSnapshot;
use Chkltlabs\Porthole\Tools\Concerns\AuthorizesPorthole;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\EntryType;

#[IsReadOnly]
class ListTelescopeEntriesTool extends Tool
{
    use AuthorizesPorthole;

    protected string $name = 'telescope-list';

    protected string $description = 'List Telescope entries by type. Query bindings, cookies, and secrets are removed. Use telescope-show for one batch.';

    public function shouldRegister(): bool
    {
        return InstalledPackage::exists(EntriesRepository::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewTelescope')) {
            return $denied;
        }

        $types = class_exists(EntryType::class)
            ? implode(',', EntryType::all())
            : '';

        $validated = $request->validate([
            'type' => ['nullable', 'string', $types === '' ? 'max:50' : 'in:'.$types],
            'tag' => ['nullable', 'string', 'max:200'],
            'batch' => ['nullable', 'string', 'max:100'],
            'family' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.ResultLimit::cap(config('porthole.limits.max'))],
        ]);

        return Response::structured([
            'entries' => app(TelescopeSnapshot::class)->list(
                isset($validated['type']) ? (string) $validated['type'] : null,
                isset($validated['tag']) ? (string) $validated['tag'] : null,
                isset($validated['batch']) ? (string) $validated['batch'] : null,
                isset($validated['family']) ? (string) $validated['family'] : null,
                ResultLimit::cap($validated['limit'] ?? null),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('Telescope entry type, such as request, exception, query, job, or log.'),
            'tag' => $schema->string()->description('Telescope tag, such as Auth:42.'),
            'batch' => $schema->string()->description('Batch id shared by one request or command.'),
            'family' => $schema->string()->description('Family hash that groups recurring exceptions.'),
            'limit' => $schema->integer()->description('Maximum entries to return.'),
        ];
    }
}
