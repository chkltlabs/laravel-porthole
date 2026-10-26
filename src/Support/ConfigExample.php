<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Support;

final class ConfigExample
{
    public static function publishIfMissing(string $source, string $target): bool
    {
        if (is_file($target)) {
            return false;
        }

        $directory = dirname($target);

        if (! is_dir($directory)) {
            return false;
        }

        return copy($source, $target);
    }
}
