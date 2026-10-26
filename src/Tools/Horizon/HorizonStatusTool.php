<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Tools\Horizon;

use Chkltlabs\ObservabilityMcp\Horizon\HorizonSnapshot;
use Chkltlabs\ObservabilityMcp\Support\InstalledPackage;
use Chkltlabs\ObservabilityMcp\Tools\Concerns\AuthorizesObservability;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class HorizonStatusTool extends Tool
{
    use AuthorizesObservability;

    protected string $name = 'horizon-status';

    protected string $description = 'Read Horizon status, supervisors, queue workload, and job counts. This tool does not pause Horizon or retry jobs.';

    public function shouldRegister(): bool
    {
        return InstalledPackage::exists(JobRepository::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewHorizon')) {
            return $denied;
        }

        return Response::structured(app(HorizonSnapshot::class)->status());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
