<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Http\Middleware;

use Chkltlabs\Porthole\Oauth\ResourceOwner;
use Chkltlabs\Porthole\Oauth\Server;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticatePortholeAccessToken
{
    public function __construct(
        private Server $server,
        private ResourceOwner $owners,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');
        $token = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : '';
        $userId = $token === '' ? null : $this->server->userIdForAccessToken($token);
        $user = $userId === null ? null : $this->owners->find($userId);

        if ($user === null) {
            $response = response()->json([
                'message' => 'Authentication is required.',
            ], 401);

            if (app('router')->has('mcp.oauth.protected-resource.nested')) {
                $response->header(
                    'WWW-Authenticate',
                    'Bearer realm="mcp", resource_metadata="'.route('mcp.oauth.protected-resource.nested', ['path' => $request->path()]).'"',
                );
            }

            return $response;
        }

        $guard = config('porthole.auth.guard');
        $guard = is_string($guard) && $guard !== '' ? $guard : null;

        Auth::guard($guard)->setUser($user);
        $request->setUserResolver(fn (mixed $guard = null): mixed => $user);

        return $next($request);
    }
}
