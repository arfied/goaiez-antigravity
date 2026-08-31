<?php

declare(strict_types=1);

namespace App\Services\Oauth;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

/**
 * Microsoft Entra ID as a Socialite driver, first-party.
 *
 * **Socialite ships no Microsoft driver.** Its built-ins are Facebook, X,
 * LinkedIn, Google, GitHub, GitLab, Bitbucket and Slack (verified against
 * https://laravel.com/docs/13.x/socialite on 2026-07-31), and FOUND-03/FOUND-04
 * both assume one exists. The community package is
 * `socialiteproviders/microsoft`, which is not an approved dependency —
 * docs/DECISIONS.md 143-145 approve larastan alone.
 *
 * WHY EXTEND AbstractProvider RATHER THAN HAND-ROLL THE FLOW. The authorization
 * code flow is four HTTP interactions and one security property, and the
 * security property is the whole difficulty: `state` must be generated,
 * session-stored, and compared in constant time on the way back, or the callback
 * is open to CSRF-style login forcing. AbstractProvider already does that —
 * redirect() stores state, user() calls hasInvalidState(), which pulls it from
 * the session and hash_equals() it. Subclassing inherits all of it and leaves
 * exactly four things to supply: two endpoints, the profile call, and the
 * mapping. Writing the flow by hand would mean reimplementing the one part that
 * is easy to get subtly and silently wrong.
 *
 * Endpoints from
 * https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow
 * (updated 2026-06-15); the profile call is Graph's /v1.0/me.
 *
 * PKCE is enabled. The documentation now calls code_challenge "recommended for
 * all application types, both public and confidential clients" — it costs
 * nothing here because AbstractProvider implements it, and it closes
 * authorization-code interception.
 */
final class MicrosoftSocialiteProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * Microsoft separates scopes with spaces, not commas.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * Overridden from config at registration; these are the floor.
     *
     * `offline_access` is not optional. Without it Microsoft issues no refresh
     * token at all, and the vault has nothing to renew — the connection simply
     * dies an hour after it is made.
     *
     * @var array<int, string>
     */
    protected $scopes = ['openid', 'profile', 'email', 'offline_access'];

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(
            MicrosoftTokenRefresher::authorizeEndpoint(),
            $state,
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl(): string
    {
        // The same endpoint the vault refreshes against, from the same config
        // key. Two spellings of one URL is how a tenant override gets applied to
        // login and missed on refresh.
        return MicrosoftTokenRefresher::tokenEndpoint();
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(
            rtrim((string) config('oauth.providers.microsoft.graph_endpoint'), '/').'/me',
            [
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.$token,
                ],
            ],
        );

        $decoded = json_decode((string) $response->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * {@inheritdoc}
     *
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => Arr::get($user, 'id'),
            'nickname' => null,
            'name' => Arr::get($user, 'displayName'),
            // `mail` is the licensed mailbox address and is null for accounts
            // without one; userPrincipalName always exists. Preferring `mail`
            // and falling back is what stops a personal account signing in with
            // a UPN that is not an address anyone can receive at.
            'email' => Arr::get($user, 'mail') ?? Arr::get($user, 'userPrincipalName'),
            'avatar' => null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function usesPKCE(): bool
    {
        return true;
    }
}
