<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Tools\Horizon;

use Chkltlabs\ObservabilityMcp\Horizon\HorizonSnapshot;
use Chkltlabs\ObservabilityMcp\Support\InstalledPackage;
use Chkltlabs\ObservabilityMcp\Support\ResultLimit;
use Chkltlabs\ObservabilityMcp\Tools\Concerns\AuthorizesObservability;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class HorizonMetricsTool extends Tool
{
    use AuthorizesObservability;

    protected string $name = 'horizon-metrics';

    protected string $description = 'Read Horizon throughput and average runtime for measured jobs and queues.';

    public function shouldRegister(): bool
    {
        return InstalledPackage::exists(MetricsRepository::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewHorizon')) {
            return $denied;
        }

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.ResultLimit::cap(config('observability-mcp.limits.max'))],
        ]);

        return Response::structured(app(HorizonSnapshot::class)->metrics(
            ResultLimit::cap($validated['limit'] ?? null),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Maximum jobs and queues to include.'),
        ];
    }
}
