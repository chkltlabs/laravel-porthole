<?php

declare(strict_types=1);

use Chkltlabs\Porthole\Support\AuthFlags;
use Chkltlabs\Porthole\Support\EnvBoolean;

$legacyAuth = env('PORTHOLE_AUTH_REQUIRED');
$pruneMinHours = max(1, (int) env('PORTHOLE_PRUNE_MIN_HOURS', 24));
$pruneMaxHours = max($pruneMinHours, (int) env('PORTHOLE_PRUNE_MAX_HOURS', 168));

return [

    /*
    |--------------------------------------------------------------------------
    | Server
    |--------------------------------------------------------------------------
    |
    | The HTTP server is registered when enabled. HTTP authentication stays
    | required until PORTHOLE_HTTP_AUTH_REQUIRED is a real boolean false.
    | An unrecognized value keeps the secure default.
    |
    | PORTHOLE_AUTH_REQUIRED is a legacy switch. It applies only to a transport
    | whose specific flag is unset.
    |
    */

    'enabled' => EnvBoolean::parse(env('PORTHOLE_ENABLED', true), true),

    'path' => env('PORTHOLE_PATH', 'mcp/porthole'),

    'auth' => [
        'required' => EnvBoolean::parse($legacyAuth ?? true, true),

        'http_required' => AuthFlags::resolve(env('PORTHOLE_HTTP_AUTH_REQUIRED'), $legacyAuth),

        'stdio_required' => AuthFlags::resolve(env('PORTHOLE_STDIO_AUTH_REQUIRED'), $legacyAuth),

        /*
        | Middleware that authenticates the caller before the package gate runs.
        | Use "auth:sanctum" or "auth:api" for bearer tokens. When HTTP auth is
        | required and this list is empty, the HTTP server is not registered.
        | The package appends its own middleware, which returns 401 or 403 and
        | does not redirect.
        */
        'middleware' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('PORTHOLE_AUTH_MIDDLEWARE', '')),
        ))),

        'guard' => env('PORTHOLE_GUARD'),

        /*
        | Laravel throttle signature for the HTTP route, such as "60,1".
        | An empty value disables the limiter. Stdio is not throttled.
        */
        'throttle' => env('PORTHOLE_HTTP_THROTTLE', '60,1'),
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

    'prune_min_hours' => $pruneMinHours,

    'prune_max_hours' => $pruneMaxHours,

    /*
    |--------------------------------------------------------------------------
    | Local stdio and OAuth
    |--------------------------------------------------------------------------
    |
    | Stdio has no transport credentials, so it is off until you enable it.
    | Tool calls still require an authenticated, authorized user unless
    | PORTHOLE_STDIO_AUTH_REQUIRED is explicitly false. That flag does not
    | affect HTTP. OAuth route registration is off until you enable it,
    | because dynamic client registration is a public endpoint.
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
