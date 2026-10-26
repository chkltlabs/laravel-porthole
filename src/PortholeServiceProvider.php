<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole;

use Chkltlabs\Porthole\Horizon\ArtisanHorizonOperator;
use Chkltlabs\Porthole\Horizon\HorizonOperator;
use Chkltlabs\Porthole\Http\Middleware\RequirePortholeAuthentication;
use Chkltlabs\Porthole\Support\AuthFlags;
use Chkltlabs\Porthole\Support\ConfigExample;
use Chkltlabs\Porthole\Support\Redactor;
use Chkltlabs\Porthole\Telescope\RepositoryTelescopeOperator;
use Chkltlabs\Porthole\Telescope\TelescopeOperator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Passport;

class PortholeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/porthole.php', 'porthole');

        $this->app->singleton(Redactor::class, function (): Redactor {
            return new Redactor(
                maxStringLength: max(1, (int) config('porthole.limits.string', 500)),
            );
        });

        $this->app->singleton(HorizonOperator::class, ArtisanHorizonOperator::class);
        $this->app->singleton(TelescopeOperator::class, RepositoryTelescopeOperator::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/porthole.php' => config_path('porthole.php'),
            ], 'porthole-config');

            if (! $this->app->runningUnitTests()) {
                ConfigExample::publishIfMissing(
                    __DIR__.'/../config/porthole.php',
                    config_path('porthole.php'),
                );
            }
        }

        if (config('porthole.enabled', true) !== true) {
            return;
        }

        $this->registerHttpServer();

        if (config('porthole.stdio.enabled') === true) {
            Mcp::local((string) config('porthole.stdio.handle', 'porthole'), PortholeServer::class);
        }

        if (config('porthole.oauth.enabled') === true && class_exists(Passport::class)) {
            Mcp::oauthRoutes();
        }
    }

    private function registerHttpServer(): void
    {
        $middleware = array_values(array_filter(
            (array) config('porthole.auth.middleware', []),
            fn (mixed $entry): bool => is_string($entry) && $entry !== '',
        ));

        if (AuthFlags::httpRequired() && $middleware === []) {
            Log::warning('Porthole HTTP server was not registered because authentication is required and porthole.auth.middleware is empty.');

            return;
        }

        $throttle = trim((string) config('porthole.auth.throttle', '60,1'));

        if ($throttle !== '') {
            $middleware[] = 'throttle:'.$throttle;
        }

        if (AuthFlags::httpRequired()) {
            $middleware[] = RequirePortholeAuthentication::class;
        }

        $route = Mcp::web(
            '/'.ltrim((string) config('porthole.path', 'mcp/porthole'), '/'),
            PortholeServer::class,
        );

        $route->action['middleware'] = [
            ...$middleware,
            ...($route->action['middleware'] ?? []),
        ];
    }
}
