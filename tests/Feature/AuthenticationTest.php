<?php

declare(strict_types=1);

use Chkltlabs\Porthole\Http\Middleware\RequirePortholeAuthentication;
use Chkltlabs\Porthole\PortholeServiceProvider;
use Chkltlabs\Porthole\Tests\PortholeUser;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Gate;

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

    expect($route)->not->toBeNull()
        ->and($route->middleware()[0])->toBe(RequirePortholeAuthentication::class);
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
