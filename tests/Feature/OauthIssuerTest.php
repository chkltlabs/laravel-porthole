<?php

declare(strict_types=1);

use Chkltlabs\Porthole\Http\Middleware\AuthenticatePortholeAccessToken;
use Chkltlabs\Porthole\PortholeServiceProvider;
use Chkltlabs\Porthole\Tests\PortholeUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $database = tempnam(sys_get_temp_dir(), 'porthole-oauth');
    $this->oauthDatabase = $database;

    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite' => [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'porthole.enabled' => true,
        'porthole.oauth.enabled' => true,
        'porthole.oauth.allow_non_local' => true,
        'porthole.oauth.dynamic_registration' => true,
        'porthole.auth.middleware' => [],
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
    ]);

    app()->instance('env', 'production');
    app('router')->setRoutes(new RouteCollection);
    app()->getProvider(PortholeServiceProvider::class)->boot();
    DB::purge('sqlite');
    $this->artisan('migrate', [
        '--database' => 'sqlite',
        '--path' => realpath(__DIR__.'/../../database/migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    Auth::provider('porthole-users', fn (): UserProvider => new class implements UserProvider
    {
        public function retrieveById($identifier): ?Authenticatable
        {
            return (string) $identifier === '1' ? new PortholeUser(['id' => 1]) : null;
        }

        public function retrieveByToken($identifier, $token): ?Authenticatable
        {
            return null;
        }

        public function updateRememberToken(Authenticatable $user, $token): void {}

        public function retrieveByCredentials(array $credentials): ?Authenticatable
        {
            return null;
        }

        public function validateCredentials(Authenticatable $user, array $credentials): bool
        {
            return false;
        }

        public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void {}
    });

    config([
        'auth.providers.users.driver' => 'porthole-users',
    ]);

    Gate::define('viewPorthole', fn (): bool => true);
});

afterEach(function () {
    DB::disconnect('sqlite');

    if (is_string($this->oauthDatabase ?? null) && is_file($this->oauthDatabase)) {
        unlink($this->oauthDatabase);
    }
});

it('advertises the porthole issuer and not the application root', function () {
    $this->getJson('/.well-known/oauth-authorization-server/porthole/oauth')
        ->assertOk()
        ->assertJsonPath('issuer', url('/porthole/oauth'))
        ->assertJsonPath('scopes_supported.0', 'mcp:use')
        ->assertJsonPath('token_endpoint_auth_methods_supported.0', 'none')
        ->assertJsonPath('authorization_endpoint', url('/porthole/oauth/authorize'));

    $this->getJson('/.well-known/oauth-protected-resource/mcp/porthole')
        ->assertOk()
        ->assertJsonPath('authorization_servers.0', url('/porthole/oauth'))
        ->assertJsonPath('resource', url('/mcp/porthole'));

    $this->getJson('/.well-known/oauth-authorization-server')->assertNotFound();
});

it('registers a public client on the porthole table', function () {
    $this->postJson('/porthole/oauth/register', [
        'client_name' => 'Cursor',
        'redirect_uris' => ['http://127.0.0.1:8888/callback'],
    ])->assertCreated()
        ->assertJsonPath('token_endpoint_auth_method', 'none')
        ->assertJsonPath('scope', 'mcp:use');

    expect(Schema::hasTable('oauth_clients'))->toBeFalse()
        ->and(DB::table('porthole_oauth_clients')->count())->toBe(1);

    $this->postJson('/porthole/oauth/register', [
        'client_name' => 'Cursor',
        'redirect_uris' => ['http://evil.test/callback'],
    ])->assertStatus(400);
});

it('issues a porthole access token that is not an application api token', function () {
    $registered = $this->postJson('/porthole/oauth/register', [
        'client_name' => 'Cursor',
        'redirect_uris' => ['http://127.0.0.1:9999/callback'],
    ])->assertCreated()->json();

    $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $consent = $this->actingAs(new PortholeUser, 'web')->get('/porthole/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => $registered['client_id'],
        'redirect_uri' => 'http://127.0.0.1:9999/callback',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'state' => 'state-1',
        'scope' => 'mcp:use',
    ]));

    $consent->assertOk()->assertSee('127.0.0.1');

    preg_match('/name="request" value="([^"]+)"/', $consent->getContent(), $matches);

    $approved = $this->actingAs(new PortholeUser, 'web')->post('/porthole/oauth/authorize', [
        'request' => $matches[1],
        'decision' => 'approve',
        '_token' => csrf_token(),
    ]);

    $approved->assertRedirect();
    parse_str((string) parse_url((string) $approved->headers->get('Location'), PHP_URL_QUERY), $query);

    $tokens = $this->post('/porthole/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $registered['client_id'],
        'code' => $query['code'],
        'redirect_uri' => 'http://127.0.0.1:9999/callback',
        'code_verifier' => $verifier,
    ])->assertOk()->json();

    expect($tokens)->toHaveKeys(['access_token', 'refresh_token'])
        ->and(Schema::hasTable('oauth_access_tokens'))->toBeFalse();

    expect($this->withToken($tokens['access_token'])->postJson('/mcp/porthole', [])->status())->not->toBe(401);

    $this->post('/porthole/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $registered['client_id'],
        'code' => $query['code'],
        'redirect_uri' => 'http://127.0.0.1:9999/callback',
        'code_verifier' => $verifier,
    ])->assertStatus(400);

    $refreshed = $this->post('/porthole/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $registered['client_id'],
        'refresh_token' => $tokens['refresh_token'],
    ])->assertOk()->json();

    $this->post('/porthole/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $registered['client_id'],
        'refresh_token' => $tokens['refresh_token'],
    ])->assertStatus(400);

    $this->withToken($refreshed['access_token'])
        ->postJson('/mcp/porthole', [])
        ->assertUnauthorized();
});

it('registers the http server with the porthole token middleware when auth middleware is empty', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($route): bool => $route->uri() === 'mcp/porthole' && in_array('POST', $route->methods(), true));

    expect($route)->not->toBeNull()
        ->and($route->middleware())->toContain(AuthenticatePortholeAccessToken::class);
});

it('sends a guest from the authorization endpoint to the login route', function () {
    $registered = $this->postJson('/porthole/oauth/register', [
        'client_name' => 'Cursor',
        'redirect_uris' => ['http://127.0.0.1:9999/callback'],
    ])->json();

    $this->get('/porthole/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => $registered['client_id'],
        'redirect_uri' => 'http://127.0.0.1:9999/callback',
        'code_challenge' => 'challenge-challenge-challenge-challenge-challenge',
        'code_challenge_method' => 'S256',
        'state' => 'state-1',
    ]))->assertRedirect('/login');
});
