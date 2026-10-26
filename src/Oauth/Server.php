<?php

declare(strict_types=1);

namespace Chkltlabs\Porthole\Oauth;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class Server
{
    public const SCOPE = 'mcp:use';

    private const CODE_TTL_SECONDS = 60;

    private const ACCESS_TTL_SECONDS = 3600;

    private const REFRESH_TTL_SECONDS = 2592000;

    /**
     * @param  list<string>  $redirectUris
     * @return array{client_id: string, redirect_uris: list<string>, grant_types: list<string>, response_types: list<string>, token_endpoint_auth_method: string, scope: string, client_name: string}
     */
    public function registerPublicClient(string $name, array $redirectUris, ?string $logoUri = null, ?string $clientUri = null): array
    {
        $name = trim($name);

        if ($name === '' || strlen($name) > 255) {
            throw new OauthException('invalid_client_metadata', 'The client name is invalid.');
        }

        if ($redirectUris === [] || count($redirectUris) > 10) {
            throw new OauthException('invalid_redirect_uri', 'Provide between 1 and 10 redirect URIs.');
        }

        $uris = [];

        foreach ($redirectUris as $uri) {
            if (! is_string($uri) || ! RedirectUri::allowed($uri)) {
                throw new OauthException('invalid_redirect_uri', 'A redirect URI is not allowed.');
            }

            $uris[] = $uri;
        }

        $id = str_replace('-', '', (string) Str::uuid());

        DB::table('porthole_oauth_clients')->insert([
            'id' => $id,
            'name' => $name,
            'redirect_uris' => json_encode(array_values(array_unique($uris)), JSON_THROW_ON_ERROR),
            'logo_uri' => $this->optionalHttpsUrl($logoUri),
            'client_uri' => $this->optionalHttpsUrl($clientUri),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'client_id' => $id,
            'client_name' => $name,
            'redirect_uris' => array_values(array_unique($uris)),
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => self::SCOPE,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{client_id: string, client_name: string, redirect_uri: string, code_challenge: string, state: string, scope: string}
     */
    public function parseAuthorize(array $query): array
    {
        $clientId = $this->stringValue($query['client_id'] ?? null);
        $redirectUri = $this->stringValue($query['redirect_uri'] ?? null);
        $client = $clientId === null ? null : $this->client($clientId);
        $matchedRedirect = $client !== null && $redirectUri !== null && $this->redirectMatches($client, $redirectUri)
            ? $redirectUri
            : null;
        $state = $this->stringValue($query['state'] ?? null);

        if (($query['response_type'] ?? null) !== 'code' || $client === null || $matchedRedirect === null) {
            throw new OauthException('invalid_request', 'The authorization request is invalid.');
        }

        if ($state === null || strlen($state) > 512) {
            throw new OauthException('invalid_request', 'The state parameter is required.', redirectUri: $matchedRedirect);
        }

        $method = $this->stringValue($query['code_challenge_method'] ?? null) ?? 'plain';
        $challenge = $this->stringValue($query['code_challenge'] ?? null);

        if ($method !== 'S256' || $challenge === null || strlen($challenge) > 128) {
            throw new OauthException('invalid_request', 'PKCE S256 is required.', redirectUri: $matchedRedirect, state: $state);
        }

        $scope = $this->stringValue($query['scope'] ?? null) ?? self::SCOPE;

        if ($scope !== self::SCOPE) {
            throw new OauthException('invalid_scope', 'The only supported scope is mcp:use.', redirectUri: $matchedRedirect, state: $state);
        }

        return [
            'client_id' => $clientId,
            'client_name' => (string) $client->name,
            'redirect_uri' => $matchedRedirect,
            'code_challenge' => $challenge,
            'state' => $state,
            'scope' => self::SCOPE,
        ];
    }

    /**
     * @param  array{client_id: string, redirect_uri: string, code_challenge: string, state: string, scope: string}  $request
     */
    public function issueCode(int|string $userId, array $request): string
    {
        $code = $this->randomToken();

        DB::table('porthole_oauth_codes')->insert([
            'id' => hash('sha256', $code),
            'client_id' => $request['client_id'],
            'user_id' => (string) $userId,
            'redirect_uri' => $request['redirect_uri'],
            'code_challenge' => $request['code_challenge'],
            'expires_at' => now()->addSeconds(self::CODE_TTL_SECONDS),
        ]);

        return $code;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string}
     */
    public function exchangeAuthorizationCode(array $input): array
    {
        $clientId = $this->stringValue($input['client_id'] ?? null);
        $code = $this->stringValue($input['code'] ?? null);
        $redirectUri = $this->stringValue($input['redirect_uri'] ?? null);
        $verifier = $this->stringValue($input['code_verifier'] ?? null);

        if ($clientId === null || $code === null || $redirectUri === null || $verifier === null || $this->client($clientId) === null) {
            throw new OauthException('invalid_client', 'The client could not be authenticated.', 401);
        }

        $row = DB::transaction(function () use ($code): ?object {
            $row = DB::table('porthole_oauth_codes')->where('id', hash('sha256', $code))->lockForUpdate()->first();

            if ($row === null) {
                return null;
            }

            DB::table('porthole_oauth_codes')->where('id', $row->id)->delete();

            return $row;
        });

        if (
            $row === null
            || Carbon::parse($row->expires_at)->lte(now())
            || ! $this->same((string) $row->client_id, $clientId)
            || ! $this->same((string) $row->redirect_uri, $redirectUri)
            || ! Pkce::matches($verifier, (string) $row->code_challenge)
        ) {
            throw new OauthException('invalid_grant', 'The authorization code is invalid.');
        }

        $pair = $this->issueTokenPair((string) $row->client_id, (string) $row->user_id, now()->addSeconds(self::REFRESH_TTL_SECONDS));
        unset($pair['id']);

        return $pair;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string}
     */
    public function refresh(array $input): array
    {
        $clientId = $this->stringValue($input['client_id'] ?? null);
        $refreshToken = $this->stringValue($input['refresh_token'] ?? null);

        if ($clientId === null || $refreshToken === null || $this->client($clientId) === null) {
            throw new OauthException('invalid_client', 'The client could not be authenticated.', 401);
        }

        $row = DB::transaction(function () use ($refreshToken): ?object {
            return DB::table('porthole_oauth_tokens')
                ->where('refresh_token_hash', hash('sha256', $refreshToken))
                ->lockForUpdate()
                ->first();
        });

        if ($row === null || ! $this->same((string) $row->client_id, $clientId)) {
            throw new OauthException('invalid_grant', 'The refresh token is invalid.');
        }

        if ((int) $row->revoked === 1) {
            DB::table('porthole_oauth_tokens')
                ->where('client_id', $row->client_id)
                ->where('user_id', $row->user_id)
                ->update([
                    'revoked' => true,
                    'updated_at' => now(),
                ]);

            throw new OauthException('invalid_grant', 'The refresh token is invalid.');
        }

        if (Carbon::parse($row->refresh_expires_at)->lte(now()) || (string) $row->scope !== self::SCOPE) {
            throw new OauthException('invalid_grant', 'The refresh token is invalid.');
        }

        return DB::transaction(function () use ($row): array {
            $current = DB::table('porthole_oauth_tokens')->where('id', $row->id)->lockForUpdate()->first();

            if ($current === null || (int) $current->revoked === 1) {
                throw new OauthException('invalid_grant', 'The refresh token is invalid.');
            }

            $pair = $this->issueTokenPair((string) $current->client_id, (string) $current->user_id, Carbon::parse($current->refresh_expires_at));

            DB::table('porthole_oauth_tokens')->where('id', $current->id)->update([
                'revoked' => true,
                'replaced_by_id' => $pair['id'],
                'updated_at' => now(),
            ]);

            unset($pair['id']);

            return $pair;
        });
    }

    public function userIdForAccessToken(string $token): ?string
    {
        try {
            $row = DB::table('porthole_oauth_tokens')
                ->where('access_token_hash', hash('sha256', $token))
                ->first();
        } catch (Throwable) {
            return null;
        }

        if (
            $row === null
            || (int) $row->revoked === 1
            || (string) $row->scope !== self::SCOPE
            || Carbon::parse($row->expires_at)->lte(now())
        ) {
            return null;
        }

        return (string) $row->user_id;
    }

    public function authorizationRedirect(OauthException $exception): ?string
    {
        if ($exception->redirectUri === null) {
            return null;
        }

        $separator = str_contains($exception->redirectUri, '?') ? '&' : '?';
        $query = http_build_query(array_filter([
            'error' => $exception->error,
            'error_description' => $exception->getMessage(),
            'state' => $exception->state,
        ], fn (mixed $value): bool => is_string($value) && $value !== ''));

        return $exception->redirectUri.$separator.$query;
    }

    public function successRedirect(string $redirectUri, string $code, string $state): string
    {
        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return $redirectUri.$separator.http_build_query([
            'code' => $code,
            'state' => $state,
        ]);
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string, id: string}
     */
    private function issueTokenPair(string $clientId, string $userId, Carbon $refreshExpiresAt): array
    {
        $access = $this->randomToken();
        $refresh = $this->randomToken();
        $id = (string) Str::uuid();

        DB::table('porthole_oauth_tokens')->insert([
            'id' => $id,
            'client_id' => $clientId,
            'user_id' => $userId,
            'access_token_hash' => hash('sha256', $access),
            'refresh_token_hash' => hash('sha256', $refresh),
            'scope' => self::SCOPE,
            'expires_at' => now()->addSeconds(self::ACCESS_TTL_SECONDS),
            'refresh_expires_at' => $refreshExpiresAt,
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $id,
            'access_token' => $access,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TTL_SECONDS,
            'refresh_token' => $refresh,
            'scope' => self::SCOPE,
        ];
    }

    private function client(string $id): ?object
    {
        try {
            return DB::table('porthole_oauth_clients')->where('id', $id)->first();
        } catch (Throwable) {
            return null;
        }
    }

    private function redirectMatches(object $client, string $redirectUri): bool
    {
        $uris = is_string($client->redirect_uris)
            ? json_decode($client->redirect_uris, true)
            : $client->redirect_uris;

        if (! is_array($uris)) {
            return false;
        }

        foreach ($uris as $uri) {
            if (is_string($uri) && $this->same($uri, $redirectUri)) {
                return true;
            }
        }

        return false;
    }

    private function optionalHttpsUrl(?string $uri): ?string
    {
        if ($uri === null || $uri === '') {
            return null;
        }

        if (strlen($uri) > 2048 || ! Str::isUrl($uri, ['https'])) {
            throw new OauthException('invalid_client_metadata', 'Client URLs must be https.');
        }

        return $uri;
    }

    private function same(string $known, string $given): bool
    {
        return strlen($known) === strlen($given) && hash_equals($known, $given);
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
