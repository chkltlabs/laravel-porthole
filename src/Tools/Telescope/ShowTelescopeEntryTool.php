<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Tools\Telescope;

use Chkltlabs\ObservabilityMcp\Support\InstalledPackage;
use Chkltlabs\ObservabilityMcp\Support\ResultLimit;
use Chkltlabs\ObservabilityMcp\Telescope\TelescopeSnapshot;
use Chkltlabs\ObservabilityMcp\Tools\Concerns\AuthorizesObservability;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Telescope\Contracts\EntriesRepository;

#[IsReadOnly]
class ShowTelescopeEntryTool extends Tool
{
    use AuthorizesObservability;

    protected string $name = 'telescope-show';

    protected string $description = 'Show one Telescope entry and a capped slice of its batch. Bindings, cookies, authorization headers, and trace arguments are removed.';

    public function shouldRegister(): bool
    {
        return InstalledPackage::exists(EntriesRepository::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewTelescope')) {
            return $denied;
        }

        $validated = $request->validate([
            'id' => ['required', 'string', 'max:100'],
            'types' => ['nullable', 'array', 'max:10'],
            'types.*' => ['string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.ResultLimit::cap(config('observability-mcp.limits.max'))],
        ]);

        $shown = app(TelescopeSnapshot::class)->show(
            $validated['id'],
            array_values($validated['types'] ?? []),
            ResultLimit::cap($validated['limit'] ?? null),
        );

        if ($shown === null) {
            return Response::error('Telescope entry was not found.');
        }

        return Response::structured($shown);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Telescope entry UUID.')->required(),
            'types' => $schema->array()->description('Optional batch entry types to keep, such as query or exception.')->items($schema->string()),
            'limit' => $schema->integer()->description('Maximum related batch entries to return.'),
        ];
    }
}
