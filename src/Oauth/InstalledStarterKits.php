<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Oauth;

use Composer\InstalledVersions;
use Flux\Flux;
use Inertia\Inertia;

final class InstalledStarterKits
{
    public function __construct(
        private string $root,
        private ?bool $fluxInstalled = null,
        private ?bool $inertiaInstalled = null,
    ) {}

    public static function application(): self
    {
        return new self(base_path());
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * Official Laravel starter kits present in the application.
     *
     * @return list<'livewire'|'react'|'vue'|'svelte'>
     */
    public function installed(): array
    {
        $kits = [];

        if ($this->livewireInstalled()) {
            $kits[] = 'livewire';
        }

        if ($this->reactInstalled()) {
            $kits[] = 'react';
        }

        if ($this->vueInstalled()) {
            $kits[] = 'vue';
        }

        if ($this->svelteInstalled()) {
            $kits[] = 'svelte';
        }

        return $kits;
    }

    public function livewireInstalled(): bool
    {
        return $this->fluxAvailable()
            && is_file($this->root.'/resources/views/layouts/auth.blade.php')
            && is_file($this->root.'/resources/views/pages/auth/login.blade.php')
            && is_file($this->root.'/resources/views/components/auth-header.blade.php');
    }

    public function reactInstalled(): bool
    {
        return is_file($this->root.'/resources/js/pages/auth/login.tsx')
            && is_file($this->root.'/resources/js/layouts/auth-layout.tsx');
    }

    public function vueInstalled(): bool
    {
        return is_file($this->root.'/resources/js/pages/auth/Login.vue')
            && is_file($this->root.'/resources/js/layouts/AuthLayout.vue');
    }

    public function svelteInstalled(): bool
    {
        return is_file($this->root.'/resources/js/pages/auth/Login.svelte')
            && is_file($this->root.'/resources/js/layouts/AuthLayout.svelte');
    }

    public function inertiaAvailable(): bool
    {
        if ($this->inertiaInstalled !== null) {
            return $this->inertiaInstalled;
        }

        return class_exists(Inertia::class) || $this->packageInstalled('inertiajs/inertia-laravel');
    }

    public function pagePublished(string $kit): bool
    {
        $path = $this->publishPath($kit);

        return is_string($path) && is_file($path);
    }

    public function inertiaComponent(string $kit): ?string
    {
        return match ($kit) {
            'react' => 'auth/porthole-consent',
            'vue', 'svelte' => 'auth/PortholeConsent',
            default => null,
        };
    }

    public function publishPath(string $kit): ?string
    {
        return match ($kit) {
            'livewire' => $this->root.'/resources/views/vendor/porthole/oauth/livewire.blade.php',
            'filament' => $this->root.'/resources/views/vendor/porthole/oauth/filament.blade.php',
            'react' => $this->root.'/resources/js/pages/auth/porthole-consent.tsx',
            'vue' => $this->root.'/resources/js/pages/auth/PortholeConsent.vue',
            'svelte' => $this->root.'/resources/js/pages/auth/PortholeConsent.svelte',
            'plain' => $this->root.'/resources/views/vendor/porthole/oauth/consent.blade.php',
            default => null,
        };
    }

    public function preferencePath(): string
    {
        return $this->root.'/resources/views/vendor/porthole/oauth/screen';
    }

    public function preference(): ?string
    {
        if (! is_file($this->preferencePath())) {
            return null;
        }

        $preference = trim((string) file_get_contents($this->preferencePath()));

        return $preference === '' ? null : $preference;
    }

    public function writePreference(string $kit): void
    {
        $directory = dirname($this->preferencePath());

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($this->preferencePath(), $kit.PHP_EOL);
    }

    private function fluxAvailable(): bool
    {
        if ($this->fluxInstalled !== null) {
            return $this->fluxInstalled;
        }

        return class_exists(Flux::class) || $this->packageInstalled('livewire/flux');
    }

    private function packageInstalled(string $package): bool
    {
        return class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($package);
    }
}
