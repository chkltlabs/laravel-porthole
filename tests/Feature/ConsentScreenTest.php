<?php

declare(strict_types=1);

use Chkltlabs\Porthole\Oauth\ConsentScreen;
use Chkltlabs\Porthole\Oauth\FilamentHost;
use Chkltlabs\Porthole\Oauth\InstalledFilamentHost;
use Chkltlabs\Porthole\Oauth\InstalledStarterKits;

afterEach(function () {
    foreach ([
        resource_path('views/vendor/porthole/oauth/consent.blade.php'),
        resource_path('views/vendor/porthole/oauth/filament.blade.php'),
        resource_path('views/vendor/porthole/oauth/filament-form.blade.php'),
        resource_path('views/vendor/porthole/oauth/livewire.blade.php'),
        resource_path('views/vendor/porthole/oauth/screen'),
    ] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('accepts filament 4 and 5 and leaves filament 3 on the plain page', function () {
    expect(InstalledFilamentHost::supported('4.0.0'))->toBeTrue()
        ->and(InstalledFilamentHost::supported('v4.2.1'))->toBeTrue()
        ->and(InstalledFilamentHost::supported('5.8.4'))->toBeTrue()
        ->and(InstalledFilamentHost::supported('v5.0.0'))->toBeTrue()
        ->and(InstalledFilamentHost::supported('3.3.0'))->toBeFalse()
        ->and(InstalledFilamentHost::supported('v3.2.0'))->toBeFalse()
        ->and(InstalledFilamentHost::supported('6.0.0'))->toBeFalse()
        ->and(InstalledFilamentHost::supported(null))->toBeFalse();
});

it('uses the plain consent view when filament is unavailable or turned off', function () {
    $screen = new ConsentScreen(filamentHost(available: true, panels: ['app' => true], default: 'app'));

    expect($screen->resolve('Cursor', 'localhost', 'request-id')['view'])->toBe(ConsentScreen::FILAMENT);

    config(['porthole.oauth.filament' => false]);

    expect($screen->resolve('Cursor', 'localhost', 'request-id')['view'])->toBe(ConsentScreen::PLAIN)
        ->and((new ConsentScreen(filamentHost()))->resolve('Cursor', 'localhost', 'request-id')['view'])->toBe(ConsentScreen::PLAIN);
});

it('uses the filament view and panel when the host has a default panel', function () {
    $resolved = (new ConsentScreen(filamentHost(available: true, panels: ['app' => true], default: 'app')))
        ->resolve('Cursor', 'localhost', 'request-id');

    expect($resolved['view'])->toBe(ConsentScreen::FILAMENT)
        ->and($resolved['data']['panelId'])->toBe('app');
});

it('reads a panel id from a filament login route and ignores a plain login route', function () {
    $screen = new ConsentScreen(filamentHost(available: true, panels: ['app' => true, 'admin' => true], default: 'admin'));

    config(['porthole.oauth.login' => 'filament.app.auth.login']);

    expect($screen->panelFromLoginRoute())->toBe('app')
        ->and($screen->panelId())->toBe('app');

    config(['porthole.oauth.login' => 'login']);

    expect($screen->panelFromLoginRoute())->toBeNull()
        ->and($screen->panelId())->toBe('admin');
});

it('lets a configured panel win and falls back when that panel is missing', function () {
    $screen = new ConsentScreen(filamentHost(available: true, panels: ['admin' => true, 'app' => true], default: 'app'));

    config([
        'porthole.oauth.filament_panel' => 'admin',
        'porthole.oauth.login' => 'filament.app.auth.login',
    ]);

    expect($screen->panelId())->toBe('admin')
        ->and($screen->resolve('Cursor', 'localhost', 'request-id')['view'])->toBe(ConsentScreen::FILAMENT);

    config(['porthole.oauth.filament_panel' => 'missing']);

    expect($screen->panelId())->toBeNull()
        ->and($screen->resolve('Cursor', 'localhost', 'request-id')['view'])->toBe(ConsentScreen::PLAIN);
});

it('uses a published consent view for either built-in screen', function () {
    $screen = new ConsentScreen(filamentHost(available: true, panels: ['app' => true], default: 'app'));
    $directory = dirname($screen->overridePath());

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($screen->overridePath(), '<p>custom</p>');

    $resolved = $screen->resolve('Cursor', 'localhost', 'request-id');

    expect($resolved['view'])->toBe(ConsentScreen::PLAIN)
        ->and($resolved['data']['panelId'])->toBe('app');
});

it('keeps the filament consent form on the porthole approve route', function () {
    $root = dirname(__DIR__, 2);
    $form = file_get_contents($root.'/resources/views/oauth/filament-form.blade.php');
    $shell = file_get_contents($root.'/resources/views/oauth/filament.blade.php');
    $page = file_get_contents($root.'/src/Oauth/Filament/ConsentPage.php');
    $controller = file_get_contents($root.'/src/Http/Controllers/Oauth/AuthorizeController.php');

    expect($form)->toContain("route('porthole.oauth.approve')")
        ->and($form)->toContain('name="request"')
        ->and($form)->toContain('decisionActions()')
        ->and($shell)->toContain('Chkltlabs\\Porthole\\Oauth\\Filament\\ConsentPage')
        ->and($page)->toContain("'approve'")
        ->and($page)->toContain("'deny'")
        ->and($page)->toContain('livewireClickHandlerEnabled(false)')
        ->and($controller)->not->toContain('Filament\\')
        ->and(file_get_contents($root.'/resources/views/oauth/livewire.blade.php'))->toContain('<x-layouts::auth')
        ->and(file_get_contents($root.'/resources/views/oauth/livewire.blade.php'))->toContain('flux:button')
        ->and(file_get_contents($root.'/resources/starter-kits/react/porthole-consent.tsx'))->toContain('name="decision"')
        ->and(file_get_contents($root.'/resources/starter-kits/react/porthole-consent.tsx'))->toContain('PortholeConsent.layout')
        ->and(file_get_contents($root.'/resources/starter-kits/vue/PortholeConsent.vue'))->toContain('variant="outline"')
        ->and(file_get_contents($root.'/resources/starter-kits/svelte/PortholeConsent.svelte'))->toContain('AppHead');
});

it('publishes the plain screen and leaves an existing override in place', function () {
    app()->instance(FilamentHost::class, filamentHost());

    $this->artisan('porthole:consent-screen')->assertSuccessful();

    $path = resource_path('views/vendor/porthole/oauth/consent.blade.php');
    $published = file_get_contents($path);

    expect($published)->toContain('porthole:consent-screen')
        ->and($published)->toContain('Authorize')
        ->and(is_file(resource_path('views/vendor/porthole/oauth/filament-form.blade.php')))->toBeFalse();

    $this->artisan('porthole:consent-screen')->assertSuccessful();

    expect(file_get_contents($path))->toBe($published);
});

it('publishes the filament screen when a panel is available', function () {
    app()->instance(FilamentHost::class, filamentHost(available: true, panels: ['app' => true], default: 'app'));

    $this->artisan('porthole:consent-screen')->assertSuccessful();

    $consent = file_get_contents(resource_path('views/vendor/porthole/oauth/filament.blade.php'));
    $form = file_get_contents(resource_path('views/vendor/porthole/oauth/filament-form.blade.php'));
    $preference = trim((string) file_get_contents(resource_path('views/vendor/porthole/oauth/screen')));

    expect($consent)->toContain('x-filament-panels::layout.simple')
        ->and($form)->toContain("route('porthole.oauth.approve')")
        ->and($form)->toContain('decision')
        ->and($preference)->toBe('filament')
        ->and(is_file(resource_path('views/vendor/porthole/oauth/consent.blade.php')))->toBeFalse();
});

it('prefers filament until a published starter kit is selected', function () {
    $root = sys_get_temp_dir().'/porthole-kits-'.uniqid();
    starterKitFiles($root, ['livewire', 'react']);
    $kits = new InstalledStarterKits($root, fluxInstalled: true, inertiaInstalled: true);
    $screen = new ConsentScreen(filamentHost(available: true, panels: ['app' => true], default: 'app'), $kits);

    expect($screen->resolve('Cursor', 'localhost', 'request-id')['view'])->toBe(ConsentScreen::FILAMENT);

    $kits->writePreference('livewire');

    expect($screen->resolve('Cursor', 'localhost', 'request-id')['view'])->toBe(ConsentScreen::LIVEWIRE);

    file_put_contents($kits->publishPath('react'), '<page />');

    config(['porthole.oauth.consent' => 'react']);

    expect($screen->resolve('Cursor', 'localhost', 'request-id')['inertia'] ?? null)->toBe('auth/porthole-consent');
});

it('uses a livewire starter kit when it is the only native screen', function () {
    $root = sys_get_temp_dir().'/porthole-livewire-'.uniqid();
    starterKitFiles($root, ['livewire']);
    $screen = new ConsentScreen(
        filamentHost(),
        new InstalledStarterKits($root, fluxInstalled: true, inertiaInstalled: false),
    );

    expect($screen->installedKits())->toBe(['livewire'])
        ->and($screen->resolve('Cursor', 'localhost', 'request-id')['view'])->toBe(ConsentScreen::LIVEWIRE);
});

it('asks for a screen when several starter kits are installed and the terminal is not interactive', function () {
    $root = sys_get_temp_dir().'/porthole-many-'.uniqid();
    starterKitFiles($root, ['react', 'vue']);
    app()->instance(InstalledStarterKits::class, new InstalledStarterKits($root, fluxInstalled: false, inertiaInstalled: true));

    $this->artisan('porthole:consent-screen')
        ->expectsOutputToContain('More than one consent screen is available: react, vue.')
        ->assertFailed();
});

it('publishes the chosen starter kit and can publish all of them', function () {
    $root = sys_get_temp_dir().'/porthole-publish-'.uniqid();
    starterKitFiles($root, ['react', 'svelte']);
    app()->instance(InstalledStarterKits::class, new InstalledStarterKits($root, fluxInstalled: false, inertiaInstalled: true));

    $this->artisan('porthole:consent-screen', ['--screen' => 'react'])->assertSuccessful();

    expect(is_file($root.'/resources/js/pages/auth/porthole-consent.tsx'))->toBeTrue()
        ->and(trim((string) file_get_contents($root.'/resources/views/vendor/porthole/oauth/screen')))->toBe('react')
        ->and(file_get_contents($root.'/resources/js/pages/auth/porthole-consent.tsx'))->toContain('approveUrl');

    $this->artisan('porthole:consent-screen', ['--all' => true, '--force' => true])->assertSuccessful();

    expect(is_file($root.'/resources/js/pages/auth/PortholeConsent.svelte'))->toBeTrue()
        ->and(file_get_contents($root.'/resources/js/pages/auth/PortholeConsent.svelte'))->toContain('decision');
});

function starterKitFiles(string $root, array $kits): void
{
    $files = [];

    if (in_array('livewire', $kits, true)) {
        $files[] = $root.'/resources/views/layouts/auth.blade.php';
        $files[] = $root.'/resources/views/pages/auth/login.blade.php';
        $files[] = $root.'/resources/views/components/auth-header.blade.php';
    }

    if (in_array('react', $kits, true)) {
        $files[] = $root.'/resources/js/pages/auth/login.tsx';
        $files[] = $root.'/resources/js/layouts/auth-layout.tsx';
    }

    if (in_array('vue', $kits, true)) {
        $files[] = $root.'/resources/js/pages/auth/Login.vue';
        $files[] = $root.'/resources/js/layouts/AuthLayout.vue';
    }

    if (in_array('svelte', $kits, true)) {
        $files[] = $root.'/resources/js/pages/auth/Login.svelte';
        $files[] = $root.'/resources/js/layouts/AuthLayout.svelte';
    }

    foreach ($files as $file) {
        $directory = dirname($file);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($file, 'kit');
    }
}

function filamentHost(bool $available = false, array $panels = [], ?string $default = null): FilamentHost
{
    return new class($available, $panels, $default) implements FilamentHost
    {
        /**
         * @param  array<string, bool>  $panels
         */
        public function __construct(
            private bool $available,
            private array $panels,
            private ?string $default,
        ) {}

        public function available(): bool
        {
            return $this->available;
        }

        public function hasPanel(string $id): bool
        {
            return ($this->panels[$id] ?? false) === true;
        }

        public function defaultPanelId(): ?string
        {
            return $this->default;
        }
    };
}
