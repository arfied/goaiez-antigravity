<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What one dunning attempt did (decision 2133, T137 SL-11).
 *
 * ⚠️ **AUTHORIZE.NET HAS NO NATIVE DUNNING AND DECISION 2108 SAYS SO OUT LOUD.**
 * Verified against the vendor's live Recurring Billing documentation (read
 * 2026-08-11): a declined payment *suspends* the subscription and the vendor
 * *terminates* it if the merchant takes no action before the next run date.
 * There is no published retry schedule, no `invoice.payment_failed` equivalent
 * that resolves itself, and nothing that emails the cardholder. Every one of
 * those is ours, and this enum is what records that they happened.
 *
 * ⚠️ **AN ATTEMPT ROW IS WRITTEN FOR EVERY OUTCOME INCLUDING THE BORING ONES.**
 * "Nothing happened" and "we never tried" look identical from a support screen a
 * month later, and the second is the one that means the schedule stopped
 * running. Decision 620's inversion — eight writers and no reader — is the shape
 * to avoid here in reverse: the reader is the suspension decision, so an unwritten
 * attempt silently changes it.
 */
enum DunningOutcome: string
{
    /**
     * The retry charged successfully and the subscription is running again.
     *
     * ⚠️ Written only after the *gateway* says so, never after our own request
     * returns 200 — `CLAUDE.md`'s webhooks-are-the-source-of-truth rule holds on
     * this path too, and the reactivation is confirmed by the subscription's own
     * status, not by the response to the call that asked for it.
     */
    case Recovered = 'recovered';

    /** The retry was attempted and the card declined again. */
    case Declined = 'declined';

    /**
     * The attempt could not be made — the gateway was unreachable, or refused
     * for a reason that is ours rather than the cardholder's.
     *
     * ⚠️ **DELIBERATELY NOT COUNTED TOWARD SUSPENSION.** A tenant must never lose
     * the product because our API credential expired or Authorize.Net had an
     * outage; that is `29` §2 rule 43's "never hard-fail" and it is the failure
     * mode that would hit every tenant at once, on the day it is least noticed.
     */
    case Unreachable = 'unreachable';

    /**
     * The schedule ran out of attempts and the subscription was suspended.
     *
     * The terminal row. What a person reads to answer "why did this tenant stop
     * having access", which is the only question anybody asks of this table.
     */
    case Exhausted = 'exhausted';

    /**
     * The tenant fixed the payment method themselves, so the schedule stopped.
     *
     * Distinct from {@see self::Recovered}: that one is our retry succeeding,
     * this one is the tenant acting, and a sustained run of the second with none
     * of the first means our retries are not working at all.
     */
    case ResolvedByTenant = 'resolved_by_tenant';

    /**
     * The subscription ended at the gateway, so there was nothing left to retry.
     *
     * ⚠️ **DISTINCT FROM {@see self::Exhausted}, AND THE DISTINCTION IS THE
     * WHOLE POINT OF THIS CASE** (decision 2685). Both end a schedule with a
     * tenant who is no longer paying, and they are different facts about **who
     * ended the relationship**: `Exhausted` is us, after the number of attempts
     * this class decides; this is the vendor reporting the subscription
     * cancelled, terminated or finished while our schedule still had attempts
     * left. A dunning history is read during a billing dispute — "we stopped
     * retrying because there was nothing to retry" and "we ran out of attempts
     * and withdrew the product" cannot be the same row.
     *
     * ⚠️ **AND IT WITHDRAWS NOTHING.** `Subscriptions::suspendForNonPayment()`
     * stays reachable only from `Exhausted` (2142): the vendor's own
     * cancellation has already moved the row through the ordinary webhook
     * projection, and calling the suspension arm on top of it would write a
     * second cancellation over a `Canceled` row — which is what was happening
     * before this case existed, several attempts later, for no reason anybody
     * reading the table could reconstruct.
     */
    case CanceledAtGateway = 'canceled_at_gateway';

    /**
     * Whether this outcome ends the schedule.
     *
     * A `match` rather than an `in_array`, so that a sixth case cannot inherit an
     * answer nobody chose — `CreditKind::isAlwaysCredit()`'s reasoning.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Recovered, self::Exhausted, self::ResolvedByTenant, self::CanceledAtGateway => true,
            self::Declined, self::Unreachable => false,
        };
    }

    /**
     * Whether this outcome counts against the retry budget.
     *
     * ⚠️ **`Unreachable` DOES NOT, AND THAT IS THE LOAD-BEARING LINE.** If it
     * did, a vendor outage would burn a tenant's whole retry budget in an
     * afternoon and suspend them for something they did not do.
     */
    public function countsAsFailure(): bool
    {
        return match ($this) {
            self::Declined => true,
            // ⚠️ `CanceledAtGateway` DOES NOT COUNT EITHER, AND IT IS TERMINAL
            // ANYWAY — so this arm can only be read by a schedule that was
            // reopened afterwards. It answers "no" because the tenant did not
            // decline anything: the subscription stopped existing.
            self::Recovered, self::Unreachable, self::Exhausted,
            self::ResolvedByTenant, self::CanceledAtGateway => false,
        };
    }
}
