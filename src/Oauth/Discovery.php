<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Oauth;

final class Discovery
{
    public static function prefix(): string
    {
        return trim((string) config('porthole.oauth.prefix', 'porthole/oauth'), '/');
    }

    public static function issuer(): string
    {
        return url('/'.self::prefix());
    }

    /**
     * @return array<string, mixed>
     */
    public static function protectedResource(string $path): array
    {
        $path = trim($path, '/');

        if ($path === '') {
            $path = trim((string) config('porthole.path', 'mcp/porthole'), '/');
        }

        return [
            'resource' => url('/'.$path),
            'authorization_servers' => [self::issuer()],
            'scopes_supported' => [Server::SCOPE],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function authorizationServer(): array
    {
        $metadata = [
            'issuer' => self::issuer(),
            'authorization_endpoint' => route('porthole.oauth.authorize'),
            'token_endpoint' => route('porthole.oauth.token'),
            'response_types_supported' => ['code'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => [Server::SCOPE],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none'],
        ];

        if (app('router')->has('porthole.oauth.register')) {
            $metadata['registration_endpoint'] = route('porthole.oauth.register');
        }

        return $metadata;
    }
}
