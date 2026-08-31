<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Billing\AuthorizeNetGateway;
use App\Services\Billing\Dunning;
use App\Services\Billing\PaymentMethodReplacement;
use App\Services\Billing\SubscriptionCancellation;

/**
 * What a tenant can actually do about the card behind their plan (6500–6519).
 *
 * ⛔ **THE STATE 6404 FOUND AND COULD NOT ACT ON.**
 * {@see AuthorizeNetGateway::replacePaymentMethod()} calls itself *"the dunning
 * remedy, and the **only** recovery that actually works on this gateway"* and
 * had no caller anywhere in `app/`. The obvious substitute was worse than
 * nothing: {@see AuthorizeNetGateway::subscribe()} throws outright for any
 * business already holding a subscription id, which is **every** business a
 * dunning notice is sent to, so an *"Update my card"* button wired to the signup
 * checkout renders, gets pressed by somebody trying to save their plan, and
 * 500s.
 *
 * ⚠️ **SIX CASES BECAUSE THERE ARE SIX HONEST SENTENCES, NOT BECAUSE THERE ARE
 * SIX CODE PATHS** — {@see CancellationOutcome}'s rule, on the same screen. A
 * single *"add a card"* control would be a false offer to four of these six
 * populations, and the falsest of them is the tenant whose plan has already
 * ended: on this vendor a terminated subscription *"can no longer be reactivated
 * and must be recreated"* (Recurring Billing feature guide,
 * `developer.authorize.net/api/reference/features/recurring-billing.html`, read
 * 2026-08-21), and nothing in this application can recreate one.
 *
 * ⛔ **NONE OF THESE IS A SUBSCRIPTION STATUS AND NONE IS EVER STORED.** They
 * describe what this screen may offer, right now. `subscriptions.status` moves
 * only when a verified webhook says so (2056), and replacing a card writes none
 * of it — see {@see PaymentMethodReplacement} for why the successful path
 * deliberately leaves the row and {@see Dunning}'s schedule exactly as it found
 * them.
 *
 * A string cast to a PHP backed enum, never a database enum — and this one has
 * no column at all.
 */
enum CardReplacementOffer: string
{
    /**
     * There is a live Authorize.Net subscription to move a card onto.
     *
     * The dunning population, and also the ordinary one: a tenant whose card is
     * about to expire has the same need and a month more warning.
     */
    case Available = 'available';

    /**
     * `28` §9.5's suspension — the account is stopped, for cause.
     *
     * ⚠️ **THIS IS NOT THE NON-PAYMENT CASE AND CONFLATING THE TWO IS THE
     * MISTAKE THIS CASE EXISTS TO PREVENT.** `TenantSuspension::suspend()`
     * writes `businesses.suspended_at`; `Subscriptions::suspendForNonPayment()`
     * writes a subscription status and **touches it not at all**. A tenant in
     * dunning is not suspended in this sense and must still be offered the form.
     *
     * ⛔ **AND THE REASON IT IS REFUSED HERE IS MECHANICAL AS WELL AS
     * EDITORIAL.** `SuspendedTenantStatus` exempts `account.plan` by name so
     * that a suspended tenant can still cancel — and exempts nothing else, so a
     * Livewire action on that screen posts to `default-livewire.update` and is
     * sent to the on-hold page. Rendering the form would put a card field in
     * front of somebody whose press can only ever land on a status page.
     */
    case AccountOnHold = 'account_on_hold';

    /**
     * The plan has ended. A new card cannot bring it back.
     *
     * ⛔ **THE VENDOR IS EXPLICIT AND THE API HAS NO ANSWER TO IT.** A suspended
     * ARB subscription that nobody corrects before its next run date is
     * *terminated*, and *"once terminated, a subscription can no longer be
     * reactivated and must be recreated"*. The Authorize.Net XML schema
     * (`apitest.authorize.net/xml/v1/schema/AnetApiSchema.xsd`, read 2026-08-21)
     * declares exactly six ARB operations — create, update, cancel, get, get
     * status, get list — and **there is no reactivate**. So there is no call
     * this application could make, and offering a card field here would be a
     * form whose only possible outcome is a vendor refusal.
     */
    case PlanHasEnded = 'plan_has_ended';

