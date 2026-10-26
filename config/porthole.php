<?php

declare(strict_types=1);

use Chkltlabs\Porthole\Support\EnvBoolean;

return [

    /*
    |--------------------------------------------------------------------------
    | Server
    |--------------------------------------------------------------------------
    |
    | The HTTP server is registered when enabled. Authentication stays required
    | until PORTHOLE_AUTH_REQUIRED is set to a real boolean false.
    | An unrecognized value keeps the secure default.
    |
    */

    'enabled' => EnvBoolean::parse(env('PORTHOLE_ENABLED', true), true),

    'path' => env('PORTHOLE_PATH', 'mcp/porthole'),

    'auth' => [
        'required' => EnvBoolean::parse(env('PORTHOLE_AUTH_REQUIRED', true), true),

        /*
        | Middleware that authenticates the caller before the package gate runs.
        | Use "auth:sanctum" or "auth:api" for bearer tokens. The package always
        | appends its own middleware, which returns 401 or 403 and does not redirect.
        */
        'middleware' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('PORTHOLE_AUTH_MIDDLEWARE', '')),
        ))),

        'guard' => env('PORTHOLE_GUARD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Writes
    |--------------------------------------------------------------------------
    |
    | Read tools are the only tools registered unless these flags are enabled.
    | "writes_enabled" allows pausing Horizon, resuming it, and retrying a
    | failed job. "destructive_enabled" allows Telescope prune, and it is a
    | separate opt-in so queue control does not also delete debug history.
    |
    */

    'writes_enabled' => EnvBoolean::parse(env('PORTHOLE_WRITES_ENABLED', false), false),

    'destructive_enabled' => EnvBoolean::parse(env('PORTHOLE_DESTRUCTIVE_ENABLED', false), false),

    'prune_max_hours' => (int) env('PORTHOLE_PRUNE_MAX_HOURS', 168),

    /*
    |--------------------------------------------------------------------------
    | Local stdio and OAuth
    |--------------------------------------------------------------------------
    |
    | Stdio has no transport credentials, so it is off until you enable it.
    | Tool calls still require an authenticated, authorized user. OAuth route
    | registration is off until you enable it, because dynamic client
    | registration is a public endpoint.
    |
    */

    'stdio' => [
        'enabled' => EnvBoolean::parse(env('PORTHOLE_STDIO_ENABLED', false), false),
        'handle' => env('PORTHOLE_STDIO_HANDLE', 'porthole'),
    ],

    'oauth' => [
        'enabled' => EnvBoolean::parse(env('PORTHOLE_OAUTH_ENABLED', false), false),
    ],

    'limits' => [
        'default' => 25,
        'max' => 100,
        'string' => 500,
        'trace_frames' => 8,
    ],

];
