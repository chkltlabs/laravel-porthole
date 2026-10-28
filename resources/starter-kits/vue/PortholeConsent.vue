<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        title: 'Authorize Porthole',
        description:
            'This client is asking for the mcp:use scope on the Porthole issuer.',
    },
});

defineProps<{
    clientName: string;
    redirectHost: string;
    requestId: string;
    approveUrl: string;
    csrf: string;
}>();
</script>

<template>
    <Head :title="`Authorize ${clientName}`" />

    <form method="POST" :action="approveUrl" class="flex flex-col gap-6">
        <input type="hidden" name="_token" :value="csrf" />
        <input type="hidden" name="request" :value="requestId" />

        <p class="text-center text-sm text-muted-foreground">
            This client is asking for the <code>mcp:use</code> scope on the
            Porthole issuer. The token it receives is not an application API
            token.
        </p>
        <p class="text-center text-sm text-muted-foreground">
            Redirect host: <code>{{ redirectHost }}</code>
        </p>

        <div class="flex flex-col gap-3">
            <Button
                type="submit"
                name="decision"
                value="approve"
                class="w-full"
                data-test="consent-approve"
            >
                Approve
            </Button>
            <Button
                type="submit"
                name="decision"
                value="deny"
                variant="outline"
                class="w-full"
                data-test="consent-deny"
            >
                Deny
            </Button>
        </div>
    </form>
</template>
