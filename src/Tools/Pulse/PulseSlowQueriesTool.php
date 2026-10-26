<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Tools\Pulse;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PulseSlowQueriesTool extends Tool
{
    use ReadsPulseAggregate;

    protected string $name = 'pulse-slow-queries';

    protected string $description = 'List slow database queries recorded by Pulse. SQL literals are masked and bindings are not stored.';

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->report($request, 'slow_query', ['count', 'max', 'avg']);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->periodSchema($schema);
    }
}
