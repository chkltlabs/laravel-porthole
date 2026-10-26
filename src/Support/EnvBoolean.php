<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Support;

final class EnvBoolean
{
    /**
     * Parse a configuration boolean without treating the string "false" as true.
     * Unrecognized values keep $default so a typo cannot turn authentication off
     * or turn writes on.
     */
    public static function parse(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }
}
