<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Billing\CreditLedger;

/**
 * Why a tenant may or may not spend one product's credit right now — the states a
 * balance of zero cannot tell apart (decisions 3960, 3609, 3610, 3824).
 *
 * ⛔ **A BALANCE OF ZERO IS THREE DIFFERENT FACTS WITH THREE DIFFERENT REMEDIES,
 * AND A BOOLEAN LOSES ALL OF THEM.** {@see CreditLedger::spendVerdict()} is the one
 * place they are told apart, and this enum is what it says:
 *
 *   {@see self::Spendable}    there is credit and the plan may spend it. **The
 *                             balance is this account's ceiling** (3293), and
 *                             nothing else has standing to refuse it
 *   {@see self::Exhausted}    funded, active, and spent. The remedy is a purchase
 *                             or the period boundary
 *   {@see self::PlanInactive} nothing spendable *because the plan lapsed*. The
 *                             balance may be large and 3441 says it is
 *                             **waiting**, not gone. The remedy is resubscribing
 *   {@see self::NeverFunded}  granted nothing, bought nothing, adjusted nothing.
 *                             3609 permits it, because gating a bare zero would
 *                             have stopped every tenant at once on the day the
 *                             gate shipped
 *
 * ⚠️ **THE LAST TWO BOTH READ ZERO AND ONE OF THEM IS PERMITTED**, which is the
 * whole reason this is an enum rather than a `bool`. Collapsing them refuses an
 * account the monthly reset has simply not reached yet; collapsing the middle two
 * tells a tenant holding $250 of purchased credit that they have run out.
 *
 * ⚠️ **IT IS NOT A DATABASE COLUMN AND MUST NOT BECOME ONE.** It is derived from
 * the ledger, the pools and the subscription on every read, so a stored copy would
 * be a second source of truth that goes stale the moment a card is retried. It is
 * backed only so that a log line and a refusal reason can carry it.
 */
enum CreditVerdict: string
{
    /** There is spendable credit, so the balance is the ceiling. */
    case Spendable = 'spendable';

    /** Funded and active, with nothing left. */
    case Exhausted = 'exhausted';

    /** Nothing spendable because the plan is inactive — 3441, and not an expiry. */
    case PlanInactive = 'plan_inactive';

    /** Never granted, bought or adjusted anything of this product (3609). */
    case NeverFunded = 'never_funded';

    /**
     * Whether this verdict permits another spend.
     *
     * ⚠️ **A `match` RATHER THAN A COMPARISON, SO A FIFTH STATE CANNOT INHERIT AN
     * ANSWER NOBODY CHOSE** — `CreditPool::expiresAtPeriodBoundary()`'s reasoning,
     * and the answer here decides whether a tenant may spend money.
     *
     * ⛔ **`NeverFunded` PERMITS, AND IT IS THE ONE THAT LOOKS WRONG** (3609). It
     * is deliberate, and it is why the AI path keeps an outer cost cap *behind*
     * this answer: a balance cannot bound an account that has never had one, so
     * something else has to (3820, 3960).
     */
    public function permitsSpending(): bool
    {
        return match ($this) {
            self::Spendable, self::NeverFunded => true,
            self::Exhausted, self::PlanInactive => false,
        };
    }
}
