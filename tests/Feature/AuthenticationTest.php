<?php

declare(strict_types=1);

use Chkltlabs\ObservabilityMcp\Http\Middleware\RequireObservabilityAuthentication;
use Chkltlabs\ObservabilityMcp\ObservabilityMcpServiceProvider;
use Chkltlabs\ObservabilityMcp\Tests\ObservabilityUser;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Gate;

it('rejects a guest before the mcp handler runs', function () {
    $this->postJson('/mcp/observability', [])->assertUnauthorized();
});

it('rejects an authenticated account that is not allowed through the gate', function () {
    $this->actingAs(new ObservabilityUser)
        ->postJson('/mcp/observability', [])
        ->assertForbidden();
});

it('lets an authorized account past the authentication middleware', function () {
    Gate::define('viewObservabilityMcp', fn (): bool => true);

    $response = $this->actingAs(new ObservabilityUser)
        ->postJson('/mcp/observability', []);

    expect($response->status())->not->toBe(401)->and($response->status())->not->toBe(403);
});

it('puts authentication ahead of protocol header checks', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route): bool => $route->uri() === 'mcp/observability' && in_array('POST', $route->methods(), true));

    expect($route)->not->toBeNull()
        ->and($route->middleware()[0])->toBe(RequireObservabilityAuthentication::class);
});

it('does not register the http server when the package is disabled', function () {
    config(['observability-mcp.enabled' => false]);

    $provider = app()->getProvider(ObservabilityMcpServiceProvider::class);
    app('router')->setRoutes(new RouteCollection);

    $provider->boot();

    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route): bool => $route->uri() === 'mcp/observability');

    expect($route)->toBeNull();
});
