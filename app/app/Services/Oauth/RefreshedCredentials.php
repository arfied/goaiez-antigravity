<?php

declare(strict_types=1);

namespace App\Services\Oauth;

use Illuminate\Support\Carbon;

/**
 * What a successful refresh produced, normalised across three providers that
 * disagree about almost everything.
 *
 * `refreshToken` is nullable and that nullability is load-bearing:
 *
 *   Google      returns no refresh_token on refresh. The existing one stays
 *               valid, so null here means "keep what you have".
 *   Microsoft   returns a NEW refresh_token every time and expects the old one
 *               to be discarded. Non-null here means "replace it".
 *   Meta        has no refresh token at all. Always null — the access token is
 *               the only credential, and extending it replaces it.
 *
 * A caller that treats null as "clear the stored refresh token" breaks Google
 * permanently on the first refresh. TokenService is the only caller, and it
 * treats null as "leave it alone".
 */
final readonly class RefreshedCredentials
{
    /**
     * @param  list<string>|null  $scopes  Null when the provider did not say,
     *                                     which is different from "none".
     */
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public ?Carbon $expiresAt,
        public ?array $scopes = null,
    ) {}

    /**
     * Build from a provider's `expires_in` seconds.
     *
     * Recorded as an absolute instant rather than a duration because the row
     * outlives the request that wrote it, and a duration is only meaningful
     * relative to a moment nobody stored.
     *
     * @param  list<string>|null  $scopes
     */
    public static function expiringIn(
        string $accessToken,
        ?int $seconds,
        ?string $refreshToken = null,
        ?array $scopes = null,
    ): self {
        return new self(
            $accessToken,
            $refreshToken,
            $seconds === null ? null : Carbon::now()->addSeconds($seconds),
            $scopes,
        );
    }
}
