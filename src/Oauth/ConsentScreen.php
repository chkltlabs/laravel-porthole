<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Oauth;

final class ConsentScreen
{
    public const PLAIN = 'porthole::oauth.consent';

    public const FILAMENT = 'porthole::oauth.filament';

    public const LIVEWIRE = 'porthole::oauth.livewire';

    /**
     * @var list<string>
     */
    public const NATIVE = ['filament', 'livewire', 'react', 'vue', 'svelte'];

    public function __construct(
        private FilamentHost $filament,
        private ?InstalledStarterKits $starterKits = null,
    ) {
        $this->starterKits ??= new InstalledStarterKits(sys_get_temp_dir().'/porthole-starter-kits-'.bin2hex(random_bytes(4)));
    }

    /**
     * @return array{view: string, data: array<string, string>, inertia?: string}
     */
    public function resolve(string $clientName, string $redirectHost, string $requestId): array
    {
        $data = [
            'clientName' => $clientName,
            'redirectHost' => $redirectHost,
            'requestId' => $requestId,
        ];

        $panelId = $this->panelId();

        if (is_string($panelId)) {
            $data['panelId'] = $panelId;
        }

        if ($this->overridePublished()) {
            return [
                'view' => self::PLAIN,
                'data' => $data,
            ];
        }

        $screen = $this->selected();

        if ($screen === 'filament') {
            return [
                'view' => self::FILAMENT,
                'data' => $data,
            ];
        }

        if ($screen === 'livewire') {
            return [
                'view' => self::LIVEWIRE,
                'data' => $data,
            ];
        }

        $component = is_string($screen) ? $this->starterKits->inertiaComponent($screen) : null;

        if (is_string($component)) {
            return [
                'view' => self::PLAIN,
                'inertia' => $component,
                'data' => $data,
            ];
        }

        return [
            'view' => self::PLAIN,
            'data' => $data,
        ];
    }

    /**
     * Kits that can supply a consent screen, including Filament.
     *
     * @return list<string>
     */
    public function installedKits(): array
    {
        $kits = [];

        if ($this->panelId() !== null) {
            $kits[] = 'filament';
        }

        foreach ($this->starterKits->installed() as $kit) {
            $kits[] = $kit;
        }

        return $kits;
    }

    public function panelId(): ?string
    {
        if (config('porthole.oauth.filament') === false || ! $this->filament->available()) {
            return null;
        }

        $configured = config('porthole.oauth.filament_panel');

        if (is_string($configured) && $configured !== '') {
            return $this->filament->hasPanel($configured) ? $configured : null;
        }

        $fromLogin = $this->panelFromLoginRoute();

        if (is_string($fromLogin) && $this->filament->hasPanel($fromLogin)) {
            return $fromLogin;
        }

        $default = $this->filament->defaultPanelId();

        if (is_string($default) && $default !== '' && $this->filament->hasPanel($default)) {
            return $default;
        }

        return null;
    }

    public function panelFromLoginRoute(): ?string
    {
        $login = config('porthole.oauth.login', 'login');

        if (! is_string($login)) {
            return null;
        }

        if (preg_match('/^filament\.([^.]+)\.auth\.login$/', $login, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    public function overridePath(): string
    {
        return $this->starterKits->publishPath('plain') ?? '';
    }

    public function overridePublished(): bool
    {
        return is_file($this->overridePath());
    }

    public function packageViewDirectory(): string
    {
        return dirname(__DIR__, 2).'/resources/views/oauth';
    }

    public function starterKitDirectory(): string
    {
        return dirname(__DIR__, 2).'/resources/starter-kits';
    }

    public function starterKits(): InstalledStarterKits
    {
        return $this->starterKits;
    }

    private function selected(): ?string
    {
        $configured = config('porthole.oauth.consent');

        if ($configured === 'plain') {
            return null;
        }

        if (is_string($configured) && $configured !== '' && $this->renderable($configured)) {
            return $configured;
        }

        $preference = $this->starterKits->preference();

        if (is_string($preference) && $this->renderable($preference)) {
            return $preference;
        }

        $renderable = array_values(array_filter(
            self::NATIVE,
            fn (string $kit): bool => $this->renderable($kit),
        ));

        if (count($renderable) === 1) {
            return $renderable[0];
        }

        return $renderable[0] ?? null;
    }

    private function renderable(string $kit): bool
    {
        if ($kit === 'filament') {
            return $this->panelId() !== null;
        }

        if ($kit === 'livewire') {
            return $this->starterKits->livewireInstalled();
        }

        if (! in_array($kit, ['react', 'vue', 'svelte'], true)) {
            return false;
        }

        $installed = match ($kit) {
            'react' => $this->starterKits->reactInstalled(),
            'vue' => $this->starterKits->vueInstalled(),
            'svelte' => $this->starterKits->svelteInstalled(),
        };

        return $installed
            && $this->starterKits->pagePublished($kit)
            && $this->starterKits->inertiaAvailable();
    }
}
