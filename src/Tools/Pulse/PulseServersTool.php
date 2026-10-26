<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Tools\Pulse;

use Chkltlabs\ObservabilityMcp\Pulse\PulseSnapshot;
use Chkltlabs\ObservabilityMcp\Support\InstalledPackage;
use Chkltlabs\ObservabilityMcp\Tools\Concerns\AuthorizesObservability;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Pulse\Contracts\Storage;

#[IsReadOnly]
class PulseServersTool extends Tool
{
    use AuthorizesObservability;

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
