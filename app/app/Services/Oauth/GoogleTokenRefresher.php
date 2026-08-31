<?php

declare(strict_types=1);

namespace App\Services\Oauth;

use App\Enums\OauthProvider;
use App\Exceptions\TokenRefreshFailed;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google's refresh_token grant.
 *
 * https://developers.google.com/identity/protocols/oauth2/web-server, read
 * 2026-07-31:
 *
 *   POST https://oauth2.googleapis.com/token
 *        client_id, client_secret, grant_type=refresh_token, refresh_token
 *
 *   200  { access_token, expires_in, token_type: "Bearer", scope }
 *        — and no refresh_token. The one we sent stays valid.
 *
 *   400  { error: "invalid_grant" } when the token "may have expired or has
 *        been invalidated. Authenticate the user again and ask for user consent
 *        to obtain new tokens." Google does not distinguish revoked from
 *        expired, so neither do we — both become ConnectionStatus::Expired and
 *        a Reconnect notification, which is the same remedy either way.
 *
 * Refresh tokens themselves are "valid until the user revokes access or the
 * refresh token expires"; Google states no fixed lifetime. Nothing here may
 * assume one.
 *
 * ⚠️ **ONE GRANT SHAPE, MORE THAN ONE PROVIDER ROW.** Google Search Console is a
 * separate `OauthProvider` case with a separate `oauth_connections` row and a
 * separate scope (decisions 1082–1083), and it refreshes through this exact
 * endpoint with this exact grant. The provider is therefore a constructor
 * parameter rather than a constant: the mechanics are shared, but the label on
 * the log line, the health row and the exception must name the connection that
 * actually broke. Refreshing a Search Console grant and recording it against
 * `google` would put an alarm on the wrong integration — and the tenant would be
 * sent to reconnect their Business Profile, which is fine.
 *
 * The OAuth *client* is genuinely shared — `config('services.google')` — because
 * it is one Cloud project and one client id issuing all of these grants. Only the
 * scope and the redirect differ.
 */
final class GoogleTokenRefresher implements TokenRefresher
{
    public function __construct(
        private readonly OauthProvider $provider = OauthProvider::Google,
    ) {}

    public function refresh(StoredCredentials $stored): RefreshedCredentials
    {
        $refreshToken = $stored->refreshToken;

        if ($refreshToken === null || $refreshToken === '') {
            // No refresh token means access_type=offline was never requested, or
            // the grant was already cleared. Either way there is nothing to try.
            throw TokenRefreshFailed::permanent($this->provider, 'no_refresh_token');
        }

        $endpoint = (string) config("oauth.providers.{$this->provider->value}.token_endpoint");

        try {
            $response = VendorLog::timed(
                $this->provider->value,
                'POST',
                $endpoint,
                fn () => Http::asForm()
                    ->timeout((int) config('oauth.timeout', 10))
                    ->acceptJson()
                    ->post($endpoint, [
                        'client_id' => (string) config('services.google.client_id'),
                        'client_secret' => (string) config('services.google.client_secret'),
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $refreshToken,
                    ]),
            );
        } catch (ConnectionException $e) {
            VendorLog::failure($this->provider->value, 'POST', $endpoint, $e::class);

            // ⛔ **`unreachable()` RATHER THAN `transient()`, AND THE DIFFERENCE
            // IS WHO THE REASON IS ABOUT** (7400). Both are transient and both
            // retry identically; only this one says the request never became a
            // response. `TokenService::refresh()` reads that and writes nothing
            // to `provider_health` — because a DNS failure of ours has no true
            // value to put in a column that is a sentence about Google.
            throw TokenRefreshFailed::unreachable($this->provider, 'connection');
        }

        if ($response->serverError()) {
            throw TokenRefreshFailed::transient(
                $this->provider,
                'http_'.$response->status(),
            );
        }

        if ($response->failed()) {
            // Only the error *code* travels onward. error_description is a free
            // text field that has been observed to echo request parameters.
            $error = $response->json('error');

            throw TokenRefreshFailed::permanent(
                $this->provider,
                is_string($error) ? $error : 'http_'.$response->status(),
            );
        }

        $accessToken = $response->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            // A 200 with no token is a contract break, not an outage. Retrying
            // would just produce the same nonsense.
            throw TokenRefreshFailed::permanent($this->provider, 'no_access_token');
        }

        $expiresIn = $response->json('expires_in');
        $scope = $response->json('scope');

        return RefreshedCredentials::expiringIn(
            $accessToken,
            is_int($expiresIn) ? $expiresIn : null,
            // Null, not $refreshToken: null means "keep the stored one", which is
            // exactly right when the provider returned nothing.
            null,
            is_string($scope) && $scope !== '' ? explode(' ', $scope) : null,
        );
    }
}
