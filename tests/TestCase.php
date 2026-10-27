<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Tests;

use Chkltlabs\Porthole\PortholeServiceProvider;
use Illuminate\Http\Middleware\ValidatePostSize;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Config applied before providers boot. Set this, then call refreshApplication().
     *
     * @var array<string, mixed>
     */
    public array $bootConfig = [];

    protected function getPackageProviders($app): array
    {
        return [
            McpServiceProvider::class,
            PortholeServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('porthole.auth.middleware', [ValidatePostSize::class]);

        foreach ($this->bootConfig as $key => $value) {
            $app['config']->set($key, $value);
        }
    }
}
