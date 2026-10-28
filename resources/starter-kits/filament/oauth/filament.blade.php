@php
    filament()->setCurrentPanel($panelId);
    filament()->bootCurrentPanel();
@endphp

<x-filament-panels::layout.simple :hasTopbar="false">
    @livewire(\Chkltlabs\Porthole\Oauth\Filament\ConsentPage::class, [
        'clientName' => $clientName,
        'redirectHost' => $redirectHost,
        'requestId' => $requestId,
    ])
</x-filament-panels::layout.simple>
