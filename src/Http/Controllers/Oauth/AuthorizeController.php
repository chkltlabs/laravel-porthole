<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Http\Controllers\Oauth;

use Chkltlabs\Porthole\Oauth\OauthException;
use Chkltlabs\Porthole\Oauth\ResourceOwner;
use Chkltlabs\Porthole\Oauth\Server;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class AuthorizeController
{
    public function __construct(
        private Server $server,
        private ResourceOwner $owners,
    ) {}

    public function show(Request $request): View|JsonResponse|RedirectResponse
    {
        try {
            $pending = $this->server->parseAuthorize($request->query());
        } catch (OauthException $exception) {
            return $this->failure($exception);
        }

        $user = $request->user($this->owners->webGuard());

        if ($user === null) {
            return redirect()->guest($this->owners->loginUrl());
        }

        if (! $this->allowed($user)) {
            return $this->redirectError($pending['redirect_uri'], 'access_denied', 'This account is not allowed to use Porthole.', $pending['state']);
        }

        $requestId = Str::random(40);
        $request->session()->put('porthole.oauth.pending', [
            'id' => $requestId,
            'request' => $pending,
        ]);

        return view('porthole::oauth.consent', [
            'clientName' => $pending['client_name'],
            'redirectHost' => (string) parse_url($pending['redirect_uri'], PHP_URL_HOST),
            'requestId' => $requestId,
        ]);
    }

    public function approve(Request $request): JsonResponse|RedirectResponse
    {
        $pending = $request->session()->pull('porthole.oauth.pending');
        $requestId = $request->string('request')->toString();

        if (
            ! is_array($pending)
            || ! isset($pending['id'], $pending['request'])
            || ! is_string($pending['id'])
            || ! is_array($pending['request'])
            || strlen($pending['id']) !== strlen($requestId)
            || ! hash_equals($pending['id'], $requestId)
        ) {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'The authorization request expired.',
            ], 400);
        }

        /** @var array{client_id: string, client_name: string, redirect_uri: string, code_challenge: string, state: string, scope: string} $authorization */
        $authorization = $pending['request'];

        if ($request->string('decision')->toString() !== 'approve') {
            return $this->redirectError($authorization['redirect_uri'], 'access_denied', 'The resource owner denied the request.', $authorization['state']);
        }

        $user = $request->user($this->owners->webGuard());

        if ($user === null || ! $this->allowed($user)) {
            return $this->redirectError($authorization['redirect_uri'], 'access_denied', 'This account is not allowed to use Porthole.', $authorization['state']);
        }

        $code = $this->server->issueCode($user->getAuthIdentifier(), $authorization);

        return redirect()->away($this->server->successRedirect($authorization['redirect_uri'], $code, $authorization['state']));
    }

    private function allowed(Authenticatable $user): bool
    {
        return Gate::forUser($user)->has('viewPorthole') && Gate::forUser($user)->check('viewPorthole');
    }

    private function failure(OauthException $exception): JsonResponse|RedirectResponse
    {
        $location = $this->server->authorizationRedirect($exception);

        if ($location === null) {
            return response()->json([
                'error' => $exception->error,
                'error_description' => $exception->getMessage(),
            ], $exception->status);
        }

        return redirect()->away($location);
    }

    private function redirectError(string $redirectUri, string $error, string $description, string $state): RedirectResponse
    {
        $location = $this->server->authorizationRedirect(new OauthException(
            $error,
            $description,
            redirectUri: $redirectUri,
            state: $state,
        ));

        return redirect()->away($location ?? $redirectUri);
    }
}
