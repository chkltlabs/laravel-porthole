<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp;

use Chkltlabs\ObservabilityMcp\Horizon\ArtisanHorizonOperator;
use Chkltlabs\ObservabilityMcp\Horizon\HorizonOperator;
use Chkltlabs\ObservabilityMcp\Http\Middleware\RequireObservabilityAuthentication;
use Chkltlabs\ObservabilityMcp\Support\Redactor;
use Chkltlabs\ObservabilityMcp\Telescope\RepositoryTelescopeOperator;
use Chkltlabs\ObservabilityMcp\Telescope\TelescopeOperator;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Passport;

class ObservabilityMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/observability-mcp.php', 'observability-mcp');

        $this->app->singleton(Redactor::class, function (): Redactor {
            return new Redactor(
                maxStringLength: max(1, (int) config('observability-mcp.limits.string', 500)),
            );
        });

        $this->app->singleton(HorizonOperator::class, ArtisanHorizonOperator::class);
        $this->app->singleton(TelescopeOperator::class, RepositoryTelescopeOperator::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/observability-mcp.php' => config_path('observability-mcp.php'),
            ], 'observability-mcp-config');
        }

        if (config('observability-mcp.enabled', true) !== true) {
            return;
        }

        $middleware = array_values(array_filter(
            (array) config('observability-mcp.auth.middleware', []),
            fn (mixed $entry): bool => is_string($entry) && $entry !== '',
        ));

        if (config('observability-mcp.auth.required', true) === true) {
            $middleware[] = RequireObservabilityAuthentication::class;
        }

        $route = Mcp::web(
            '/'.ltrim((string) config('observability-mcp.path', 'mcp/observability'), '/'),
            ObservabilityServer::class,
        );

        $route->action['middleware'] = [
            ...$middleware,
            ...($route->action['middleware'] ?? []),
        ];

        if (config('observability-mcp.stdio.enabled') === true) {
            Mcp::local((string) config('observability-mcp.stdio.handle', 'observability'), ObservabilityServer::class);
        }

        if (config('observability-mcp.oauth.enabled') === true && class_exists(Passport::class)) {
            Mcp::oauthRoutes();
        }
    }
}
