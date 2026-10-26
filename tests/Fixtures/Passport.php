<?php

declare(strict_types=1);

namespace Laravel\Passport;

class Passport
{
    /**
     * @var array<string, string>
     */
    public static array $scopes = [];

    /**
     * @param  array<string, string>  $scopes
     */
    public static function tokensCan(array $scopes): void
    {
        self::$scopes = $scopes;
    }
}
