<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Tools\Horizon;

use Chkltlabs\ObservabilityMcp\Horizon\HorizonOperator;
use Chkltlabs\ObservabilityMcp\Support\InstalledPackage;
use Chkltlabs\ObservabilityMcp\Tools\Concerns\AuthorizesObservability;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class RetryFailedHorizonJobTool extends Tool
{
    use AuthorizesObservability;

    protected string $name = 'horizon-retry-failed-job';

    protected string $description = 'Retry one failed Horizon job by id. Registered only when observability-mcp.writes_enabled is true.';

    public function shouldRegister(): bool
    {
        return config('observability-mcp.writes_enabled') === true
            && InstalledPackage::exists(JobRepository::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewHorizon')) {
            return $denied;
        }

        if ($denied = $this->writesDenied()) {
            return $denied;
        }

        $validated = $request->validate([
            'id' => ['required', 'string', 'max:100'],
        ]);

        $retried = app(HorizonOperator::class)->retryFailedJob($validated['id']);

        if (! $retried) {
            return Response::error('Failed job was not found.');
        }

        return Response::structured([
            'retried' => true,
            'id' => $validated['id'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The failed Horizon job id.')->required(),
        ];
    }
}
