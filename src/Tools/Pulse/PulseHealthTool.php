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
class PulseHealthTool extends Tool
{
    use AuthorizesObservability;

    protected string $name = 'pulse-health';

    protected string $description = 'Read whether Pulse is enabled, which ingest driver it uses, and how many exceptions and slow requests were recorded in the last hour.';

    public function shouldRegister(): bool
    {
        return InstalledPackage::exists(Storage::class);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewPulse')) {
            return $denied;
        }

        $snapshot = app(PulseSnapshot::class);

        return Response::structured([
            'enabled' => (bool) config('pulse.enabled', true),
            'ingest' => config('pulse.ingest.driver'),
            'storage' => config('pulse.storage.driver'),
            'exceptions_1h' => $snapshot->total('exception', '1h'),
            'slow_requests_1h' => $snapshot->total('slow_request', '1h'),
        ]);
    }
}
