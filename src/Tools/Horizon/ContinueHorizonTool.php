<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Tools\Horizon;

use Chkltlabs\Porthole\Horizon\HorizonCommandFailed;
use Chkltlabs\Porthole\Horizon\HorizonOperator;
use Chkltlabs\Porthole\Support\InstalledPackage;
use Chkltlabs\Porthole\Tools\Concerns\AuthorizesPorthole;
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
    use AuthorizesPorthole;

    protected string $name = 'horizon-continue';

    protected string $description = 'Resume paused Horizon supervisors. Registered only when porthole.writes_enabled is true.';

    public function shouldRegister(): bool
    {
        return config('porthole.writes_enabled') === true
            && InstalledPackage::exists(MasterSupervisorRepository::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->mutationDenied($request, 'viewHorizon', 'mutateHorizon')) {
            return $denied;
        }

        if ($denied = $this->writesDenied()) {
            return $denied;
        }

        try {
            app(HorizonOperator::class)->resume();
        } catch (HorizonCommandFailed $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured([
            'resumed' => true,
        ]);
    }
}
