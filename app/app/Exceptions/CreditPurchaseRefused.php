<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a top-up purchase may not be opened, charged or settled as asked.
 *
 * ⚠️ **DISTINCT FROM `CreditMovementRefused`, AND THE DISTINCTION IS WHICH SIDE
 * OF THE MONEY IT IS ON.** That one is the ledger refusing a movement — a tenant
 * who has run out, an incoherent kind, a balance that would go below zero — and
 * every sender in this application already catches it into a soft refusal (2904).
 * This one is the *funder* refusing to charge somebody, and its callers are a
 * screen or an operator rather than a send loop: nothing degrades gracefully past
 * it, because there is nothing to degrade to. A purchase that cannot be opened is
 * simply not opened.
 *
 * ⛔ **IT NEVER ESCAPES A WEBHOOK.** Settlement runs inside
 * {@see App\Services\Billing\CreditPurchases}, which turns every refusal at that
 * point into a recorded outcome on the purchase row instead — a `mismatched` row
 * an operator can see. Throwing out of a webhook handler would make the gateway
 * retry a decision that was made correctly, which is 3239's instruction.
 *
 * The messages are written for a developer or an operator, never a customer.
 */
final class CreditPurchaseRefused extends RuntimeException
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
