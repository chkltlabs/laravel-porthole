<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Oauth;

use Chkltlabs\Porthole\Http\Controllers\Oauth\AuthorizeController;
use Chkltlabs\Porthole\Http\Controllers\Oauth\DiscoveryController;
use Chkltlabs\Porthole\Http\Controllers\Oauth\RegisterController;
use Chkltlabs\Porthole\Http\Controllers\Oauth\TokenController;
use Chkltlabs\Porthole\Support\EnvBoolean;
use Illuminate\Support\Facades\Route;

final class OauthIssuer
{
    public function registerRoutes(): void
    {
        $prefix = Discovery::prefix();

        Route::get('.well-known/oauth-protected-resource/{path}', [DiscoveryController::class, 'protectedResource'])
            ->where('path', '.*')
            ->name('mcp.oauth.protected-resource.nested');

        Route::get('.well-known/oauth-authorization-server/'.$prefix, [DiscoveryController::class, 'authorizationServer'])
            ->name('porthole.oauth.authorization-server');

        Route::middleware(['web', 'throttle:30,1'])->group(function () use ($prefix): void {
            Route::get($prefix.'/authorize', [AuthorizeController::class, 'show'])->name('porthole.oauth.authorize');
            Route::post($prefix.'/authorize', [AuthorizeController::class, 'approve'])->name('porthole.oauth.approve');
        });

        Route::post($prefix.'/token', TokenController::class)
            ->middleware('throttle:30,1')
            ->name('porthole.oauth.token');

        if (EnvBoolean::parse(config('porthole.oauth.dynamic_registration'), false)) {
            Route::post($prefix.'/register', RegisterController::class)
                ->middleware('throttle:5,1')
                ->name('porthole.oauth.register');
        }
    }
}
