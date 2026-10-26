<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole;

use Chkltlabs\Porthole\Horizon\ArtisanHorizonOperator;
use Chkltlabs\Porthole\Horizon\HorizonOperator;
use Chkltlabs\Porthole\Http\Middleware\RequirePortholeAuthentication;
use Chkltlabs\Porthole\Support\AuthFlags;
use Chkltlabs\Porthole\Support\ConfigExample;
use Chkltlabs\Porthole\Support\EnvBoolean;
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
        $this->registerLocalStdio();
        $this->registerOAuth();
    }

    private function registerHttpServer(): void
    {
        $middleware = array_values(array_filter(
            (array) config('porthole.auth.middleware', []),
            fn (mixed $entry): bool => is_string($entry) && $entry !== '',
        ));

        if ($middleware === []) {
            Log::warning('Porthole HTTP server was not registered because porthole.auth.middleware is empty.');

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

    private function registerLocalStdio(): void
    {
        if (config('porthole.stdio.enabled') !== true) {
            return;
        }

        if (! $this->app->environment('local')) {
            Log::warning('Porthole stdio was not registered because the application environment is not local.');

            return;
        }

        Mcp::local((string) config('porthole.stdio.handle', 'porthole'), PortholeServer::class);
    }

    private function registerOAuth(): void
    {
        if (! EnvBoolean::parse(config('porthole.oauth.enabled'), false) || ! class_exists(Passport::class)) {
            return;
        }

        $local = $this->app->environment('local');
        $allowNonLocal = EnvBoolean::parse(config('porthole.oauth.allow_non_local'), false);

        if (! $local && ! $allowNonLocal) {
            Log::warning('Porthole OAuth routes were not registered because the application environment is not local and porthole.oauth.allow_non_local is false.');

            return;
        }

        Mcp::oauthRoutes('oauth', EnvBoolean::parse(config('porthole.oauth.dynamic_registration'), false));
    }
}
