<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Billing\CreditClawbacks;

/**
 * Why the money for a credit top-up went back to the person who paid it.
 *
 * ⛔ **THE THREE ARE NOT INTERCHANGEABLE AND ARE DELIBERATELY NOT ONE STRING.**
 * A `reason` sentence typed at the call site is a sentence somebody rewrites; a
 * case is a fact a test can assert and an audit reader can filter on a year
 * later. What they share is that the balance moves the same way — what differs is
 * what the row *means* during a billing dispute, which is the same distinction
 * 2685 kept between *"the gateway cancelled it"* and *"we exhausted our retries"*.
 *
 * ⚠️ **ONLY {@see CreditClawbacks} CONSTRUCTS THESE**, because it is the only
 * thing that writes {@see CreditKind::Refund} — see the chokepoint lint in
 * `Architecture\BillingTest`.
 */
enum CreditReversalCause: string
{
    /**
     * The merchant returned the money — ours or a support operator's act.
     *
     * `charge.refunded` on Stripe and `net.authorize.payment.refund.created` on
     * Authorize.Net, both read from the vendors' own event catalogues on
     * 2026-08-20.
     */
    case Refunded = 'refunded';

    /**
     * The cardholder disputed the charge and the money was taken back from us.
     *
     * ⛔ **STRIPE ONLY, AND THAT IS A FACT ABOUT THE VENDOR RATHER THAN A GAP IN
     * THIS ENUM.** Stripe raises `charge.dispute.funds_withdrawn`;
     * **Authorize.Net publishes no chargeback or dispute webhook event at all** —
     * its whole `net.authorize.*` catalogue was read on 2026-08-20 and there is
     * none. On that gateway a chargeback is a `transactionStatus` of `chargeback`
     * or `returnedItem` on the *original* transaction, visible only by asking the
     * Transaction Details API — which nothing does for a purchase that has
     * already credited. Decision 6392.
     */
    case ChargedBack = 'charged_back';

    /**
     * The charge was cancelled before it settled, so the money never left.
     *
     * `net.authorize.payment.void.created`. Stripe has no equivalent on this
     * path: Checkout captures immediately, so there is no unsettled charge to
     * cancel and a reversal there is always a refund or a dispute.
     */
    case Voided = 'voided';

    /**
     * What the owner is told, in outcome language and with no figure in it.
     *
     * ⚠️ **IT NAMES WHAT HAPPENED TO THEIR MONEY, NEVER WHAT HAPPENED IN OUR
     * LEDGER** (`22`'s outcome-language rule). *"A refund was clawed back"* is
     * this system describing itself; *"your top-up was refunded"* is the fact the
     * person can act on. The consequence to the balance is the second half of the
     * sentence {@see CreditClawbacks} builds around this.
     */
    public function tenantWording(): string
    {
        return match ($this) {
            self::Refunded => 'was refunded',
            self::ChargedBack => 'was charged back',
            self::Voided => 'was cancelled before it went through',
        };
    }

    /**
     * The sentence that lands on the ledger row itself.
     *
     * ⚠️ **A LEDGER `reason` IS READ BY A SUPPORT OPERATOR AGAINST A BALANCE THAT
     * MAY LOOK PERFECTLY HEALTHY**, months later, which is why it says what moved
     * the money rather than which webhook fired. The gateway and the transaction
     * id are on the audit entry beside it, where an incident review will look.
     */
    public function ledgerReason(): string
    {
        return match ($this) {
            self::Refunded => 'The payment for this top-up was refunded, so the credit it bought goes back.',
            self::ChargedBack => 'The payment for this top-up was charged back, so the credit it bought goes back.',
            self::Voided => 'The payment for this top-up was cancelled before it settled, so the credit it bought goes back.',
        };
    }
}
