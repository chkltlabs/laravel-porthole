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

Optional config publish:

```bash
php artisan vendor:publish --tag=porthole-config
```

The HTTP endpoint is `POST /mcp/porthole`.

## Authorization

Define gates before the package boots. There is no permissive default.

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

Gate::define('viewPorthole', function (User $user): bool {
    return (bool) $user->is_admin;
});
```

Horizon, Pulse, and Telescope still apply their own gates. A caller needs both `viewPorthole` and the source gate.

Point HTTP auth at the guard your MCP clients already use:

```env
PORTHOLE_AUTH_MIDDLEWARE=auth:sanctum
```

Passport apps should use `auth:api`. For OAuth discovery and dynamic client registration, set `PORTHOLE_OAUTH_ENABLED=true`. Leave that off until you intend to expose the public registration route.

`PORTHOLE_AUTH_REQUIRED=false` removes the authentication check. Leave it unset on any shared environment. Unrecognized values keep authentication required.

## Connect an AI coding agent

Porthole speaks MCP. Your editor or CLI agent is the MCP *client*. There are two ways to attach:

| Transport | How the client reaches Porthole | Typical auth |
| --- | --- | --- |
| **Local stdio** | Client spawns `php artisan mcp:start porthole` in your app | Trust the machine, or disable auth for that local env |
| **HTTP** | Client calls `https://your-app.test/mcp/porthole` | Sanctum bearer token, or Passport OAuth |

Pasting a stdio JSON block into an MCP settings file does **not** open a browser login. Cursor (and most other tools) start a local process and talk over stdin/stdout. Auth still runs inside each tool call.

### What happens after you add the config

1. Restart the agent / reload MCP so the client picks up the new server.
2. For **stdio**, the client runs `php artisan mcp:start porthole` from your Laravel app directory (set `cwd` or use absolute paths).
3. The client lists tools. Read tools appear when Horizon, Pulse, and/or Telescope are installed.
4. The first tool call checks `$request->user()` and the gates.
5. On stdio there is normally **no logged-in user**, so you get `Authentication is required to read Porthole data` until you either:
   - set `PORTHOLE_AUTH_REQUIRED=false` for that local environment only, or
   - switch to **HTTP** and send a Sanctum/Passport credential.

### App setup by transport

**Local stdio (laptop / same machine as the app)**

```env
PORTHOLE_STDIO_ENABLED=true
# Optional for a private local app only:
# PORTHOLE_AUTH_REQUIRED=false
```

Confirm the handle:

```bash
php artisan list mcp
```

**HTTP + Sanctum (same team, fixed token)**

```env
PORTHOLE_AUTH_MIDDLEWARE=auth:sanctum
```

Issue a personal access token for a user that passes `viewPorthole` (and the source gates). Clients send:

```http
Authorization: Bearer YOUR_SANCTUM_TOKEN
```

**HTTP + Passport OAuth (external / interactive clients)**

```env
PORTHOLE_AUTH_MIDDLEWARE=auth:api
PORTHOLE_OAUTH_ENABLED=true
```

The client discovers OAuth metadata, registers if needed, opens a browser to authorize, then sends the access token on later requests. That flow is handled by Passport + `laravel/mcp`, not by the stdio JSON snippet.

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

**HTTP + Sanctum**:

```json
{
  "url": "https://your-app.test/mcp/porthole",
  "headers": {
    "Authorization": "Bearer YOUR_SANCTUM_TOKEN"
  }
}
```

Replace the host with your real app URL. Some clients also need an explicit transport `type` (see below).

### Client examples

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
| `mcp:start porthole` unknown / server missing | `PORTHOLE_STDIO_ENABLED` is still false |
| `artisan` not found | Wrong `cwd` / missing absolute path |
| `Authentication is required to read Porthole data` | Stdio with no user; use local `PORTHOLE_AUTH_REQUIRED=false` or switch to HTTP + token |
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

Horizon job payloads are reduced to the display name. Telescope query bindings, cookies, authorization headers, and trace arguments are removed. Sensitive array keys such as `password`, `token`, and `secret` are replaced with `[redacted]`.

## Writes

These tools are absent from `tools/list` until the matching flag is true. Calling one while the flag is off returns an error and does not touch Horizon or Telescope.

```env
PORTHOLE_WRITES_ENABLED=true
PORTHOLE_DESTRUCTIVE_ENABLED=true
```

`writes_enabled` registers `horizon-pause`, `horizon-continue`, and `horizon-retry-failed-job`.

`destructive_enabled` registers `telescope-prune`. Retention hours are capped by `PORTHOLE_PRUNE_MAX_HOURS` (default 168).

## Tests

```bash
composer install
composer test
composer test:coverage
```

CI requires at least **60%** line coverage under `src/` (`pest --coverage --min=60`).
