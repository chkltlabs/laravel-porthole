<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Oauth;

final class Pkce
{
    public static function matches(string $verifier, string $challenge): bool
    {
        if (strlen($verifier) < 43 || strlen($verifier) > 128) {
            return false;
        }

        $hashed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($challenge, $hashed);
    }
}
