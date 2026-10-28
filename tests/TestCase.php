<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Tests;

use Chkltlabs\Porthole\Oauth\FilamentHost;
use Chkltlabs\Porthole\Oauth\InstalledStarterKits;
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

    /**
     * Replaces the Filament host before the provider boots. Set this, then call refreshApplication().
     */
    public ?FilamentHost $filamentHost = null;

    /**
     * Replaces starter-kit detection before the provider boots. Set this, then call refreshApplication().
     */
    public ?InstalledStarterKits $starterKits = null;

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

        if ($this->filamentHost instanceof FilamentHost) {
            $app->instance(FilamentHost::class, $this->filamentHost);
        }

        if ($this->starterKits instanceof InstalledStarterKits) {
            $app->instance(InstalledStarterKits::class, $this->starterKits);
        }
    }
}
