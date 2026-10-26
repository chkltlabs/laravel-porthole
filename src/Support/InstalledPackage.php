<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Support;

final class InstalledPackage
{
    /**
     * True when a class or interface from an optional package can be loaded.
     * class_exists() is false for interfaces, which is how Horizon, Pulse, and
     * Telescope expose their storage contracts.
     */
    public static function exists(string $class): bool
    {
        return class_exists($class) || interface_exists($class);
    }
}
