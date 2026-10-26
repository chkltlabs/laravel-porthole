<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RequireObservabilityAuthentication
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('observability-mcp.auth.required', true) !== true) {
            return $next($request);
        }

        $guard = config('observability-mcp.auth.guard');
        $user = $request->user(is_string($guard) && $guard !== '' ? $guard : null);

        if ($user === null) {
            return response()->json([
                'message' => 'Authentication is required.',
            ], 401);
        }

        if (! Gate::forUser($user)->has('viewObservabilityMcp') || ! Gate::forUser($user)->check('viewObservabilityMcp')) {
            return response()->json([
                'message' => 'This account is not allowed to use the observability MCP server.',
            ], 403);
        }

        return $next($request);
    }
}