    /**
     * Every payment for this plan has already been taken.
     *
     * The annual plan bought in instalments: three payments a cycle apart finish
     * inside the first quarter and the vendor then reports `expired` (2748).
     * Nothing is owed and nothing renews (2749), so a new card would be a card
     * for a charge that is never going to happen.
     *
     * ⚠️ **ONLY THE VENDOR KNOWS THIS, SO ONLY THE PRESS CAN RETURN IT.**
     * {@see PaymentMethodReplacement::offer()} reads our own row and cannot tell
     * it from {@see self::Available} — the same one-directional imprecision
     * {@see SubscriptionCancellation::preview()} accepts, and for the same
     * reason: a `GET` that opened a socket to a payment gateway would be a
     * vendor round trip on every view of a page people land on to read.
     */
    case NothingIsDue = 'nothing_is_due';

    /** There is no paid plan on this account, so there is no card to replace. */
    case NoPlanToPayFor = 'no_plan_to_pay_for';

    /**
     * The plan is billed through Stripe, whose card path is not this one.
     *
     * ⚠️ **NOT BUILT, AND SAID RATHER THAN IMPLIED** (2056 keeps both gateways
     * live). Stripe's equivalent is a SetupIntent or the hosted billing portal,
     * and neither exists in `app/`; pointing `replacePaymentMethod()` — an
     * Authorize.Net method, on Authorize.Net ids — at a Stripe subscription
     * refuses before it reaches a vendor at all. The tenant is pointed at a
     * person instead of at a form that cannot work.
     */
    case HandledByStripe = 'handled_by_stripe';

    /**
     * Whether this screen may render a card field at all.
     *
     * A method rather than a comparison at each call site, for
     * {@see CancellationOutcome::endedSomething()}'s reason: the day a seventh
     * case exists this goes red instead of quietly picking a side.
     *
     * ⛔ **AND IT WAS `$this === self::Available` UNTIL 9229, WHICH IS THE ONE
     * SPELLING THAT DOES NOT DO WHAT THE SENTENCE ABOVE PROMISES.** A seventh
     * case would have answered `false` here silently — the card panel simply
     * absent for a population nobody had thought about — where an exhaustive
     * `match` raises `UnhandledMatchError` and names the case. That is
     * `CLAUDE.md`'s 314–316 in its cheapest form: the paragraph asserting the
     * property is what stopped anybody checking the line under it, and
     * {@see CancellationOutcome::impliesALivePlan()} two files away had been
     * written as a `match` all along.
     */
    public function offersAForm(): bool
    {
        return match ($this) {
            self::Available => true,
            self::AccountOnHold, self::PlanHasEnded, self::NothingIsDue,
            self::NoPlanToPayFor, self::HandledByStripe => false,
        };
    }

    /**
     * Whether this account has never bought a plan, and may be offered one.
     *
     * ⛔ **THE POPULATION 9201 CREATES, AND UNTIL 9229 NO SCREEN SAID ANYTHING
     * TO IT.** Registration used to open on a card checkout; it now lands on
     * `/setup`, so an owner reaches the end of onboarding having bought nothing
     * — and `/account/plan`'s card panel had no arm for {@see self::NoPlanToPayFor}
     * at all. It rendered a heading, *"you do not have a paid plan"*, and no
     * control of any kind: `CLAUDE.md`'s *a rendered screen with no control on
     * it*, one panel down.
     *
     * ⚠️ **THIS IS THE SAME QUESTION `offer()` ALREADY ANSWERS, ASKED BY A
     * SECOND SCREEN RATHER THAN RE-DERIVED.** The wizard's last step needs to
     * know whether to invite a first purchase, and the alternative was an eighth
     * spelling of *"is there a vendor subscription id on this row"* — there are
     * seven in `app/` already. {@see PaymentMethodReplacement::offer()} reads our
     * own row and calls nobody, which is what makes it affordable on a page
     * people land on to read.
     *
     * ⛔ **FALSE FOR `PlanHasEnded`, AND THAT IS A RULING RATHER THAN AN
     * OVERSIGHT.** On this vendor a terminated subscription *"can no longer be
     * reactivated and must be recreated"* and nothing here recreates one (8975,
     * raised at 6508), so an invitation there would be a promise no code keeps.
     * False for `AccountOnHold` for the reason that case carries; false for
     * `HandledByStripe` and `Available` because both mean a plan already exists.
     */
    public function invitesAFirstPlan(): bool
    {
        return match ($this) {
            self::NoPlanToPayFor => true,
            self::Available, self::AccountOnHold, self::PlanHasEnded,
            self::NothingIsDue, self::HandledByStripe => false,
        };
    }
}
