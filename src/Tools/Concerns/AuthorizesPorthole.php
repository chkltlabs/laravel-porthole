<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Tools\Concerns;

use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

trait AuthorizesPorthole
{
    protected function denied(Request $request, string $sourceGate): ?Response
    {
        if (config('porthole.auth.required', true) !== true) {
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

    private function guard(): ?string
    {
        $guard = config('porthole.auth.guard');

        return is_string($guard) && $guard !== '' ? $guard : null;
    }
}
