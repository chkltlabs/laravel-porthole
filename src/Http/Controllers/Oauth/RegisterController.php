<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Http\Controllers\Oauth;

use Chkltlabs\Porthole\Oauth\OauthException;
use Chkltlabs\Porthole\Oauth\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class RegisterController
{
    public function __construct(private Server $server) {}

    public function __invoke(Request $request): JsonResponse
    {
        $redirects = $request->input('redirect_uris', []);

        if (! is_array($redirects)) {
            return $this->error(new OauthException('invalid_redirect_uri', 'redirect_uris must be a list of URIs.'));
        }

        /** @var list<string> $redirectUris */
        $redirectUris = array_values(array_filter($redirects, is_string(...)));

        if (count($redirectUris) !== count($redirects)) {
            return $this->error(new OauthException('invalid_redirect_uri', 'redirect_uris must be a list of URIs.'));
        }

        $name = $request->input('client_name', $request->input('name'));

        if (! is_string($name) || trim($name) === '') {
            $host = parse_url($redirectUris[0] ?? '', PHP_URL_HOST);
            $name = is_string($host) && $host !== '' ? $host : 'MCP Client';
        }

        try {
            $client = $this->server->registerPublicClient(
                $name,
                $redirectUris,
                $this->optionalString($request->input('logo_uri')),
                $this->optionalString($request->input('client_uri')),
            );
        } catch (OauthException $exception) {
            return $this->error($exception);
        } catch (Throwable $exception) {
            report($exception);

            return $this->error(new OauthException('server_error', 'The client could not be registered.', 500));
        }

        unset($client['client_name']);

        return response()->json($client, 201);
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function error(OauthException $exception): JsonResponse
    {
        return response()->json([
            'error' => $exception->error,
            'error_description' => $exception->getMessage(),
        ], $exception->status);
    }
}
