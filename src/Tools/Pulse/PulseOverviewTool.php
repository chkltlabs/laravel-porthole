<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Tools\Pulse;

use Chkltlabs\ObservabilityMcp\Pulse\PulseSnapshot;
use Chkltlabs\ObservabilityMcp\Support\InstalledPackage;
use Chkltlabs\ObservabilityMcp\Tools\Concerns\AuthorizesObservability;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Pulse\Contracts\Storage;

#[IsReadOnly]
class PulseOverviewTool extends Tool
{
    use AuthorizesObservability;

    protected string $name = 'pulse-overview';

    protected string $description = 'Read Pulse totals and the top slow requests, slow queries, and exceptions for a period. Period is 1h, 6h, 24h, or 7d.';

    public function shouldRegister(): bool
    {
        return InstalledPackage::exists(Storage::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewPulse')) {
            return $denied;
        }

        $validated = $request->validate([
            'period' => ['nullable', 'in:1h,6h,24h,7d'],
        ]);

        $period = (string) ($validated['period'] ?? '24h');
        $snapshot = app(PulseSnapshot::class);

        return Response::structured([
            'period' => $period,
            'totals' => [
                'exceptions' => $snapshot->total('exception', $period),
                'slow_requests' => $snapshot->total('slow_request', $period),
                'slow_queries' => $snapshot->total('slow_query', $period),
                'slow_jobs' => $snapshot->total('slow_job', $period),
            ],
            'slow_requests' => $snapshot->rows('slow_request', $period, ['count', 'max', 'avg'], 5),
            'slow_queries' => $snapshot->rows('slow_query', $period, ['count', 'max', 'avg'], 5),
            'exceptions' => $snapshot->rows('exception', $period, ['count'], 5),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->description('One of 1h, 6h, 24h, or 7d.')->enum(['1h', '6h', '24h', '7d']),
        ];
    }
}
