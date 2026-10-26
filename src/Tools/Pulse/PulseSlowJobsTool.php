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
class PulseSlowJobsTool extends Tool
{
    use ReadsPulseAggregate;

    protected string $name = 'pulse-slow-jobs';

    protected string $description = 'List slow queued jobs recorded by Pulse, with count and duration.';

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->report($request, 'slow_job', ['count', 'max', 'avg']);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->periodSchema($schema);
    }
}
