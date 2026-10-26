<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Tools\Horizon;

use Chkltlabs\ObservabilityMcp\Horizon\HorizonOperator;
use Chkltlabs\ObservabilityMcp\Support\InstalledPackage;
use Chkltlabs\ObservabilityMcp\Tools\Concerns\AuthorizesObservability;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsDestructive]
#[IsIdempotent]
class ContinueHorizonTool extends Tool
{
    use AuthorizesObservability;

    protected string $name = 'horizon-continue';

    protected string $description = 'Resume paused Horizon supervisors. Registered only when observability-mcp.writes_enabled is true.';

    public function shouldRegister(): bool
    {
        return config('observability-mcp.writes_enabled') === true
            && InstalledPackage::exists(MasterSupervisorRepository::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewHorizon')) {
            return $denied;
        }

        if ($denied = $this->writesDenied()) {
            return $denied;
        }

        app(HorizonOperator::class)->resume();

        return Response::structured([
            'resumed' => true,
        ]);
    }
}
