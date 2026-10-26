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
use Laravel\Pulse\Contracts\Storage;

trait ReadsPulseAggregate
{
    use AuthorizesPorthole;

    public function shouldRegister(): bool
    {
        return InstalledPackage::exists(Storage::class);
    }

    /**
     * @param  list<'count'|'min'|'max'|'sum'|'avg'>  $aggregates
     */
    protected function report(Request $request, string $type, array $aggregates): Response|ResponseFactory
    {
        if ($denied = $this->denied($request, 'viewPulse')) {
            return $denied;
        }

        $validated = $request->validate([
            'period' => ['nullable', 'in:1h,6h,24h,7d'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.ResultLimit::cap(config('porthole.limits.max'))],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        $period = (string) ($validated['period'] ?? '24h');

        return Response::structured([
            'type' => $type,
            'period' => $period,
            'rows' => app(PulseSnapshot::class)->rows(
                $type,
                $period,
                $aggregates,
                ResultLimit::cap($validated['limit'] ?? null),
                isset($validated['search']) ? (string) $validated['search'] : null,
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function periodSchema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->description('One of 1h, 6h, 24h, or 7d.')->enum(['1h', '6h', '24h', '7d']),
            'limit' => $schema->integer()->description('Maximum rows to return.'),
            'search' => $schema->string()->description('Case-insensitive filter applied to the row key after it is read.'),
        ];
    }
}
