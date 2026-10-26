<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Server
    |--------------------------------------------------------------------------
    |
    | The HTTP server is registered when enabled. Publish this file and edit
    | it in your application. HTTP authentication stays required until
    | http_required is false.
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

        'http_required' => true,

        'stdio_required' => true,

        /*
        | Middleware that authenticates the caller before the package gate runs.
        | Use "auth:sanctum" or "auth:api" for bearer tokens. When HTTP auth is
        | required and this list is empty, the HTTP server is not registered.
        | The package appends its own middleware, which returns 401 or 403 and
        | does not redirect.
        */
        'middleware' => [
            // 'auth:sanctum',
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
    | failed job. "destructive_enabled" allows Telescope prune, and it is a
    | separate opt-in so queue control does not also delete debug history.
    |
    */

    'writes_enabled' => false,

    'destructive_enabled' => false,

    'prune_min_hours' => 24,

    'prune_max_hours' => 168,

    /*
    |--------------------------------------------------------------------------
    | Local stdio and OAuth
    |--------------------------------------------------------------------------
    |
    | Stdio has no transport credentials, so it stays off until you enable it.
    | Tool calls still require an authenticated, authorized user unless
    | auth.stdio_required is false. That flag does not affect HTTP. OAuth
    | route registration stays off until you enable it, because dynamic
    | client registration is a public endpoint.
    |
    */

    'stdio' => [
        'enabled' => false,
        'handle' => 'porthole',
    ],

    'oauth' => [
        'enabled' => false,
    ],

    'limits' => [
        'default' => 25,
        'max' => 100,
        'string' => 500,
        'trace_frames' => 8,
    ],

];
