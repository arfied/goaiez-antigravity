<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Authorize.Net's own ARB subscription statuses, and the one place they are
 * translated into ours.
 *
 * ⚠️ **THIS IS THE TRANSLATION TABLE {@see SubscriptionStatus} REFUSES TO BE,
 * AND THE ASYMMETRY IS DELIBERATE (decision 2131).** `SubscriptionStatus`
 * deliberately speaks Stripe's vocabulary so that projecting a Stripe webhook
 * needs no mapping at all. Authorize.Net has a completely different and smaller
 * vocabulary — five words, none of which is `trialing`, `past_due` or
 * `incomplete` — so a translation is unavoidable on this side. Putting it in one
 * enum with one `match` is what keeps it from being scattered across a handler,
 * a job and a screen, which is how the two would come to disagree.
 *
 * ⚠️ **`suspended` IS THE WHOLE REASON DUNNING IS OUR WORK (2108).** Verified
 * against Authorize.Net's live Recurring Billing documentation (read
 * 2026-08-11): a subscription is *suspended* when a payment declines or the card
 * expires, and it is **terminated** if "a merchant takes no action on a suspended
 * account before the next runDate". The vendor does not retry the charge and
 * publishes no retry schedule — the merchant is expected to fix the payment
 * method and reactivate. So `suspended` is not a state that resolves itself, and
 * anything treating it like Stripe's `past_due` (which *is* an automatic retry
 * window) is assuming a mechanism that is not there.
 *
 * ⚠️ **THE STRINGS ARE NOT UPPERCASE AND ARE COMPARED CASE-INSENSITIVELY AT THE
 * BOUNDARY.** `ARBGetSubscriptionStatusResponse` returns `status` in lower case;
 * webhook payloads for `net.authorize.customer.subscription.*` carry the same
 * word. Neither is a contract we control, so {@see self::fromVendor()} lowercases
 * before matching rather than trusting either.
 *
 * A string cast to a PHP backed enum, never a database enum — `CLAUDE.md`.
 */
enum AuthorizeNetSubscriptionStatus: string
{
    /** Scheduled to be charged at the interval it was created with. */
    case Active = 'active';

    /** The schedule of payments has ended — `totalOccurrences` was reached. */
    case Expired = 'expired';

    /**
     * A payment declined, or the card on file expired.
     *
     * ⚠️ Not an automatic retry window. See the class docblock: the vendor waits
     * for the merchant, and terminates if nobody acts before the next run date.
     */
    case Suspended = 'suspended';

    /** Cancelled. The vendor's documentation says it "cannot be reactivated". */
    case Canceled = 'canceled';

    /** Suspended, and nobody acted before the next run date. */
    case Terminated = 'terminated';

    /**
     * Read a status off a vendor payload, or null if it is not one we model.
     *
     * ⚠️ **NULL RATHER THAN A DEFAULT.** `SubscriptionStatus`'s docblock states
     * the rule — "a subscription in an unmodelled state is a thing a person needs
     * to look at, not a thing to round off" — and it binds harder here, because
     * this vendor's five words are a documented closed set: a sixth means the
     * vendor changed something and a person should know.
     *
     * ⚠️ **Authorize.Net spells it `canceled` with one `l` and this method does
     * not assume that.** Their *webhook event type* is
     * `net.authorize.customer.subscription.cancelled` — two `l`s — while the ARB
     * status field is `canceled`. Both spellings are accepted here rather than
     * one being picked from memory; `CLAUDE.md`'s rule about verifying a vendor
     * string against the raw artefact is exactly this, and the two artefacts
     * disagree with each other.
     */
    public static function fromVendor(?string $status): ?self
    {
        if (! is_string($status)) {
            return null;
        }

        return match (strtolower(trim($status))) {
            'active' => self::Active,
            'expired' => self::Expired,
            'suspended' => self::Suspended,
            'canceled', 'cancelled' => self::Canceled,
            'terminated' => self::Terminated,
            default => null,
        };
    }

    /**
     * What this means for the tenant's own subscription row.
     *
     * ⚠️ **`Suspended` MAPS TO `past_due` AND THAT IS A CHOICE WITH A COST.**
     * `past_due` is entitled (see `SubscriptionStatus::isEntitled()`), so a
     * suspended Authorize.Net subscription leaves the tenant using the product
     * while nothing is billing them — which is `CLAUDE.md`'s "never hard-fail"
     * and "never bill by surprise" pointing the same way, and it is also the
     * expensive direction if nobody ever looks. **That is what
     * `App\Services\Billing\Dunning` exists to bound**: entitlement is not
     * withdrawn by this mapping, it is withdrawn by the dunning schedule after a
     * recorded number of failures, which is a decision with a number attached
     * rather than a side effect of a `match` arm.
     *
     * ⚠️ **NOTHING HERE PRODUCES `trialing`.** Authorize.Net has no trial status:
     * a trial is expressed as a future `startDate`, so "in trial" is a fact about
     * a date and not about this word. `App\Services\Billing\AuthorizeNetGateway`
     * derives it, and it is the one status on this path our row can hold that the
     * vendor cannot tell us.
     *
     * ⚠️ Both of those are spelled in prose rather than with an `@see` tag: Pint
     * promotes an `@see` into a real `use` import, and an enum importing two
     * services is a dependency nobody chose and a cycle waiting to happen.
     */
    public function toSubscriptionStatus(): SubscriptionStatus
    {
        return match ($this) {
            self::Active => SubscriptionStatus::Active,
            self::Suspended => SubscriptionStatus::PastDue,
            self::Expired, self::Canceled, self::Terminated => SubscriptionStatus::Canceled,
        };
    }
}
