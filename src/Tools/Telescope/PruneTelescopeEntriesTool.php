<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Tools\Telescope;

use Chkltlabs\Porthole\Support\InstalledPackage;
use Chkltlabs\Porthole\Telescope\TelescopeOperator;
use Chkltlabs\Porthole\Tools\Concerns\AuthorizesPorthole;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Telescope\Contracts\PrunableRepository;

#[IsDestructive]
class PruneTelescopeEntriesTool extends Tool
{
    use AuthorizesPorthole;

    protected string $name = 'telescope-prune';

    protected string $description = 'Delete Telescope entries older than a number of hours. Registered only when porthole.destructive_enabled is true.';

    public function shouldRegister(): bool
    {
        return config('porthole.destructive_enabled') === true
            && InstalledPackage::exists(PrunableRepository::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewTelescope')) {
            return $denied;
        }

        if ($denied = $this->destructiveDenied()) {
            return $denied;
        }

        $maxHours = max(1, (int) config('porthole.prune_max_hours', 168));

        $validated = $request->validate([
            'hours' => ['nullable', 'integer', 'min:1', 'max:'.$maxHours],
            'keep_exceptions' => ['nullable', 'boolean'],
        ]);

        $hours = (int) ($validated['hours'] ?? 24);
        $pruned = app(TelescopeOperator::class)->prune($hours, (bool) ($validated['keep_exceptions'] ?? false));

        return Response::structured([
            'pruned' => $pruned,
            'hours' => $hours,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'hours' => $schema->integer()->description('Hours of Telescope data to retain. The package caps this value.'),
            'keep_exceptions' => $schema->boolean()->description('Retain exception entries when true.'),
        ];
    }
}
