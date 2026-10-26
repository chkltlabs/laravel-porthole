<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Http\Controllers\Oauth;

use Chkltlabs\Porthole\Oauth\OauthException;
use Chkltlabs\Porthole\Oauth\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class TokenController
{
    public function __construct(private Server $server) {}

    public function __invoke(Request $request): JsonResponse
    {
        if ($request->headers->has('Authorization') && str_starts_with((string) $request->header('Authorization'), 'Basic ')) {
            return $this->error(new OauthException('invalid_client', 'This issuer only accepts public clients.', 401));
        }

        try {
            $tokens = match ($request->string('grant_type')->toString()) {
                'authorization_code' => $this->server->exchangeAuthorizationCode($request->all()),
                'refresh_token' => $this->server->refresh($request->all()),
                default => throw new OauthException('unsupported_grant_type', 'The grant type is not supported.'),
            };
        } catch (OauthException $exception) {
            return $this->error($exception);
        } catch (Throwable $exception) {
            report($exception);

            return $this->error(new OauthException('server_error', 'The token could not be issued.', 500));
        }

        return response()->json($tokens)->header('Cache-Control', 'no-store');
    }

    private function error(OauthException $exception): JsonResponse
    {
        $response = response()->json([
            'error' => $exception->error,
            'error_description' => $exception->getMessage(),
        ], $exception->status)->header('Cache-Control', 'no-store');

        if ($exception->status === 401) {
            $response->header('WWW-Authenticate', 'Bearer realm="porthole"');
        }

        return $response;
    }
}
