<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a campaign may not be written, confirmed, enrolled or resumed as
 * asked.
 *
 * ⚠️ **DISTINCT FROM A SEND REFUSAL, AND THE DIFFERENCE IS WHO IS STANDING
 * THERE.** `SendRefusalReason` is a frequent, expected *answer* about one
 * contact — suppression, quiet hours, an exhausted balance — and the runner
 * writes it on a row and carries on. This is a caller doing something the
 * campaign engine will not do at all, with a person waiting for the screen to
 * respond, and every case names the thing they can change.
 *
 * The messages are written for an operator or an owner, never a customer, and
 * none of them names a contact.
 */
final class CampaignRefused extends RuntimeException
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
