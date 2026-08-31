<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a credit movement may not be written as asked.
 *
 * ⚠️ **A refusal rather than a validation message, because `credit_ledger` is
 * append-only.** A row this service permits is a row nothing in the application
 * can take back — the model throws on update and delete — so the only correction
 * is a second, compensating movement, and the wrong one stays in the history
 * beside it forever. That is the opposite trade from a form field, where the cost
 * of a bad value is one edit.
 *
 * Every case `CreditLedger` itself raises is also caught by a CHECK constraint
 * underneath (decisions 216, 303–316's three layers). The layers are not
 * redundant: the constraint stops a repair script, and this stops a caller with
 * an error it can act on rather than a SQLSTATE it cannot.
 *
 * ⚠️ **`CreditGrants`' ceiling refusal is the one case that is not also a CHECK,
 * and that is stated rather than left implied** (decisions 314–316). A
 * per-actor ceiling — `support_agent` may grant at most 500 in one action — has
 * no column on `credit_ledger` to check against, because the row does not carry
 * who wrote it in a form a constraint can read. The two-layer claim above is
 * true of every case `CreditLedger` raises for itself; it would be false of this
 * one if this paragraph did not say so.
 *
 * The messages are written for a developer or an operator, never a customer —
 * `StaffChangeRefused`' rule. No path here is customer-facing.
 */
final class CreditMovementRefused extends RuntimeException
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
