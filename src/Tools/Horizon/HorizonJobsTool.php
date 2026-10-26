<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Tools\Horizon;

use Chkltlabs\ObservabilityMcp\Horizon\HorizonSnapshot;
use Chkltlabs\ObservabilityMcp\Support\InstalledPackage;
use Chkltlabs\ObservabilityMcp\Support\ResultLimit;
use Chkltlabs\ObservabilityMcp\Tools\Concerns\AuthorizesObservability;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class HorizonJobsTool extends Tool
{
    use AuthorizesObservability;

    protected string $name = 'horizon-jobs';

    protected string $description = 'List recent, pending, completed, failed, or silenced Horizon jobs. Payloads and serialized commands are omitted.';

    public function shouldRegister(): bool
    {
        return InstalledPackage::exists(JobRepository::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewHorizon')) {
            return $denied;
        }

        $validated = $request->validate([
            'status' => ['nullable', 'in:recent,pending,completed,failed,silenced'],
            'after' => ['nullable', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.ResultLimit::cap(config('observability-mcp.limits.max'))],
        ]);

        return Response::structured(app(HorizonSnapshot::class)->jobs(
            (string) ($validated['status'] ?? 'recent'),
            isset($validated['after']) ? (string) $validated['after'] : null,
            ResultLimit::cap($validated['limit'] ?? null),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('One of recent, pending, completed, failed, or silenced.')->enum(['recent', 'pending', 'completed', 'failed', 'silenced']),
            'after' => $schema->string()->description('Horizon cursor returned with a previous page.'),
            'limit' => $schema->integer()->description('Maximum jobs to return.'),
        ];
    }
}
