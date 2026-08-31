<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What one reconciliation attempt did about one purchase (decision 3483's gap).
 *
 * A string column cast to this enum with a CHECK naming the same values —
 * `CLAUDE.md`'s standing rule and 216's two layers. It is the sweep's own record,
 * distinct from `credit_purchases.status`, and the distinction is load-bearing:
 * **the sweep writes to the purchase row only through
 * {@see App\Services\Billing\CreditPurchases::settle()}**, so everything else it
 * concludes has to live somewhere, and this is that somewhere.
 *
 * ⛔ **A TERMINAL VERDICT IS THE ONLY THING THAT STOPS THE SWEEP, WHICH IS WHY IT
 * IS A ROW RATHER THAN A STATUS CHANGE.** Marking a purchase `failed` because a
 * gateway said "declined" would be the tidy answer and it is the dangerous one:
 * `settle()` claims `WHERE status IN ('pending','authorized')`, so a purchase the
 * sweep moved out of a settleable state can **never** be credited by a
 * notification that arrives afterwards. The sweep must not be able to disarm the
 * path it exists to back up.
 */
enum PurchaseReconciliationVerdict: string
{
    /** The gateway said paid, and this attempt is what moved the balance. */
    case Credited = 'credited';

    /**
     * The gateway said paid and the purchase was already settled.
     *
     * ⚠️ **THE RACE WITH A LATE NOTIFICATION, AND IT IS A SUCCESS.** Both this and
     * the webhook go through the same conditional claim, so whichever arrives
     * second updates nothing and is told so. The balance moves once.
     */
    case AlreadySettled = 'already_settled';

    /**
     * The gateway said paid, for an amount that is not the SKU's price.
     *
     * `CreditPurchases::settle()` refused and moved the purchase to `mismatched`,
     * which is a person's job. Nothing here re-decides that.
     */
    case Mismatched = 'mismatched';

    /** The gateway says no money is ours. Terminal: never swept again. */
    case NotPaid = 'not_paid';

    /** The money moved and was returned. Terminal, and nothing is credited. */
    case Refunded = 'refunded';

    /** No answer either way. Retried, up to the attempt bound. */
    case Ambiguous = 'ambiguous';

    /** The vendor could not be asked. Retried, up to the attempt bound. */
    case Unreachable = 'unreachable';

    /**
     * The attempt bound was reached and the sweep stopped looking.
     *
     * ⛔ **THE ONE VERDICT THAT IS ALWAYS LOGGED AND ALWAYS AUDITED.** A silently
     * abandoned paid-and-uncredited purchase is precisely the defect this sweep
     * exists to fix, reappearing one level up — so giving up is an event with a
     * record, an operator can find it, and the purchase row itself is left in the
     * settleable state a person or a late notification can still resolve.
     */
    case Abandoned = 'abandoned';

    /**
     * Whether this attempt closed the question for good.
     *
     * A `match` rather than an `in_array`, so a ninth case cannot inherit an
     * answer nobody chose — and here the answer decides whether a tenant who has
     * paid is ever looked at again.
     */
    public function stopsTheSweep(): bool
    {
        return match ($this) {
            self::Credited,
            self::AlreadySettled,
            self::Mismatched,
            self::NotPaid,
            self::Refunded,
            self::Abandoned => true,
            self::Ambiguous, self::Unreachable => false,
        };
    }
}
