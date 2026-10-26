<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Oauth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

final class ResourceOwner
{
    public function find(string $id): ?Authenticatable
    {
        $name = config('porthole.oauth.user_provider');

        if (! is_string($name) || $name === '') {
            $guard = config('porthole.oauth.web_guard', 'web');
            $guard = is_string($guard) && $guard !== '' ? $guard : 'web';
            $name = config('auth.guards.'.$guard.'.provider', 'users');
        }

        if (! is_string($name) || $name === '') {
            return null;
        }

        $user = Auth::createUserProvider($name)?->retrieveById($id);

        return $user instanceof Authenticatable ? $user : null;
    }

    public function webGuard(): string
    {
        $guard = config('porthole.oauth.web_guard', 'web');

        return is_string($guard) && $guard !== '' ? $guard : 'web';
    }

    public function loginUrl(): string
    {
        $login = config('porthole.oauth.login', 'login');
        $login = is_string($login) && $login !== '' ? $login : 'login';

        if (app('router')->has($login)) {
            return route($login);
        }

        return url(str_starts_with($login, '/') ? $login : '/'.$login);
    }
}
