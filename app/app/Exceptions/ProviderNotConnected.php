<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\OauthProvider;
use RuntimeException;

/**
 * Thrown when a usable token was asked for and the vault has none.
 *
 * Covers three states that all mean the same thing to a caller — never
 * connected, connected but revoked, and connected but unrefreshable. They differ
 * only in what the owner must do about it, which is the Reconnect notification's
 * job to say, not this exception's.
 *
 * Never carries token material, and never carries a provider error body: both
 * reach exception messages, logs, and error trackers.
 */
final class ProviderNotConnected extends RuntimeException
{
    private function __construct(
        public readonly OauthProvider $provider,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function missing(OauthProvider $provider): self
    {
        return new self(
            $provider,
            "No active {$provider->value} connection for this business.",
        );
    }

    public static function unusable(OauthProvider $provider): self
    {
        return new self(
            $provider,
            "The {$provider->value} connection needs reconnecting before it can be used.",
        );
    }
}
