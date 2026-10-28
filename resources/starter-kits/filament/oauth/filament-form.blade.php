<x-filament-panels::page.simple>
    <form id="{{ $this::FORM_ID }}" method="POST" action="{{ route('porthole.oauth.approve') }}">
        @csrf
        <input type="hidden" name="request" value="{{ $requestId }}">
        <div class="fi-sc-actions">
            <div class="fi-ac fi-width-full">
                @foreach ($this->decisionActions() as $action)
                    {!! $action->toHtml() !!}
                @endforeach
            </div>
        </div>
    </form>
</x-filament-panels::page.simple>
