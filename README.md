# Laravel Observability MCP

Authenticated Model Context Protocol server for Laravel Horizon, Pulse, and Telescope. It is built on [laravel/mcp](https://github.com/laravel/mcp).

Read tools are registered by default. Write tools stay unregistered until you opt in. Every HTTP call requires an authenticated user who passes the `viewObservabilityMcp` gate, and each tool also checks the gate for that package (`viewHorizon`, `viewPulse`, or `viewTelescope`). If a gate is missing, the call is denied.

## Requirements

- PHP 8.2+
- Laravel 11.45, 12.41, or 13
- `laravel/mcp` 1.x
- At least one of `laravel/horizon`, `laravel/pulse`, or `laravel/telescope`

## Install

```bash
composer require chkltlabs/laravel-observability-mcp
```

Until the package is on Packagist, require it from Git:

```bash
composer config repositories.observability-mcp vcs https://github.com/chkltlabs/laravel-observability-mcp.git
composer require chkltlabs/laravel-observability-mcp:dev-main
```

Publish the configuration when you want to commit overrides:

```bash
php artisan vendor:publish --tag=observability-mcp-config
```

The HTTP server is `POST /mcp/observability`.

## Authorization

Define the gate in `AppServiceProvider::boot()` before this package boots. The package does not define a permissive default. An undefined gate denies every caller.

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

Gate::define('viewObservabilityMcp', function (User $user): bool {
    return (bool) $user->is_admin;
});
```

Horizon, Pulse, and Telescope still apply their own gates. A caller needs both `viewObservabilityMcp` and the source gate.

Authenticate the route with the guard your MCP clients already use:

```env
OBSERVABILITY_MCP_AUTH_MIDDLEWARE=auth:sanctum
```

Passport apps should use `auth:api`. For OAuth discovery and dynamic client registration, set `OBSERVABILITY_MCP_OAUTH_ENABLED=true`. Leave that off until you intend to expose the public registration route. The HTTP server still requires authentication.

`OBSERVABILITY_MCP_AUTH_REQUIRED=false` removes the authentication check. Leave it unset on any shared environment. Unrecognized values keep authentication required.

## What an agent can read

| Tool | Source |
| --- | --- |
| `horizon-status` | Masters, supervisors, workload, job counts |
| `horizon-jobs` | Recent, pending, completed, failed, or silenced jobs |
| `horizon-metrics` | Throughput and average runtime |
| `pulse-health` | Ingest driver and one-hour counts |
| `pulse-overview` | Totals plus the top slow requests, queries, and exceptions |
| `pulse-exceptions` | Exception class and location counts |
| `pulse-slow-queries` | Slow SQL with literals masked |
| `pulse-slow-requests` | Slow routes |
| `pulse-slow-jobs` | Slow jobs |
| `pulse-slow-outgoing-requests` | Slow HTTP client calls |
| `pulse-queues` | Queued, processing, processed, released, and failed counts |
| `pulse-servers` | Latest CPU, memory, and disk samples |
| `telescope-list` | Entries by type, tag, batch, or family |
| `telescope-show` | One entry and a capped slice of its batch |

Pulse periods are `1h`, `6h`, `24h`, and `7d`.

Horizon job payloads are reduced to the display name. Telescope query bindings, cookies, authorization headers, and trace arguments are removed. Sensitive array keys such as `password`, `token`, and `secret` are replaced with `[redacted]`.

## Writes

These tools are absent from `tools/list` until the matching flag is true. Calling one while the flag is off returns an error and does not touch Horizon or Telescope.

```env
OBSERVABILITY_MCP_WRITES_ENABLED=true
OBSERVABILITY_MCP_DESTRUCTIVE_ENABLED=true
```

`writes_enabled` registers `horizon-pause`, `horizon-continue`, and `horizon-retry-failed-job`.

`destructive_enabled` registers `telescope-prune`. Retention hours are capped by `OBSERVABILITY_MCP_PRUNE_MAX_HOURS` (default 168).

## Local stdio

Stdio is off by default because the process has no transport credentials. Tool calls still require an authenticated user when you enable it:

```env
OBSERVABILITY_MCP_STDIO_ENABLED=true
```

Register it with the Artisan handle `observability`:

```json
{
    "mcpServers": {
        "observability": {
            "command": "php",
            "args": ["artisan", "mcp:start", "observability"]
        }
    }
}
```

Confirm the local command name with `php artisan list mcp`.

## Tests

```bash
composer install
composer test
```
