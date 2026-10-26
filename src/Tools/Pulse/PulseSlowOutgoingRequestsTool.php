<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Tools\Pulse;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PulseSlowOutgoingRequestsTool extends Tool
{
    use ReadsPulseAggregate;

    protected string $name = 'pulse-slow-outgoing-requests';

    protected string $description = 'List slow outgoing HTTP calls recorded by Pulse. URL query values inside the key are masked when they are quoted.';

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->report($request, 'slow_outgoing_request', ['count', 'max', 'avg']);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->periodSchema($schema);
    }
}
