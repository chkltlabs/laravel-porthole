<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Tools\Pulse;

use Chkltlabs\Porthole\Pulse\PulseSnapshot;
use Chkltlabs\Porthole\Support\InstalledPackage;
use Chkltlabs\Porthole\Support\ResultLimit;
use Chkltlabs\Porthole\Tools\Concerns\AuthorizesPorthole;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Pulse\Contracts\Storage;

#[IsReadOnly]
class PulseQueuesTool extends Tool
{
    use AuthorizesPorthole;

    protected string $name = 'pulse-queues';

    protected string $description = 'Read Pulse queue counts: queued, processing, processed, released, and failed.';

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
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.ResultLimit::cap(config('porthole.limits.max'))],
        ]);

        return Response::structured(array_merge(
            ['period' => (string) ($validated['period'] ?? '24h')],
            app(PulseSnapshot::class)->queues(
                (string) ($validated['period'] ?? '24h'),
                ResultLimit::cap($validated['limit'] ?? null),
            ),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->description('One of 1h, 6h, 24h, or 7d.')->enum(['1h', '6h', '24h', '7d']),
            'limit' => $schema->integer()->description('Maximum queues to include per status.'),
        ];
    }
}
