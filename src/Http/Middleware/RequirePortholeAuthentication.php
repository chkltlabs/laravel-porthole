<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Http\Middleware;

use Chkltlabs\Porthole\Support\AuthFlags;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RequirePortholeAuthentication
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! AuthFlags::httpRequired()) {
            return $next($request);
        }

        $guard = config('porthole.auth.guard');
        $user = $request->user(is_string($guard) && $guard !== '' ? $guard : null);

        if ($user === null) {
            return response()->json([
                'message' => 'Authentication is required.',
            ], 401);
        }

        if (! Gate::forUser($user)->has('viewPorthole') || ! Gate::forUser($user)->check('viewPorthole')) {
            return response()->json([
                'message' => 'This account is not allowed to use Porthole.',
            ], 403);
        }

        return $next($request);
    }
}
