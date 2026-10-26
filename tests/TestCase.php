<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Tests;

use Chkltlabs\ObservabilityMcp\ObservabilityMcpServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            McpServiceProvider::class,
            ObservabilityMcpServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }
}
