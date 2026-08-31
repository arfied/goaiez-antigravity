<?php

declare(strict_types=1);

namespace App\Services\Oauth;

use App\Enums\OauthProvider;
use App\Exceptions\TokenRefreshFailed;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Meta's long-lived token exchange — which is not a refresh, and cannot be.
 *
 * FOUND-03 asks for "transparent auto-refresh" across Google, Facebook and
 * Microsoft. Two of the three can do that. Meta cannot, and no amount of code
 * makes it possible.
 *
 * https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived,
 * read 2026-07-31:
 *
 *   - Meta issues no refresh token. There is no refresh_token grant.
 *   - The only mechanism is exchanging a token for a longer-lived one:
 *
 *       GET https://graph.facebook.com/{version}/oauth/access_token
 *           ?grant_type=fb_exchange_token
 *           &client_id=&client_secret=&fb_exchange_token=<current access token>
 *
 *   - A long-lived user token "generally lasts about 60 days".
 *   - And the sentence that decides the design: "You can not use an expired
 *     token to request a long-lived token. If the token has expired, your app
 *     must send the user through the login flow again to regenerate a new
 *     short-lived access token."
 *
 * So the window for keeping a Meta connection alive without the owner is *while
 * the token still works*. Once it lapses, a human must reconnect — there is no
 * server-side recovery. That makes the scheduled sweep load-bearing for Meta in
 * a way it is not for Google or Microsoft: miss the window and the connection is
 * gone until someone clicks.
 *
 * Long-lived *Page* tokens are the exception — they "do not have an expiration
 * date and only expire or are invalidated under certain conditions" — and are
 * obtained from /{user-id}/accounts with a long-lived user token. That is
 * MetaService's job, not this class's.
 *
 * The token travels in the query string because that is the documented shape.
 * VendorLog strips query strings before recording an endpoint precisely because
 * of this call.
 */
final class MetaTokenRefresher implements TokenRefresher
{
    public function refresh(StoredCredentials $stored): RefreshedCredentials
    {
        // The *access* token, not a refresh token. Meta has none.
        $current = $stored->accessToken;

        if ($current === null || $current === '') {
            throw TokenRefreshFailed::permanent(OauthProvider::Facebook, 'no_access_token');
        }

        $endpoint = self::exchangeEndpoint();

        try {
            $response = VendorLog::timed(
                OauthProvider::Facebook->value,
                'GET',
                $endpoint,
                fn () => Http::timeout((int) config('oauth.timeout', 10))
                    ->acceptJson()
                    ->get($endpoint, [
                        'grant_type' => 'fb_exchange_token',
                        'client_id' => (string) config('services.facebook.client_id'),
                        'client_secret' => (string) config('services.facebook.client_secret'),
                        'fb_exchange_token' => $current,
                    ]),
            );
        } catch (ConnectionException $e) {
            VendorLog::failure(OauthProvider::Facebook->value, 'GET', $endpoint, $e::class);

            // `unreachable()`, not `transient()`: the request never became a
            // response, so the reason describes our network and never Meta's.
            // See TokenRefreshFailed::unreachable() (7400).
            throw TokenRefreshFailed::unreachable(OauthProvider::Facebook, 'connection');
        }

        if ($response->serverError()) {
            throw TokenRefreshFailed::transient(
                OauthProvider::Facebook,
                'http_'.$response->status(),
            );
        }

        if ($response->failed()) {
            // Meta nests its errors: { error: { message, type, code, ... } }.
            // Only the numeric code travels — `message` is free text that echoes
            // the request, token included.
            $code = $response->json('error.code');

            throw TokenRefreshFailed::permanent(
                OauthProvider::Facebook,
                is_int($code) ? 'meta_'.$code : 'http_'.$response->status(),
            );
        }

        $accessToken = $response->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw TokenRefreshFailed::permanent(OauthProvider::Facebook, 'no_access_token');
        }

        $expiresIn = $response->json('expires_in');

        return RefreshedCredentials::expiringIn(
            $accessToken,
            is_int($expiresIn) ? $expiresIn : null,
            // Always null. Meta has no refresh token to store, and pretending
            // otherwise would put an access token in the refresh column.
            null,
        );
    }

    /**
     * The version-pinned exchange URL.
     *
     * Pinned rather than defaulted because Meta serves the *oldest* available
     * version when the path carries none — the opposite of every other vendor's
     * behaviour, and a silent one.
     */
    public static function exchangeEndpoint(): string
    {
        return sprintf(
            '%s/%s/oauth/access_token',
            rtrim((string) config('oauth.providers.facebook.graph_endpoint'), '/'),
            (string) config('oauth.providers.facebook.graph_version'),
        );
    }
}
