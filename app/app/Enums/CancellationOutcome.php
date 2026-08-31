<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Billing\StripeApi;

/**
 * What actually happens when a tenant cancels (2980–2999).
 *
 * ⚠️ **SIX CASES BECAUSE THERE ARE SIX HONEST SENTENCES, NOT BECAUSE THERE ARE
 * SIX CODE PATHS.** California's Automatic Renewal Law wants the cancellation
 * *mechanism* to be easy; what makes it usable is that the screen says what the
 * press did. The two gateways end a subscription in materially different ways —
 * Stripe can stop at the end of a period it has already charged for and
 * Authorize.Net cannot express that at all — and collapsing them into "your
 * plan is cancelled" would leave one of the two populations with a false
 * statement.
 *
 * ⛔ **NONE OF THESE IS A SUBSCRIPTION STATUS.** They describe the *request*.
 * `subscriptions.status` moves only when a verified webhook says so (2056), and
 * a screen that read a value from this enum as the state would be exactly the
 * optimistic write this whole design refuses.
 *
 * A string cast to a PHP backed enum, never a database enum — and in fact this
 * one is never stored at all.
 */
enum CancellationOutcome: string
{
    /**
     * Stripe: it stops at the end of the period already paid for.
     *
     * `cancel_at_period_end`, which is what {@see StripeApi}
     * sends. The customer keeps what they bought and is not charged again.
     */
    case StopsAtPeriodEnd = 'stops_at_period_end';

    /**
     * Authorize.Net, annual term, still inside the year that was bought.
     *
     * The vendor's subscription ends now — it has no scheduled cancellation —
     * and the paid term does not. See
     * `Subscriptions::applyAuthorizeNetSubscription()` for the one narrow arm
     * that keeps the row entitled until `annual_term_ends_on`.
     */
    case KeepsPaidTerm = 'keeps_paid_term';

    /**
     * Authorize.Net, monthly term.
     *
     * ⚠️ **THE REMAINDER OF THE CURRENT 30-DAY CYCLE IS LOST, AND THE SCREEN
     * SAYS SO BEFORE THE PRESS.** The vendor has no `cancel_at_period_end` and
     * this application stores no paid-through date on the monthly Authorize.Net
     * path, so there is no date to schedule against and nothing to inform a
     * fairer answer. Recorded as an owner question rather than papered over.
     */
    case StopsNow = 'stops_now';

    /**
     * The vendor's payment schedule has already finished.
     *
     * An annual plan bought in instalments completes inside the first quarter
     * and Authorize.Net reports it `expired` (2748), so there is no subscription
     * left to cancel — and, by 2749, nothing that would have renewed it either.
     * The request is still recorded: the tenant asked, and that is evidence.
     */
    case NoFurtherCharge = 'no_further_charge';

    /** It was already cancelled or terminated. Nothing to do, nothing to say. */
    case AlreadyEnded = 'already_ended';

    /** There is no subscription on this account at all. */
    case NothingToCancel = 'nothing_to_cancel';

    /**
     * Whether this outcome means a live subscription was actually stopped.
     *
     * The screen uses it to choose between a confirmation and an explanation,
     * and it is a method rather than a comparison at each call site for the
     * ordinary reason: the day a seventh case exists, this goes red.
     */
    public function endedSomething(): bool
    {
        return match ($this) {
            self::StopsAtPeriodEnd, self::KeepsPaidTerm, self::StopsNow => true,
            self::NoFurtherCharge, self::AlreadyEnded, self::NothingToCancel => false,
        };
    }

    /**
     * Whether there is a plan on this account at all — a price worth naming (4840).
     *
     * ⚠️ **NOT `endedSomething()` WITH A WIDER MOUTH.** That one answers *"would
     * pressing cancel do anything"*, which is false for a plan whose payments have
     * all been taken and which the tenant is still using — `NoFurtherCharge` is
     * exactly the annual-in-instalments tenant three months in, and telling them
     * their plan has no price would be a false statement about the money they have
     * already handed over.
     *
     * ⛔ **`AlreadyEnded` AND `NothingToCancel` ARE THE ONLY TWO THAT ARE FALSE**,
     * and they are false for two different reasons: nothing is being charged
     * because it stopped, and nothing is being charged because it never started.
     * Either way "what you pay" has no honest answer, and rendering the registry's
     * figure there would quote a stranger a price for a plan they do not have.
     *
     * A method rather than a comparison at each call site, for
     * {@see self::endedSomething()}'s reason: the day a seventh case exists, this
     * goes red instead of quietly picking a side.
     */
    public function impliesALivePlan(): bool
    {
        return match ($this) {
            self::StopsAtPeriodEnd, self::KeepsPaidTerm, self::StopsNow, self::NoFurtherCharge => true,
            self::AlreadyEnded, self::NothingToCancel => false,
        };
    }
}
