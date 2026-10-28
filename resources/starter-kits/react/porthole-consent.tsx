import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

type Props = {
    clientName: string;
    redirectHost: string;
    requestId: string;
    approveUrl: string;
    csrf: string;
};

export default function PortholeConsent({
    clientName,
    redirectHost,
    requestId,
    approveUrl,
    csrf,
}: Props) {
    return (
        <>
            <Head title={`Authorize ${clientName}`} />

            <form method="POST" action={approveUrl} className="flex flex-col gap-6">
                <input type="hidden" name="_token" value={csrf} />
                <input type="hidden" name="request" value={requestId} />

                <p className="text-center text-sm text-muted-foreground">
                    This client is asking for the <code>mcp:use</code> scope on
                    the Porthole issuer. The token it receives is not an
                    application API token.
                </p>
                <p className="text-center text-sm text-muted-foreground">
                    Redirect host: <code>{redirectHost}</code>
                </p>

                <div className="flex flex-col gap-3">
                    <Button
                        type="submit"
                        name="decision"
                        value="approve"
                        className="w-full"
                        data-test="consent-approve"
                    >
                        Approve
                    </Button>
                    <Button
                        type="submit"
                        name="decision"
                        value="deny"
                        variant="outline"
                        className="w-full"
                        data-test="consent-deny"
                    >
                        Deny
                    </Button>
                </div>
            </form>
        </>
    );
}

PortholeConsent.layout = {
    title: 'Authorize Porthole',
    description:
        'This client is asking for the mcp:use scope on the Porthole issuer.',
};
