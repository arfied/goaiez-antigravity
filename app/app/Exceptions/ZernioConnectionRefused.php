<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ZernioConnectionRefused extends RuntimeException
{
    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function notStarted(): self
    {
        return new self('not_started', 'No connection was started for this location.');
    }

    public static function wrongProfile(): self
    {
        return new self('wrong_profile', 'That connection belongs to a different account.');
    }

    public static function accountNotOnProfile(string $platformLabel): self
    {
        return new self(
            'account_not_on_profile',
            "That $platformLabel account is not connected to this business. Please start the connection again."
        );
    }

    public static function ceilingReached(string $platformLabel): self
    {
        return new self(
            'ceiling_reached',
            "New $platformLabel connections are paused. Reviews and replies continue through the handoff path."
        );
    }
}
