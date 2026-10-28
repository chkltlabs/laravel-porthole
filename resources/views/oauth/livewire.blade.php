<x-layouts::auth :title="__('Authorize :client', ['client' => $clientName])">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Authorize :client', ['client' => $clientName])"
            :description="__('This client is asking for the mcp:use scope on the Porthole issuer. The token it receives is not an application API token.')"
        />

        <form method="POST" action="{{ route('porthole.oauth.approve') }}" class="flex flex-col gap-6">
            @csrf
            <input type="hidden" name="request" value="{{ $requestId }}">

            <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Redirect host:') }} <code>{{ $redirectHost }}</code>
            </p>

            <div class="flex flex-col gap-3">
                <flux:button variant="primary" type="submit" name="decision" value="approve" class="w-full" data-test="consent-approve">
                    {{ __('Approve') }}
                </flux:button>
                <flux:button variant="ghost" type="submit" name="decision" value="deny" class="w-full" data-test="consent-deny">
                    {{ __('Deny') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth>
