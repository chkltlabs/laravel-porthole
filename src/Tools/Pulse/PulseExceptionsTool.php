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
class PulseExceptionsTool extends Tool
{
    use ReadsPulseAggregate;

    protected string $name = 'pulse-exceptions';

    protected string $description = 'List Pulse exceptions by class and location for a period. Messages and stack traces are not stored by Pulse.';

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->report($request, 'exception', ['count']);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->periodSchema($schema);
    }
}
