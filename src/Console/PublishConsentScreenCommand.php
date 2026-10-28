<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Console;

use Chkltlabs\Porthole\Oauth\ConsentScreen;
use Illuminate\Console\Command;
use Throwable;

final class PublishConsentScreenCommand extends Command
{
    protected $signature = 'porthole:consent-screen
        {--force : Replace an existing consent screen}
        {--screen= : Publish one screen: plain, filament, livewire, react, vue, or svelte}
        {--all : Publish every detected consent screen}';

    protected $description = 'Publish the active OAuth consent screen so the application can edit it.';

    public function handle(ConsentScreen $screen): int
    {
        $kits = $screen->installedKits();
        $choice = $this->option('screen');
        $choice = is_string($choice) && $choice !== '' ? $choice : null;

        if ($this->option('all')) {
            $choice = 'all';
        }

        if ($choice === null && count($kits) > 1) {
            $choice = $this->askForScreen($kits);

            if ($choice === null) {
                return self::FAILURE;
            }
        }

        if ($choice === null) {
            $choice = $kits[0] ?? 'plain';
        }

        if ($choice !== 'all' && $choice !== 'plain' && ! in_array($choice, $kits, true)) {
            $this->error('The '.$choice.' consent screen is not installed.');

            return self::FAILURE;
        }

        $selected = $choice === 'all' ? $kits : [$choice];

        if ($selected === []) {
            $selected = ['plain'];
        }

        if (! $this->option('force')) {
            foreach ($selected as $kit) {
                $path = $this->destination($screen, $kit);

                if (is_string($path) && is_file($path)) {
                    $this->line('Consent screen already exists: '.$path);
                    $this->line('Run php artisan porthole:consent-screen --force to replace it.');

                    return self::SUCCESS;
                }
            }
        }

        foreach ($selected as $kit) {
            if (! $this->publishKit($screen, $kit)) {
                return self::FAILURE;
            }
        }

        if (count($selected) === 1 && $selected[0] !== 'plain') {
            $screen->starterKits()->writePreference($selected[0]);
            $this->line('Porthole will use the '.$selected[0].' consent screen.');
        }

        if (count($selected) > 1) {
            $this->line('Set oauth.consent to choose which published screen is shown.');
            $this->line('Until then, Porthole uses the first available screen: '.implode(', ', ConsentScreen::NATIVE).'.');
        }

        $this->line('Keep the POST to the porthole.oauth.approve route, with the CSRF token, request, and decision (approve or deny).');

        if (array_intersect($selected, ['react', 'vue', 'svelte']) !== []) {
            $this->line('Rebuild the frontend so the published page is included.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $kits
     */
    private function askForScreen(array $kits): ?string
    {
        $labels = [];

        foreach ($kits as $kit) {
            $labels[$kit] = $this->label($kit);
        }

        $labels['all'] = 'All';

        if (! $this->input->isInteractive()) {
            $this->error('More than one consent screen is available: '.implode(', ', $kits).'.');
            $this->line('Pass --screen= or --all.');

            return null;
        }

        try {
            $answer = $this->choice('Which consent screen should be published?', array_values($labels));
        } catch (Throwable) {
            $this->error('More than one consent screen is available: '.implode(', ', $kits).'.');
            $this->line('Pass --screen= or --all.');

            return null;
        }

        $selected = array_search($answer, $labels, true);

        return is_string($selected) ? $selected : null;
    }

    private function publishKit(ConsentScreen $screen, string $kit): bool
    {
        $destination = $this->destination($screen, $kit);

        if ($destination === null) {
            $this->error('The '.$kit.' consent screen cannot be published.');

            return false;
        }

        $source = $this->source($screen, $kit);

        if (! $this->publish($source, $destination)) {
            return false;
        }

        $this->line($this->label($kit).' consent screen: '.$destination);

        if ($kit === 'filament') {
            $form = dirname($destination).'/filament-form.blade.php';

            if (! $this->publish($screen->packageViewDirectory().'/filament-form.blade.php', $form)) {
                return false;
            }

            $this->line('Button form: '.$form);
        }

        return true;
    }

    private function destination(ConsentScreen $screen, string $kit): ?string
    {
        return $screen->starterKits()->publishPath($kit);
    }

    private function source(ConsentScreen $screen, string $kit): string
    {
        $views = $screen->packageViewDirectory();
        $kits = $screen->starterKitDirectory();

        return match ($kit) {
            'filament' => $views.'/filament.blade.php',
            'livewire' => $views.'/livewire.blade.php',
            'react' => $kits.'/react/porthole-consent.tsx',
            'vue' => $kits.'/vue/PortholeConsent.vue',
            'svelte' => $kits.'/svelte/PortholeConsent.svelte',
            default => $views.'/consent.blade.php',
        };
    }

    private function publish(string $source, string $destination): bool
    {
        $contents = file_get_contents($source);

        if ($contents === false) {
            $this->error('Could not read '.$source.'.');

            return false;
        }

        $directory = dirname($destination);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error('Could not create '.$directory.'.');

            return false;
        }

        if (file_put_contents($destination, $this->banner($destination).$contents) === false) {
            $this->error('Could not write '.$destination.'.');

            return false;
        }

        return true;
    }

    private function banner(string $destination): string
    {
        $note = 'Published by porthole:consent-screen. Keep the POST to the porthole.oauth.approve route, with the CSRF token, request, and decision set to approve or deny.';

        if (str_ends_with($destination, '.blade.php')) {
            return "{{-- {$note} --}}\n\n";
        }

        if (str_ends_with($destination, '.vue') || str_ends_with($destination, '.svelte')) {
            return "<!-- {$note} -->\n\n";
        }

        return "/* {$note} */\n\n";
    }

    private function label(string $kit): string
    {
        return match ($kit) {
            'filament' => 'Filament',
            'livewire' => 'Livewire',
            'react' => 'React',
            'vue' => 'Vue',
            'svelte' => 'Svelte',
            'plain' => 'Plain',
            'all' => 'All',
            default => $kit,
        };
    }
}
