<?php

declare(strict_types=1);

use Chkltlabs\Porthole\Http\Middleware\RequirePortholeAuthentication;
use Chkltlabs\Porthole\PortholeServiceProvider;
use Chkltlabs\Porthole\Tests\PortholeUser;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Server\Middleware\ValidateMcpHeaders;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;
use Psr\Log\LoggerInterface;

it('rejects a guest before the mcp handler runs', function () {
    $this->postJson('/mcp/porthole', [])->assertUnauthorized();
});

it('rejects an authenticated account that is not allowed through the gate', function () {
    $this->actingAs(new PortholeUser)
        ->postJson('/mcp/porthole', [])
        ->assertForbidden();
});

it('lets an authorized account past the authentication middleware', function () {
    Gate::define('viewPorthole', fn (): bool => true);

    $response = $this->actingAs(new PortholeUser)
        ->postJson('/mcp/porthole', []);

    expect($response->status())->not->toBe(401)->and($response->status())->not->toBe(403);
});

it('puts authentication ahead of protocol header checks', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route): bool => $route->uri() === 'mcp/porthole' && in_array('POST', $route->methods(), true));

    $middleware = $route->middleware();
    $authentication = array_search(RequirePortholeAuthentication::class, $middleware, true);
    $protocol = array_search(ValidateMcpHeaders::class, $middleware, true);

    expect($route)->not->toBeNull()
        ->and($authentication)->toBeInt()
        ->and($protocol)->toBeInt()
        ->and($authentication)->toBeLessThan($protocol)
        ->and($middleware)->toContain('throttle:60,1');
});

it('keeps http authentication when only stdio auth is disabled', function () {
    config([
        'porthole.auth.http_required' => true,
        'porthole.auth.stdio_required' => false,
        'porthole.auth.middleware' => [ValidatePostSize::class],
    ]);

    app('router')->setRoutes(new RouteCollection);
    app()->getProvider(PortholeServiceProvider::class)->boot();

    $this->postJson('/mcp/porthole', [])->assertUnauthorized();
});

it('keeps http authentication when the stored flag is the string true', function () {
    config([
        'porthole.auth.http_required' => 'true',
        'porthole.auth.middleware' => [ValidatePostSize::class],
    ]);

    app('router')->setRoutes(new RouteCollection);
    app()->getProvider(PortholeServiceProvider::class)->boot();

    $this->postJson('/mcp/porthole', [])->assertUnauthorized();
});

it('does not register the http server when middleware is empty', function () {
    config([
        'porthole.enabled' => true,
        'porthole.auth.http_required' => false,
        'porthole.auth.middleware' => [],
    ]);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')->once()->withArgs(
        fn (string $message): bool => str_contains($message, 'porthole.auth.middleware'),
    );
    Log::swap($logger);

    app('router')->setRoutes(new RouteCollection);
    app()->getProvider(PortholeServiceProvider::class)->boot();

    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route): bool => $route->uri() === 'mcp/porthole');

    expect($route)->toBeNull();
});

it('does not register the http server when auth is required and middleware is empty', function () {
    config([
        'porthole.enabled' => true,
        'porthole.auth.http_required' => true,
        'porthole.auth.middleware' => [],
    ]);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')->once()->withArgs(
        fn (string $message): bool => str_contains($message, 'porthole.auth.middleware'),
    );
    Log::swap($logger);

    app('router')->setRoutes(new RouteCollection);
    app()->getProvider(PortholeServiceProvider::class)->boot();

    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route): bool => $route->uri() === 'mcp/porthole');

    expect($route)->toBeNull();
});

it('registers stdio only when the environment is local', function () {
    config(['porthole.enabled' => true, 'porthole.stdio.enabled' => true]);

    app()->instance('env', 'production');
    app()->getProvider(PortholeServiceProvider::class)->boot();

    expect(app(Registrar::class)->getLocalServer('porthole'))->toBeNull();

    app()->instance('env', 'local');
    app()->getProvider(PortholeServiceProvider::class)->boot();

    expect(app(Registrar::class)->getLocalServer('porthole'))->not->toBeNull();
});

it('does not register oauth routes outside local unless that deploy explicitly allows them', function () {
    config([
        'porthole.enabled' => true,
        'porthole.oauth.enabled' => true,
        'porthole.oauth.dynamic_registration' => true,
        'porthole.oauth.allow_non_local' => false,
    ]);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')->once()->withArgs(
        fn (string $message): bool => str_contains($message, 'allow_non_local'),
    );
    Log::swap($logger);

    app()->instance('env', 'production');
    app('router')->setRoutes(new RouteCollection);
    app()->getProvider(PortholeServiceProvider::class)->boot();

    expect(app('router')->has('mcp.oauth.protected-resource.nested'))->toBeFalse()
        ->and(app('router')->has('porthole.oauth.register'))->toBeFalse()
        ->and(app('router')->has('porthole.oauth.authorize'))->toBeFalse();
});

it('registers the porthole issuer without touching the application passport path', function () {
    require_once __DIR__.'/../Fixtures/Passport.php';

    Passport::$scopes = ['mobile' => 'Mobile access'];

    config([
        'porthole.enabled' => true,
        'porthole.oauth.enabled' => true,
        'porthole.oauth.dynamic_registration' => false,
        'porthole.oauth.allow_non_local' => true,
    ]);

    app()->instance('env', 'production');
    app('router')->setRoutes(new RouteCollection);
    app()->getProvider(PortholeServiceProvider::class)->boot();

    $uris = fn (): array => array_map(
        fn ($route): string => $route->uri().' '.implode(',', $route->methods()),
        app('router')->getRoutes()->getRoutes(),
    );

    expect($uris())->toContain('porthole/oauth/authorize GET,HEAD')
        ->and($uris())->toContain('.well-known/oauth-authorization-server/porthole/oauth GET,HEAD')
        ->and($uris())->not->toContain('oauth/register POST')
        ->and($uris())->not->toContain('oauth/authorize GET,HEAD')
        ->and(Passport::$scopes)->toBe(['mobile' => 'Mobile access']);

    config(['porthole.oauth.dynamic_registration' => true]);
    app('router')->setRoutes(new RouteCollection);
    app()->getProvider(PortholeServiceProvider::class)->boot();

    expect($uris())->toContain('porthole/oauth/register POST')
        ->and($uris())->not->toContain('oauth/register POST');
});

it('does not register the http server when the package is disabled', function () {
    config(['porthole.enabled' => false]);

    $provider = app()->getProvider(PortholeServiceProvider::class);
    app('router')->setRoutes(new RouteCollection);

    $provider->boot();

    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route): bool => $route->uri() === 'mcp/porthole');

    expect($route)->toBeNull();
});
