<?php

declare(strict_types=1);

use Chkltlabs\Porthole\Support\EnvBoolean;

$authMiddleware = env('PORTHOLE_AUTH_MIDDLEWARE');

return [

    /*
    |--------------------------------------------------------------------------
    | Server
    |--------------------------------------------------------------------------
    |
    | The HTTP server is registered when enabled and auth.middleware is set.
    | Publish this file and edit it in your application. Environment
    | variables below override the dangerous keys per deploy. An
    | unrecognized value keeps the secure default.
    |
    */

    'enabled' => true,

    'path' => 'mcp/porthole',

    'auth' => [
        /*
        | Legacy fallback used only when http_required or stdio_required is
        | missing from an older published file.
        */
        'required' => true,

        'http_required' => EnvBoolean::parse(env('PORTHOLE_HTTP_AUTH_REQUIRED', true), true),

        'stdio_required' => EnvBoolean::parse(env('PORTHOLE_STDIO_AUTH_REQUIRED', true), true),

        /*
        | Middleware that authenticates the caller before the package gate runs.
        | Use "auth:api" or "auth:sanctum" for bearer tokens. When this list is
        | empty, the HTTP server is not registered. The package appends its own
        | middleware, which returns 401 or 403 and does not redirect.
        |
        | PORTHOLE_AUTH_MIDDLEWARE replaces this list when it is set. Separate
        | middleware names with commas.
        */
        'middleware' => is_string($authMiddleware) && trim($authMiddleware) !== ''
            ? array_values(array_filter(array_map(
                static fn (string $middleware): string => trim($middleware),
                explode(',', $authMiddleware),
            )))
            : [
                // 'auth:api',
            ],

        'guard' => null,

        /*
        | Laravel throttle signature for the HTTP route, such as "60,1".
        | An empty string disables the limiter. Stdio is not throttled.
        */
        'throttle' => '60,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Writes
    |--------------------------------------------------------------------------
    |
    | Read tools are the only tools registered unless these flags are enabled.
    | "writes_enabled" allows pausing Horizon, resuming it, and retrying a
    | failed job. Pause, continue, and retry also require a passing
    | mutateHorizon gate. "destructive_enabled" allows Telescope prune, and
    | it is a separate opt-in so queue control does not also delete debug
    | history. Outside the local environment, prune retention cannot drop
    | below 24 hours.
    |
    */

    'writes_enabled' => EnvBoolean::parse(env('PORTHOLE_WRITES_ENABLED', false), false),

    'destructive_enabled' => EnvBoolean::parse(env('PORTHOLE_DESTRUCTIVE_ENABLED', false), false),

    'writes' => [
        /*
        | Display names of failed jobs that horizon-retry-failed-job may
        | dispatch. An empty list refuses every retry. PORTHOLE_RETRY_ALLOWLIST
        | is a comma-separated list of those display names.
        */
        'retry_allowlist' => array_values(array_filter(array_map(
            static fn (string $name): string => trim($name),
            explode(',', (string) env('PORTHOLE_RETRY_ALLOWLIST', '')),
        ))),
    ],

    'prune_min_hours' => 24,

    'prune_max_hours' => 168,

    'prune_allow_short_retention' => EnvBoolean::parse(env('PORTHOLE_PRUNE_ALLOW_SHORT_RETENTION', false), false),

    /*
    |--------------------------------------------------------------------------
    | Local stdio and OAuth
    |--------------------------------------------------------------------------
    |
    | Stdio has no transport credentials, so it stays off until you enable it,
    | and it registers only when the application environment is "local".
    | Tool calls still require an authenticated, authorized user unless
    | auth.stdio_required is false. That flag does not affect HTTP. OAuth
    | uses an issuer that belongs to Porthole. It does not call Passport,
    | register clients on the application's oauth_clients table, or change
    | the application's scopes. Routes register only when oauth.enabled is
    | true and the environment is local or oauth.allow_non_local is true.
    | Dynamic client registration stays off unless oauth.dynamic_registration
    | is true. Run the porthole OAuth migrations before enabling it.
    |
    */

    'stdio' => [
        'enabled' => false,
        'handle' => 'porthole',
    ],

    'oauth' => [
        'enabled' => EnvBoolean::parse(env('PORTHOLE_OAUTH_ENABLED', false), false),
        'dynamic_registration' => EnvBoolean::parse(env('PORTHOLE_OAUTH_DYNAMIC_REGISTRATION', false), false),
        'allow_non_local' => EnvBoolean::parse(env('PORTHOLE_OAUTH_ALLOW_NON_LOCAL', false), false),

        /*
        | Route prefix for the Porthole authorization server. This must not be
        | "oauth", which is the application's Passport issuer.
        */
        'prefix' => env('PORTHOLE_OAUTH_PREFIX', 'porthole/oauth'),

        /*
        | Browser session used while a person approves an MCP client. Token
        | requests do not use this guard. user_provider overrides the provider
        | used to resolve that person from a Porthole access token.
        */
        'web_guard' => 'web',
        'user_provider' => null,
        'login' => 'login',

        /*
        | Extra redirect schemes for native MCP clients, comma-separated in
        | PORTHOLE_OAUTH_REDIRECT_SCHEMES. http is limited to loopback hosts.
        | https is allowed. The application's mcp.custom_schemes list is also
        | honored.
        */
        'redirect_schemes' => array_values(array_filter(array_map(
            static fn (string $scheme): string => strtolower(trim($scheme)),
            explode(',', (string) env('PORTHOLE_OAUTH_REDIRECT_SCHEMES', '')),
        ))),
    ],

    'limits' => [
        'default' => 25,
        'max' => 100,
        'string' => 500,
        'trace_frames' => 8,
    ],

];
