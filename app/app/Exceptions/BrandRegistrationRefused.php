<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a tenant's own 10DLC filing may not be recorded, approved or
 * refused as asked (decision 3310).
 *
 * ⚠️ **NOT A SEND REFUSAL.** `SendRefusalReason` is an expected answer about one
 * contact that a runner writes on a row and moves past; this is an operator doing
 * something the registration service will not do at all, with a person waiting
 * for the command to respond — `CampaignRefused`'s distinction, one service over.
 *
 * The messages are written for an operator, never a customer, and none of them
 * names a person or carries a vendor credential.
 */
final class BrandRegistrationRefused extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function because(string $message): self
    {
        return new self($message);
    }
}
