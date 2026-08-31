<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a business stands with us commercially.
 *
 * ⚠️ **THESE ARE STRIPE'S OWN STATUS STRINGS, AND THAT IS DELIBERATE.** Slice B
 * writes this column from `customer.subscription.*` webhooks, and a translation
 * table between Stripe's vocabulary and ours would be a second place for the two
 * to disagree — with the disagreement showing up as a tenant billed for a plan
 * they do not have, or served a plan they are not paying for. Stripe is the
 * source of truth for subscription state (`cashier-billing` skill: "webhooks are
 * the source of truth, never the post-Checkout redirect"), so the column speaks
 * its language.
 *
 * ⚠️ **ONE CASE IS OURS AND NOT STRIPE'S — `pending_checkout` (decision 685).**
 * The paragraph above refuses a *translation table*: a second name for a state
 * Stripe already names. This is the opposite — the state before Stripe knows
 * this business exists at all, which Stripe has no word for because there is no
 * Stripe object yet. Slice A wrote `trialing` here and said at the write that it
 * meant "registered" rather than "card captured" (586); now that a card is
 * collectable, keeping that would leave the one word that decides entitlement
 * meaning two different things. So the honest state gets its own name, and
 * `trialing` gets Stripe's meaning back and a CHECK requiring a subscription
 * behind it.
 *
 * ⚠️ **NOT EVERY STRIPE STATUS IS HERE.** `unpaid` and `paused` exist upstream
 * and are absent, because nothing decides what they mean for this product yet —
 * `CLAUDE.md` is explicit that "nobody has decided what a delinquent tenant
 * loses or when", and inventing a case would answer that question in code. Slice
 * B's webhook handler must refuse a status it does not recognise rather than
 * coerce it to the nearest one: a subscription in an unmodelled state is a thing
 * a person needs to look at, not a thing to round off.
 *
 * A string column cast to this enum, never a database enum — `CLAUDE.md`'s
 * standing rule, and this is exactly the churn-prone set that rule is about.
 */
enum SubscriptionStatus: string
{
    /**
     * Registered, and Stripe has not been told yet. **Our word, not Stripe's.**
     *
     * What provisioning writes: the person has an account and a tenant, and no
     * Checkout Session has completed, so there is no subscription and no card. A
     * CHECK refuses this status with a `stripe_subscription_id` present — the
     * inverse of the constraint on `active`, and for the same reason: a status
     * that can lie about what is behind it is a status nothing can be decided
     * from.
     *
     * ⚠️ **A STRIPE *CUSTOMER* MAY WELL EXIST HERE.** One is created to open the
     * Checkout Session, so it exists while somebody is on Stripe's payment page
     * and for good if they close the tab. This state means "no subscription",
     * never "Stripe has never heard of them".
     *
     * ⚠️ **IT IS ENTITLED, LIKE EVERY OTHER FAIL-OPEN HERE (588).** "What a
     * person who abandons Checkout loses, and when" is the same undecided
     * question as delinquency, which `CLAUDE.md` reserves and slice C asks the
     * owner. Making this the first unentitled state would answer it by
     * accident, in the direction that locks people out.
     *
     * ⛔ **THE PREMISE OFFERED FOR THAT SENTENCE WAS FALSE, AND THE CONCLUSION
     * IS UNCHANGED — BOTH READINGS KEPT AND DATED, 2026-08-24** (4368). It read
     * *"Nothing in `app/` calls `isEntitled()` yet, so refusing here would
     * invent an enforcement policy in the slice that happens to have added the
     * state"*. **Entitlement is consumed, on both of the methods that answer
     * it** — this enum's `isEntitled()` and `Services\Billing\Subscriptions`'s,
     * spelled bare because Pint promotes an `@see` into a real `use` import and
     * this enum imports nothing. Between them they are asked wherever credit is
     * granted, spent, topped up or auto-charged, on the tenant's own credit
     * screen, on the content publish path, on both of the surfaces an operator
     * reads an account through, and — since wave 40 (10854) — on the weekly
     * owner digest. ⛔ **DO NOT MAINTAIN THAT LIST BY HAND; IT WENT STALE
     * WITHIN A DAY OF BEING WRITTEN AND THE DIGEST IS THE ROT** (10972). The
     * exact set is `tests/Feature/BillingSubscriptionTest.php`'s and it fails
     * the build on an addition; **the property is what to read here** — an
     * entitlement question is asked wherever this platform grants, spends or
     * says something on a tenant's behalf.
     *
     * ⛔ **THE ENUMERATION IS A TEST RATHER THAN A SENTENCE, AND THAT IS THE
     * WHOLE OF THE FIX.** A call-site count written into a docblock is exactly
     * what rotted here: the callers arrived one slice at a time, each of them
     * correct, and nothing could redden. `tests/Feature/BillingSubscriptionTest.php`
     * now holds the consumer list as an exact set compared against `app/`, so
     * the next caller to arrive fails the build here rather than falsifying this
     * paragraph in silence.
     *
     * ⚠️ **AND THE ARGUMENT GOT STRONGER RATHER THAN WEAKER**, which is why
     * the ruling stands. With no consumer, refusing here would have decided
     * nothing; with these, it withdraws the monthly allotment, credit spend,
     * auto top-up, content publishing and the weekly owner digest from every
     * account that has registered and not finished Checkout — which is every account, for the whole interval
     * this state exists to describe. ⚠️ **It is also not the ungated state it
     * reads as**: `Console\Commands\ResetMonthlyCredits` puts a non-paying status
     * through `TrialGrantAuthorization` before minting anything, so 2066's
     * no-card-trial fraud surface is handled where the money is rather than by
     * this enum refusing entitlement.
     */
    case PendingCheckout = 'pending_checkout';

    /**
     * The card-required free trial (decisions 98, 544 — 14 days).
     *
     * ⚠️ **THIS NOW MEANS WHAT STRIPE MEANS BY IT, WHICH IT DID NOT IN SLICE A**
     * (686). A row in this state has a `stripe_subscription_id`, a card behind
     * it, and a `trial_ends_at` that came from Stripe's own `trial_end` rather
     * than from our arithmetic — enforced by a CHECK, not merely by the writer.
     * The state slice A used this for is {@see self::PendingCheckout}.
     */
    case Trialing = 'trialing';

    /** Paying. Stripe has a live subscription and the last invoice was paid. */
    case Active = 'active';

    /** A charge failed and Stripe is retrying. Dunning is slice C's. */
    case PastDue = 'past_due';

    /** Ended — by the customer, by us, or by exhausted retries. */
    case Canceled = 'canceled';

    /**
     * Stripe has a subscription but never got a successful first payment.
     *
     * The state a signup lands in when the card is declined at Checkout, and the
     * reason `active` is not the only non-trial case a webhook may write.
     */
    case Incomplete = 'incomplete';

    /**
     * Whether this state entitles the business to the product.
     *
     * ⚠️ **`past_due` IS ENTITLED, ON PURPOSE.** Decision 82's "never hard-fail"
     * is a cost-cap rule and `CLAUDE.md` says plainly that it "does not obviously
     * extend to non-payment" — so this method does not decide what a delinquent
     * tenant loses, it only refuses to cut them off during Stripe's retry window,
     * which is hours to days and routinely a card expiry rather than a refusal to
     * pay. Whoever builds dunning (slice C) decides when `past_due` stops being
     * entitled; that decision belongs to the owner and is not made here.
     */
    public function isEntitled(): bool
    {
        return match ($this) {
            // ⚠️ `PendingCheckout` IS ENTITLED, AND IT IS THE ONLY BRANCH WITH
            // REAL TENANTS ON IT — every business that has ever registered is
            // here until it finishes Checkout, exactly as 588 said of a missing
            // row. Refusing here would invent an answer to "what does a person
            // who never adds a card lose, and when", which `CLAUDE.md` reserves
            // and slice C asks the owner. `29` §2 rule 43's "never hard-fail"
            // and `CLAUDE.md`'s "never bill by surprise" both point this way.
            self::PendingCheckout, self::Trialing, self::Active, self::PastDue => true,
            self::Canceled, self::Incomplete => false,
        };
    }
}
