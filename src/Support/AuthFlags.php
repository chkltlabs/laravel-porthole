<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Support;

final class AuthFlags
{
    /**
     * A transport-specific env value wins. When it is absent, the legacy
     * PORTHOLE_AUTH_REQUIRED value applies to that transport. Unset both
     * keeps authentication required.
     */
    public static function resolve(mixed $specific, mixed $legacy): bool
    {
        $value = self::isPresent($specific) ? $specific : $legacy;

        return EnvBoolean::parse($value, true);
    }

    public static function httpRequired(): bool
    {
        return self::stored('http_required');
    }

    public static function stdioRequired(): bool
    {
        return self::stored('stdio_required');
    }

    private static function stored(string $key): bool
    {
        $value = config('porthole.auth.'.$key);

        if ($value === null) {
            return config('porthole.auth.required', true) === true;
        }

        return $value === true;
    }

    private static function isPresent(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }
}
