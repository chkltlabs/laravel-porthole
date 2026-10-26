<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Http\Controllers\Oauth;

use Chkltlabs\Porthole\Oauth\Discovery;
use Illuminate\Http\JsonResponse;

final class DiscoveryController
{
    public function protectedResource(string $path = ''): JsonResponse
    {
        return response()->json(Discovery::protectedResource($path));
    }

    public function authorizationServer(): JsonResponse
    {
        return response()->json(Discovery::authorizationServer());
    }
}
