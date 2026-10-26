<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Console;

use Chkltlabs\Porthole\Oauth\OauthException;
use Chkltlabs\Porthole\Oauth\Server;
use Illuminate\Console\Command;
use Throwable;

final class CreateOauthClientCommand extends Command
{
    protected $signature = 'porthole:oauth-client {name} {redirect}';

    protected $description = 'Register a public OAuth client on the Porthole issuer.';

    public function handle(Server $server): int
    {
        try {
            $client = $server->registerPublicClient(
                (string) $this->argument('name'),
                [(string) $this->argument('redirect')],
            );
        } catch (OauthException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('The Porthole OAuth tables are not migrated.');

            return self::FAILURE;
        }

        $this->line('Client ID: '.$client['client_id']);
        $this->line('This client belongs to the Porthole issuer, not the application Passport server.');

        return self::SUCCESS;
    }
}
