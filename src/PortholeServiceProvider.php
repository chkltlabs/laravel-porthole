<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole;

use Chkltlabs\Porthole\Console\CreateOauthClientCommand;
use Chkltlabs\Porthole\Console\PublishConsentScreenCommand;
use Chkltlabs\Porthole\Horizon\ArtisanHorizonOperator;
use Chkltlabs\Porthole\Horizon\HorizonOperator;
use Chkltlabs\Porthole\Http\Middleware\AuthenticatePortholeAccessToken;
use Chkltlabs\Porthole\Http\Middleware\RequirePortholeAuthentication;
use Chkltlabs\Porthole\Oauth\Discovery;
use Chkltlabs\Porthole\Oauth\FilamentHost;
use Chkltlabs\Porthole\Oauth\InstalledFilamentHost;
use Chkltlabs\Porthole\Oauth\InstalledStarterKits;
use Chkltlabs\Porthole\Oauth\OauthIssuer;
use Chkltlabs\Porthole\Support\AuthFlags;
use Chkltlabs\Porthole\Support\ConfigExample;
use Chkltlabs\Porthole\Support\EnvBoolean;
use Chkltlabs\Porthole\Support\PassportScopes;
use Chkltlabs\Porthole\Support\Redactor;
use Chkltlabs\Porthole\Telescope\RepositoryTelescopeOperator;
use Chkltlabs\Porthole\Telescope\TelescopeOperator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;

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

        $this->app->singleton(FilamentHost::class, InstalledFilamentHost::class);
        $this->app->singleton(InstalledStarterKits::class, fn (): InstalledStarterKits => InstalledStarterKits::application());

        PassportScopes::preserve($this->app);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/porthole.php' => config_path('porthole.php'),
            ], 'porthole-config');

            $this->publishes([
                __DIR__.'/../database/migrations/2026_10_26_000000_create_porthole_oauth_tables.php' => database_path('migrations/2026_10_26_000000_create_porthole_oauth_tables.php'),
            ], 'porthole-migrations');

            $this->commands([
                CreateOauthClientCommand::class,
                PublishConsentScreenCommand::class,
            ]);

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

        $oauth = $this->oauthMode();
        $this->registerHttpServer($oauth);
        $this->registerLocalStdio();
        $this->registerOAuth($oauth);
    }

    private function registerHttpServer(string $oauth): void
    {
        $middleware = array_values(array_filter(
            (array) config('porthole.auth.middleware', []),
            fn (mixed $entry): bool => is_string($entry) && $entry !== '',
        ));

        if ($middleware === [] && $oauth !== 'on') {
            Log::warning('Porthole HTTP server was not registered because porthole.auth.middleware is empty.');

            return;
        }

        if ($oauth === 'on') {
            $middleware[] = AuthenticatePortholeAccessToken::class;
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

    private function registerOAuth(string $oauth): void
    {
        if ($oauth !== 'on') {
            return;
        }

        if (! $this->app->bound('porthole.oauth.migrations')) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'porthole');
            $this->app->instance('porthole.oauth.migrations', true);
        }

        $this->app->make(OauthIssuer::class)->registerRoutes();
    }

    private function oauthMode(): string
    {
        if (! EnvBoolean::parse(config('porthole.oauth.enabled'), false)) {
            return 'off';
        }

        $prefix = Discovery::prefix();

        if ($prefix === '' || $prefix === 'oauth' || str_starts_with($prefix, 'oauth/')) {
            Log::warning('Porthole OAuth was not registered because porthole.oauth.prefix must stay off the application Passport path.');

            return 'blocked';
        }

        if (! $this->app->environment('local') && ! EnvBoolean::parse(config('porthole.oauth.allow_non_local'), false)) {
            Log::warning('Porthole OAuth routes were not registered because the application environment is not local and porthole.oauth.allow_non_local is false.');

            return 'blocked';
        }

        return 'on';
    }
}
