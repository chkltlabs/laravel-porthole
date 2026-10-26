# Laravel Porthole

[![Latest Version on Packagist](https://img.shields.io/packagist/v/chkltlabs/laravel-porthole.svg?style=flat-square)](https://packagist.org/packages/chkltlabs/laravel-porthole)
[![Total Downloads](https://img.shields.io/packagist/dt/chkltlabs/laravel-porthole.svg?style=flat-square)](https://packagist.org/packages/chkltlabs/laravel-porthole)
[![Tests](https://img.shields.io/github/actions/workflow/status/chkltlabs/laravel-porthole/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/chkltlabs/laravel-porthole/actions/workflows/tests.yml)

Authenticated [Model Context Protocol](https://modelcontextprotocol.io) server for Laravel Horizon, Pulse, and Telescope. Built on [laravel/mcp](https://github.com/laravel/mcp).

Read tools are registered by default. Write tools stay unregistered until you opt in. Callers need an authenticated user who passes `viewPorthole`, plus the package gate for that source (`viewHorizon`, `viewPulse`, or `viewTelescope`). An undefined gate denies the call.

## Requirements

- PHP 8.2+
- Laravel 11.45, 12.41, or 13
- `laravel/mcp` 1.x
- At least one of `laravel/horizon`, `laravel/pulse`, or `laravel/telescope`

## Install

```bash
composer require chkltlabs/laravel-porthole
```

The next Artisan command copies the example to `config/porthole.php` when that file is missing. Edit that file. Republish it with:

```bash
php artisan vendor:publish --tag=porthole-config
```

`vendor:publish` leaves an existing file in place unless you pass `--force`.

The HTTP endpoint is `POST /mcp/porthole`.

## Authorization

Define gates before the package boots. There is no permissive default.

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

Gate::define('viewPorthole', function (User $user): bool {
    return $user->hasRole('staff');
});

Gate::define('mutateHorizon', function (User $user): bool {
    return $user->hasRole('staff');
});
```

An institution admin is not this role. `viewPorthole` is a staff gate for reading operational data. `mutateHorizon` is a separate staff gate for pausing Horizon, resuming it, and retrying a job. A missing `mutateHorizon` gate denies those writes.

Horizon, Pulse, and Telescope still apply their own gates. A caller needs both `viewPorthole` and the source gate.

Point HTTP auth at the guard your MCP clients already use. With the Porthole OAuth issuer off, Porthole does **not** register `POST /mcp/porthole` when `auth.middleware` is empty, including when `auth.http_required` is false. With the issuer on, an empty list is valid because the package authenticates its own access tokens.

```php
'auth' => [
    'middleware' => [
        'auth:api',
    ],
    'throttle' => '60,1',
],
```

Sanctum apps can use `auth:sanctum` instead. Set `auth.throttle` to an empty string to disable the limiter.

OAuth stays off until `oauth.enabled` is true and the app is running in `local` (or `oauth.allow_non_local` is true). Porthole then serves its own authorization server at `/porthole/oauth`. That issuer has its own client table and the `mcp:use` scope. It does not register Passport routes, call `Passport::tokensCan()`, or change `ignoreRoutes()` and `$user->createToken()`. A token from this flow is not an application API token.

The MCP client URL stays `https://your-app.test/mcp/porthole`. The client discovers `/porthole/oauth` from the `401` response. Do not put the issuer in the client's `url` field, and do not send it to the application's Passport server.

Dynamic client registration stays off until `oauth.dynamic_registration` is true. With it off, create a public client with `php artisan porthole:oauth-client`. Enable OAuth, then run `php artisan migrate`. Porthole loads its OAuth migrations in that mode. Do not also publish `porthole-migrations` into `database/migrations`, or migrate runs the same tables twice. When OAuth is on, `auth.middleware` can stay empty. Do not put `auth:api` in front of these tokens.

HTTP and stdio auth are separate keys in the same file:

```php
'auth' => [
    'http_required' => true,
    'stdio_required' => false,
],
```

`stdio_required` set to false trusts the local process and skips `viewPorthole` and the source gates for stdio tool calls only. It does not open HTTP. `auth.required` is a legacy fallback used only when a transport flag is missing from an older published file.

## Connect an AI coding agent

Porthole speaks MCP. Your editor or CLI agent is the MCP *client*. There are two ways to attach:

| Transport | How the client reaches Porthole | Typical auth |
| --- | --- | --- |
| **Local stdio** | Client spawns `php artisan mcp:start porthole` in your app. Registered only when the Laravel environment is `local`. | Trust the machine, or disable auth for that local env |
| **HTTP** | Client calls `https://your-app.test/mcp/porthole` | Porthole OAuth, or a bearer token from `auth:api` / Sanctum |

Pasting a stdio JSON block into an MCP settings file does **not** open a browser login. Cursor (and most other tools) start a local process and talk over stdin/stdout. Auth still runs inside each tool call.

### What happens after you add the config

1. Restart the agent / reload MCP so the client picks up the new server.
2. For **stdio**, the client runs `php artisan mcp:start porthole` from your Laravel app directory (set `cwd` or use absolute paths).
3. The client lists tools. Read tools appear when Horizon, Pulse, and/or Telescope are installed.
4. The first tool call checks `$request->user()` and the gates.
5. On stdio there is normally **no logged-in user**, so you get `Authentication is required to read Porthole data` until you either:
   - set `auth.stdio_required` to false in `config/porthole.php` for that local app (HTTP stays authenticated), or
   - switch to **HTTP** and send a Porthole OAuth token, or a bearer token your `auth` middleware already accepts.

### App setup by transport

**Local stdio (laptop / same machine as the app)**

Stdio registers only when `APP_ENV=local` and `stdio.enabled` is true. Staging, production, and any other environment ignore the flag.

```php
'auth' => [
    'http_required' => true,
    'stdio_required' => true, // false trusts this machine only
    'middleware' => ['auth:api'],
],

'stdio' => [
    'enabled' => true,
],
```

Confirm the handle:

```bash
php artisan list mcp
```

**HTTP + bearer token (same team, fixed token)**

```php
'auth' => [
    'middleware' => ['auth:api'],
],
```

Issue a personal access token for a user that passes `viewPorthole` (and the source gates). Clients send:

```http
Authorization: Bearer YOUR_ACCESS_TOKEN
```

Sanctum apps use `auth:sanctum` and a Sanctum token in that same header.

**HTTP + Porthole OAuth (external / interactive clients)**

```php
'auth' => [
    'middleware' => [],
],

'oauth' => [
    'enabled' => true,
    'dynamic_registration' => false,
    'allow_non_local' => false,
    'prefix' => 'porthole/oauth',
],
```

Enable OAuth and run `php artisan migrate`. The client calls `https://your-app.test/mcp/porthole` with no bearer header. A `401` points it at `/.well-known/oauth-protected-resource/mcp/porthole`, and that document names the `/porthole/oauth` issuer. The browser login uses the `web` guard and `viewPorthole`. The access token is stored in `porthole_oauth_tokens` and is accepted only by this package.

Leave `dynamic_registration` false unless you intend to expose `POST /porthole/oauth/register`. That route writes Porthole clients, not Passport clients. Leave `allow_non_local` false outside local development. Do not set `prefix` to `oauth`.

Do not commit real tokens. Prefer env vars / your tool’s secret inputs.

### Shared config snippets

**Stdio** (run from the Laravel app root, or set `cwd`):

```json
{
  "command": "php",
  "args": ["artisan", "mcp:start", "porthole"],
  "cwd": "/absolute/path/to/your-app"
}
```

**HTTP with a fixed bearer token** (`auth:api` or Sanctum):

```json
{
  "url": "https://your-app.test/mcp/porthole",
  "headers": {
    "Authorization": "Bearer YOUR_ACCESS_TOKEN"
  }
}
```

**HTTP with Porthole OAuth.** Omit the bearer header so the client can discover the issuer. The per-client examples below that include `Authorization` are the fixed-token path.

```json
{
  "url": "https://your-app.test/mcp/porthole"
}
```

Replace the host with your real app URL. Some clients also need an explicit transport `type` (see below).

### Client examples

Bearer headers in these examples are the fixed-token path (`auth:api` or Sanctum). For Porthole OAuth, keep the same URL and omit `Authorization`.

#### Cursor

- Project: `.cursor/mcp.json`
- User: `~/.cursor/mcp.json`
- Key: `mcpServers`

```json
{
  "mcpServers": {
    "porthole": {
      "command": "php",
      "args": ["artisan", "mcp:start", "porthole"],
      "cwd": "/absolute/path/to/your-app"
    }
  }
}
```

HTTP example:

```json
{
  "mcpServers": {
    "porthole": {
      "url": "https://your-app.test/mcp/porthole",
      "headers": {
        "Authorization": "Bearer YOUR_SANCTUM_TOKEN"
      }
    }
  }
}
```

#### VS Code / GitHub Copilot

- Workspace: `.vscode/mcp.json` — top-level key is **`servers`** (not `mcpServers`)
- Or portable: `.mcp.json` / `~/.copilot/mcp-config.json` with `mcpServers`

```json
{
  "servers": {
    "porthole": {
      "type": "stdio",
      "command": "php",
      "args": ["artisan", "mcp:start", "porthole"],
      "cwd": "${workspaceFolder}"
    }
  }
}
```

HTTP:

```json
{
  "servers": {
    "porthole": {
      "type": "http",
      "url": "https://your-app.test/mcp/porthole",
      "headers": {
        "Authorization": "Bearer ${input:porthole-token}"
      }
    }
  }
}
```

#### Claude Code

- Project: `.mcp.json`
- User: `~/.claude.json`
- Key: `mcpServers`
- CLI: `claude mcp add`

Stdio:

```bash
claude mcp add --transport stdio porthole -- php artisan mcp:start porthole
```

Run that from the Laravel app root, or pass an absolute path to `artisan`.

HTTP:

```bash
claude mcp add --transport http porthole https://your-app.test/mcp/porthole \
  --header "Authorization: Bearer YOUR_SANCTUM_TOKEN"
```

JSON (remote entries **must** include `"type": "http"`):

```json
{
  "mcpServers": {
    "porthole": {
      "type": "http",
      "url": "https://your-app.test/mcp/porthole",
      "headers": {
        "Authorization": "Bearer YOUR_SANCTUM_TOKEN"
      }
    }
  }
}
```

#### Claude Desktop

- macOS: `~/Library/Application Support/Claude/claude_desktop_config.json`
- Windows: `%APPDATA%\Claude\claude_desktop_config.json`
- Key: `mcpServers` (stdio is the usual Desktop path)

```json
{
  "mcpServers": {
    "porthole": {
      "command": "php",
      "args": [
        "/absolute/path/to/your-app/artisan",
        "mcp:start",
        "porthole"
      ]
    }
  }
}
```

#### Windsurf / Devin Desktop

- Current: `~/.config/devin/mcp_config.json` (Windows: `%APPDATA%\devin\mcp_config.json`)
- Older Windsurf: `~/.codeium/windsurf/mcp_config.json`
- Key: `mcpServers`

```json
{
  "mcpServers": {
    "porthole": {
      "command": "php",
      "args": ["artisan", "mcp:start", "porthole"],
      "cwd": "/absolute/path/to/your-app"
    }
  }
}
```

#### Zed

- macOS / Linux: `~/.config/zed/settings.json`
- Windows: `%APPDATA%\Zed\settings.json`
- Key: **`context_servers`**

```json
{
  "context_servers": {
    "porthole": {
      "command": {
        "path": "php",
        "args": [
          "/absolute/path/to/your-app/artisan",
          "mcp:start",
          "porthole"
        ]
      }
    }
  }
}
```

(Zed’s exact `command` shape can vary by version; if the nested object fails, try a flat `command` + `args` under the same key.)

#### OpenAI Codex CLI

- User: `~/.codex/config.toml`
- Project: `.codex/config.toml` (trusted projects)
- Tables: `[mcp_servers.<name>]`

```toml
[mcp_servers.porthole]
command = "php"
args = ["artisan", "mcp:start", "porthole"]
cwd = "/absolute/path/to/your-app"
```

#### Gemini CLI

- User: `~/.gemini/settings.json`
- Project: `.gemini/settings.json`
- Key: `mcpServers`
- Remote HTTP uses **`httpUrl`**, not `url`

```json
{
  "mcpServers": {
    "porthole": {
      "command": "php",
      "args": ["artisan", "mcp:start", "porthole"],
      "cwd": "/absolute/path/to/your-app"
    }
  }
}
```

HTTP:

```json
{
  "mcpServers": {
    "porthole": {
      "httpUrl": "https://your-app.test/mcp/porthole",
      "headers": {
        "Authorization": "Bearer YOUR_SANCTUM_TOKEN"
      }
    }
  }
}
```

#### Cline

- VS Code global storage settings (path varies by OS), or `CLINE_MCP_SETTINGS_PATH`
- Key: `mcpServers`

```json
{
  "mcpServers": {
    "porthole": {
      "command": "php",
      "args": ["artisan", "mcp:start", "porthole"],
      "cwd": "/absolute/path/to/your-app"
    }
  }
}
```

For remote HTTP, set an explicit streamable HTTP type (Cline defaults to legacy SSE if omitted), for example `"type": "streamableHttp"` plus `url` / headers per current Cline docs.

#### Continue

Drop a Cursor/Claude-style JSON file into `.continue/mcpServers/` (plural), or use YAML with a **list** of servers:

```yaml
name: Porthole
version: 0.0.1
schema: v1
mcpServers:
  - name: porthole
    command: php
    args:
      - artisan
      - mcp:start
      - porthole
    cwd: /absolute/path/to/your-app
```

#### JetBrains AI Assistant

Open the MCP settings UI and paste a standard `mcpServers` JSON block (stdio or HTTP). Prefer absolute paths to `artisan` so the IDE’s working directory does not matter.

#### Roo Code

- Global: VS Code `globalStorage/.../mcp_settings.json`
- Project: `.roo/mcp.json`
- Key: `mcpServers` (same shape as Cursor)

### Quick troubleshooting

| Symptom | Likely cause |
| --- | --- |
| `mcp:start porthole` unknown / server missing | `stdio.enabled` is false, or the app environment is not `local` |
| `artisan` not found | Wrong `cwd` / missing absolute path |
| `Authentication is required to read Porthole data` | Stdio with no user; set `auth.stdio_required` to false locally, or switch to HTTP + token |
| HTTP 404 on `/mcp/porthole` | The Porthole issuer is off and `auth.middleware` is empty, so the route was not registered |
| `You are not authorized to use Porthole` | User is authenticated but `viewPorthole` is missing or fails |
| VS Code ignores a pasted Cursor file | Workspace file must use `"servers"`, not `"mcpServers"` |
| Claude Code skips a remote entry | Add `"type": "http"` next to `url` |

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

Horizon job payloads are reduced to the display name. Telescope query bindings, cookies, authorization headers, and trace arguments are removed. Sensitive array keys such as `password`, `token`, `secret`, `email`, `account_number`, `routing_number`, and `private_key` are replaced with `[redacted]`. Quoted SQL strings and unquoted numeric literals are masked. URLs lose their query strings, including in exception, log, and job-exception text. This is not a tenant boundary: a caller who can read a source can still see that source's operational data.

## Writes

These tools are absent from `tools/list` until the matching flag is true. Calling one while the flag is off returns an error and does not touch Horizon or Telescope.

```php
'writes_enabled' => true,
'destructive_enabled' => true,
```

`writes_enabled` registers `horizon-pause`, `horizon-continue`, and `horizon-retry-failed-job`. Those tools also require a defined, passing `mutateHorizon` gate. Retry stays refused unless `writes.retry_allowlist` contains the failed job's display name. An empty allowlist refuses every retry.

`destructive_enabled` registers `telescope-prune`. Retention hours must fall between the prune floor and `prune_max_hours` (default 168). Outside `local`, the floor is at least 24 hours even when `prune_min_hours` is lower. `prune_allow_short_retention` is honored only when the environment is `local`.

## Tests

```bash
composer install
composer test
composer test:coverage
```

CI requires at least **60%** line coverage under `src/` (`pest --coverage --min=60`).
