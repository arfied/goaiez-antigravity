<?php

declare(strict_types=1);

namespace App\Services\Oauth;

use App\Enums\OauthProvider;
use App\Exceptions\TokenRefreshFailed;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Microsoft Entra ID v2.0 refresh_token grant.
 *
 * https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow,
 * updated 2026-06-15:
 *
 *   POST https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token
 *        client_id, client_secret, grant_type=refresh_token, refresh_token,
 *        scope
 *
 *   200  { access_token, token_type, expires_in, scope, refresh_token, id_token }
 *
 * The rotation is the thing to get right. Microsoft returns **a new refresh
 * token on every refresh** and the documentation is explicit: "Replace the old
 * refresh token with this newly acquired refresh token to ensure your refresh
 * tokens remain valid for as long as possible" — and separately, "Refresh tokens
 * aren't revoked when used to acquire new access tokens. You're expected to
 * discard the old refresh token."
 *
 * Failing to persist the rotation does not break anything immediately, which is
 * what makes it dangerous: the old token keeps working until Microsoft decides
 * to revoke it, and the integration then dies weeks later with no proximate
 * cause. TokenService persists whatever comes back here.
 *
 * `offline_access` must have been in the original authorization or no refresh
 * token was ever issued. That is enforced at the config layer — see
 * config/oauth.php.
 *
 * Errors arrive as { error, error_description, error_codes[], timestamp,
 * trace_id, correlation_id }. Only `error` travels onward: error_description
 * embeds the full AADSTS text plus trace and correlation ids, and correlation
 * ids identify a tenant.
 */
final class MicrosoftTokenRefresher implements TokenRefresher
{
    public function refresh(StoredCredentials $stored): RefreshedCredentials
    {
        $refreshToken = $stored->refreshToken;

        if ($refreshToken === null || $refreshToken === '') {
            throw TokenRefreshFailed::permanent(OauthProvider::Microsoft, 'no_refresh_token');
        }

        $endpoint = self::tokenEndpoint();

        /** @var list<string> $scopes */
        $scopes = config('oauth.providers.microsoft.scopes', []);

        try {
            $response = VendorLog::timed(
                OauthProvider::Microsoft->value,
                'POST',
                $endpoint,
                fn () => Http::asForm()
                    ->timeout((int) config('oauth.timeout', 10))
                    ->acceptJson()
                    ->post($endpoint, [
                        'client_id' => (string) config('services.microsoft.client_id'),
                        'client_secret' => (string) config('services.microsoft.client_secret'),
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $refreshToken,
                        'scope' => implode(' ', $scopes),
                    ]),
            );
        } catch (ConnectionException $e) {
            VendorLog::failure(OauthProvider::Microsoft->value, 'POST', $endpoint, $e::class);

            // `unreachable()`, not `transient()`: the request never became a
            // response, so the reason describes our network and never
            // Microsoft's. See TokenRefreshFailed::unreachable() (7400).
            throw TokenRefreshFailed::unreachable(OauthProvider::Microsoft, 'connection');
        }

        if ($response->serverError()) {
            throw TokenRefreshFailed::transient(
                OauthProvider::Microsoft,
                'http_'.$response->status(),
            );
        }

        if ($response->failed()) {
            $error = $response->json('error');

            throw TokenRefreshFailed::permanent(
                OauthProvider::Microsoft,
                is_string($error) ? $error : 'http_'.$response->status(),
            );
        }

        $accessToken = $response->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw TokenRefreshFailed::permanent(OauthProvider::Microsoft, 'no_access_token');
        }

        $rotated = $response->json('refresh_token');
        $expiresIn = $response->json('expires_in');
        $scope = $response->json('scope');

        return RefreshedCredentials::expiringIn(
            $accessToken,
            is_int($expiresIn) ? $expiresIn : null,
            // Non-null when Microsoft rotated, which is every time in practice.
            is_string($rotated) && $rotated !== '' ? $rotated : null,
            is_string($scope) && $scope !== '' ? explode(' ', $scope) : null,
        );
    }

    /**
     * The tenant-templated token URL.
     *
     * `common` admits work, school and personal accounts. A single-tenant
     * deployment substitutes its directory id, which is why this is a template
     * rather than a constant.
     */
    public static function tokenEndpoint(): string
    {
        return str_replace(
            '{tenant}',
            (string) config('services.microsoft.tenant', 'common'),
            (string) config('oauth.providers.microsoft.token_endpoint'),
        );
    }

    public static function authorizeEndpoint(): string
    {
        return str_replace(
            '{tenant}',
            (string) config('services.microsoft.tenant', 'common'),
            (string) config('oauth.providers.microsoft.authorize_endpoint'),
        );
    }
}
