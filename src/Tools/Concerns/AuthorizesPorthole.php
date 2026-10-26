<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Tools\Concerns;

use Chkltlabs\Porthole\Support\AuthFlags;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

trait AuthorizesPorthole
{
    protected function denied(Request $request, string $sourceGate): ?Response
    {
        if (! $this->authenticationRequired()) {
            return null;
        }

        $user = $request->user($this->guard());

        if ($user === null) {
            return Response::error('Authentication is required to read Porthole data.');
        }

        if (! Gate::forUser($user)->has('viewPorthole') || ! Gate::forUser($user)->check('viewPorthole')) {
            return Response::error('You are not authorized to use Porthole.');
        }

        if (! Gate::forUser($user)->has($sourceGate) || ! Gate::forUser($user)->check($sourceGate)) {
            return Response::error('You are not authorized to read this Porthole source.');
        }

        return null;
    }

    protected function mutationDenied(Request $request, string $sourceGate, string $mutateGate): ?Response
    {
        if ($denied = $this->denied($request, $sourceGate)) {
            return $denied;
        }

        if (! $this->authenticationRequired()) {
            return null;
        }

        $user = $request->user($this->guard());

        if ($user === null) {
            return Response::error('Authentication is required to read Porthole data.');
        }

        if (! Gate::forUser($user)->has($mutateGate) || ! Gate::forUser($user)->check($mutateGate)) {
            return Response::error('You are not authorized to change this Porthole source.');
        }

        return null;
    }

    protected function writesDenied(): ?Response
    {
        if (config('porthole.writes_enabled') === true) {
            return null;
        }

        return Response::error('Write tools are disabled.');
    }

    protected function destructiveDenied(): ?Response
    {
        if (config('porthole.destructive_enabled') === true) {
            return null;
        }

        return Response::error('Destructive tools are disabled.');
    }

    private function authenticationRequired(): bool
    {
        if (app()->bound('porthole.transport') && app('porthole.transport') === 'stdio') {
            return AuthFlags::stdioRequired();
        }

        return AuthFlags::httpRequired();
    }

    private function guard(): ?string
    {
        $guard = config('porthole.auth.guard');

        return is_string($guard) && $guard !== '' ? $guard : null;
    }
}
