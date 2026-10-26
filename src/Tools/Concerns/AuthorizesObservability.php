<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Tools\Concerns;

use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

trait AuthorizesObservability
{
    protected function denied(Request $request, string $sourceGate): ?Response
    {
        if (config('observability-mcp.auth.required', true) !== true) {
            return null;
        }

        $user = $request->user($this->guard());

        if ($user === null) {
            return Response::error('Authentication is required to read observability data.');
        }

        if (! Gate::forUser($user)->has('viewObservabilityMcp') || ! Gate::forUser($user)->check('viewObservabilityMcp')) {
            return Response::error('You are not authorized to use the observability MCP server.');
        }

        if (! Gate::forUser($user)->has($sourceGate) || ! Gate::forUser($user)->check($sourceGate)) {
            return Response::error('You are not authorized to read this observability source.');
        }

        return null;
    }

    protected function writesDenied(): ?Response
    {
        if (config('observability-mcp.writes_enabled') === true) {
            return null;
        }

        return Response::error('Write tools are disabled.');
    }

    protected function destructiveDenied(): ?Response
    {
        if (config('observability-mcp.destructive_enabled') === true) {
            return null;
        }

        return Response::error('Destructive tools are disabled.');
    }

    private function guard(): ?string
    {
        $guard = config('observability-mcp.auth.guard');

        return is_string($guard) && $guard !== '' ? $guard : null;
    }
}
