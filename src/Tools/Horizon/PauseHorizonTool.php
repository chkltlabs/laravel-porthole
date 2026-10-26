<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Tools\Horizon;

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
class PauseHorizonTool extends Tool
{
    use AuthorizesPorthole;

    protected string $name = 'horizon-pause';

    protected string $description = 'Pause Horizon supervisors. Registered only when porthole.writes_enabled is true.';

    public function shouldRegister(): bool
    {
        return config('porthole.writes_enabled') === true
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

        app(HorizonOperator::class)->pause();

        return Response::structured([
            'paused' => true,
        ]);
    }
}
