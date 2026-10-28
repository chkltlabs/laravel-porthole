<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Oauth;

use Composer\InstalledVersions;
use Filament\Facades\Filament;
use Throwable;

final class InstalledFilamentHost implements FilamentHost
{
    /**
     * Filament 4 and 5 read a simple page's view from an instance property.
     * Filament 3 reads a static property of the same name, so one page class
     * cannot extend both. Apps on Filament 3 keep the plain consent screen.
     */
    public function available(): bool
    {
        if (! class_exists(Filament::class) || ! function_exists('filament')) {
            return false;
        }

        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled('filament/filament')) {
            return false;
        }

        return self::supported(
            InstalledVersions::getPrettyVersion('filament/filament')
                ?? InstalledVersions::getVersion('filament/filament'),
        );
    }

    public static function supported(?string $version): bool
    {
        if (! is_string($version) || $version === '') {
            return false;
        }

        return preg_match('/^(4|5)\./', ltrim($version, 'vV')) === 1;
    }

    public function hasPanel(string $id): bool
    {
        if (! function_exists('filament')) {
            return false;
        }

        try {
            $panels = filament()->getPanels();
        } catch (Throwable) {
            return false;
        }

        return is_array($panels) && array_key_exists($id, $panels);
    }

    public function defaultPanelId(): ?string
    {
        if (! function_exists('filament')) {
            return null;
        }

        try {
            $id = filament()->getDefaultPanel()->getId();
        } catch (Throwable) {
            return null;
        }

        return is_string($id) && $id !== '' ? $id : null;
    }
}
