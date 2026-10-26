<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Tools\Pulse;

use Chkltlabs\Porthole\Pulse\PulseSnapshot;
use Chkltlabs\Porthole\Support\InstalledPackage;
use Chkltlabs\Porthole\Tools\Concerns\AuthorizesPorthole;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Pulse\Contracts\Storage;

#[IsReadOnly]
class PulseServersTool extends Tool
{
    use AuthorizesPorthole;

    protected string $name = 'pulse-servers';

    protected string $description = 'Read the latest Pulse server samples for CPU, memory, and disk.';

    public function shouldRegister(): bool
    {
        return InstalledPackage::exists(Storage::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewPulse')) {
            return $denied;
        }

        return Response::structured([
            'servers' => app(PulseSnapshot::class)->servers(),
        ]);
    }
}
