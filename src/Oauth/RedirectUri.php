<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Oauth;

use Illuminate\Support\Str;

final class RedirectUri
{
    public static function allowed(string $uri): bool
    {
        if ($uri === '' || strlen($uri) > 1024 || preg_match('/[\r\n\x00]/', $uri) === 1) {
            return false;
        }

        $parts = parse_url($uri);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);

        if ($scheme === 'https') {
            return Str::isUrl($uri, ['https']);
        }

        if ($scheme === 'http') {
            return self::isLoopback($parts['host']) && Str::isUrl($uri, ['http']);
        }

        return in_array($scheme, self::customSchemes(), true);
    }

    private static function isLoopback(string $host): bool
    {
        return in_array(strtolower($host), ['localhost', '127.0.0.1', '[::1]'], true);
    }

    /**
     * @return list<string>
     */
    private static function customSchemes(): array
    {
        $configured = array_merge(
            (array) config('porthole.oauth.redirect_schemes', []),
            (array) config('mcp.custom_schemes', []),
        );

        $schemes = [];

        foreach ($configured as $scheme) {
            if (! is_string($scheme)) {
                continue;
            }

            $scheme = strtolower(trim($scheme));

            if ($scheme !== '' && $scheme !== 'http' && $scheme !== 'https' && preg_match('/^[a-z][a-z0-9+.-]*$/', $scheme) === 1) {
                $schemes[] = $scheme;
            }
        }

        return $schemes;
    }
}
