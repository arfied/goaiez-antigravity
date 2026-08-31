<?php

declare(strict_types=1);

namespace App\Services\Oauth;

/**
 * The decrypted contents of one connection, on their way to a refresher.
 *
 * Both fields, not just the refresh token, because the three providers do not
 * agree on what a refresh even consumes:
 *
 *   Google, Microsoft   spend the refresh token; the access token is irrelevant
 *   Meta                has no refresh token and spends the *access* token,
 *                       exchanging a still-valid one for a longer-lived one
 *
 * A single-argument `refresh(string $refreshToken)` forces the Meta case to
 * smuggle an access token through a parameter named for something else, and the
 * next person to read it has no way to know. Passing both and letting each
 * refresher take what it needs costs one small class and removes the trap.
 *
 * Exists only in memory, only between the vault decrypting and a refresher
 * using it. Never serialized, never logged, never a job payload.
 */
final readonly class StoredCredentials
{
    public function __construct(
        public ?string $accessToken,
        public ?string $refreshToken,
    ) {}
}
