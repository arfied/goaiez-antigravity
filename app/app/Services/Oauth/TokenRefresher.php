<?php

declare(strict_types=1);

namespace App\Services\Oauth;

use App\Exceptions\TokenRefreshFailed;

/**
 * One provider's answer to "make this credential usable again".
 *
 * Implementations do exactly one HTTP call and no retrying. Retries are the
 * queue's job — see config/oauth.php `timeout`.
 *
 * Socialite is deliberately not used for this, even though AbstractProvider has
 * a refreshToken() method, and the reasons are per-provider rather than
 * stylistic (all verified 2026-07-31):
 *
 *   Google      Socialite's GoogleProvider posts to the legacy
 *               https://www.googleapis.com/oauth2/v4/token; Google currently
 *               documents https://oauth2.googleapis.com/token.
 *
 *   Facebook    FacebookProvider does not override refreshToken(), so it
 *               inherits AbstractProvider's generic `grant_type=refresh_token`
 *               — a grant Meta does not implement. It would also TypeError
 *               before it got that far: Socialite\Two\Token's constructor
 *               requires a non-null string refresh token and Meta returns none.
 *
 *   Microsoft   Socialite ships no Microsoft driver at all.
 *
 * And one reason that applies to all three: Socialite builds its own Guzzle
 * client, which Http::fake() and Http::preventStrayRequests() cannot see. Every
 * call in this namespace goes through Laravel's HTTP client so that a test can
 * assert the request shape and can never reach a live endpoint.
 */
interface TokenRefresher
{
    /**
     * @param  StoredCredentials  $stored  Plaintext, decrypted by the vault.
     *                                     Never logged, never returned in an
     *                                     exception, never serialized.
     *
     * @throws TokenRefreshFailed
     */
    public function refresh(StoredCredentials $stored): RefreshedCredentials;
}
