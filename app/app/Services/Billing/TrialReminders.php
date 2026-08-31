<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\LifecycleRung;
use App\Exceptions\MailNotDeliverable;
use App\Exceptions\MessageCannotBeComposed;
use App\Models\Business;
use App\Notifications\TrialReminder;
use App\Services\Mail\PlatformMailer;
use App\Services\Messaging\LifecycleLadder;
use App\Support\Messaging\LifecycleLadderCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Telling somebody their free trial is running out (9395–9404).
 *
 * ## ⛔ THE LADDER SHIPPED WITH NO SENDER AND SAID SO, WHICH IS WHY THIS IS A
 * SLICE RATHER THAN A FIX
 *
 * {@see LifecycleLadderCatalog} and {@see LifecycleLadder} landed at 5258 with
 * their own docblocks stating *"no scheduler picks a rung, no job sends one, and
 * no screen shows one"* and naming the dispatcher as owed. That is the honest
 * form of `CLAUDE.md`'s first recurring failure — a deliberate absence, written
 * down, rather than a control nobody noticed was dead — and this class is the
 * thing it said was owed. ⚠️ **Only the trial rungs.** The usage ladder is a
 * different clock and its beats have no writer here.
 *
 * ## ⛔ WHAT THIS EXISTS FOR: A CLOCK THAT RUNS AND IS RENDERED NOWHERE ANYBODY
 * LOOKS
 *
 * 9328 bounded the no-card trial at `billing.trial_days` from registration and
 * rendered the date on three screens. **All three are pull surfaces** — the
 * owner has to open them on the day in question — and the owner this product is
 * built for is the one who *"does nothing but reply to occasional text
 * messages"*. A bound nobody is told about is a product that stops for a reason
 * the person cannot tell apart from a fault.
 *
 * ## ⚠️ IT IS A PRODUCT CHOICE AND NOT A STATUTORY DUTY, AND THE DIFFERENCE
 * DECIDES THE MECHANISM
 *
 * {@see RenewalReminders} bounds California's Automatic Renewal Law to *"a
 * subscription whose term is a year or longer and which renews by itself"* and
 * says outright that *"it is not a duty on every subscription and inventing one
 * would be its own kind of wrong"*. **A no-card trial cannot auto-convert** —
 * there is no payment instrument — so the free-trial-conversion reminder has no
 * subject here, and `docs/39-LEGAL-PACK-DRAFTS.md` §27 defers auto-renewal
 * disclosure to counsel and scopes it to checkout. Nothing below claims
 * otherwise, and the asymmetry that follows from it is written at
 * {@see Subscriptions::claimTrialRung()}: that claim never lapses, because the
 * failure worth leaving standing here is the missed message rather than the
 * duplicate.
 *
 * ## ⛔ EMAIL ONLY, AND THE SMS HALF IS REFUSED RATHER THAN DEFERRED (9399)
 *
 * Three of the four **trial** rungs carry an authored SMS line and this class
 * sends none of them. ⚠️ **THE SCOPE OF THAT COUNT IS THE TRIAL LADDER AND NOT
 * THE LADDER** — six of the seven rungs carry a text,
 * {@see LifecycleRung::hasText()} is the answer, and `TrialDaySeven` is the only
 * case it refuses. 10548 names *"the six … SMS rungs"* as owed;
 * {@see LifecycleLadder}'s own docblock said **three** for the whole ladder
 * until 2026-08-28 and is corrected there (10942).
 *
 * ## ⛔ THE MONEY HALF OF 9399's ARGUMENT IS SUPERSEDED BY 10546, AND IS KEPT
 * DATED RATHER THAN DELETED (4368) — CORRECTED 2026-08-28 (10940)
 *
 * This paragraph read, and 9399 still reads: *"A text to an account holder is a
 * **send**: it spends the tenant's own SMS balance … So the shape it would take
 * is 'spend a trial account's 500 granted messages to ask them to pay us', on a
 * number nobody captured consent for, and `messaging_lane` is derived and
 * unsettable precisely so that an attestation cannot be written as consent."*
 *
 * ⛔ **THE FIRST CLAUSE IS NO LONGER TRUE OF THIS APPLICATION.** Wave 38 built
 * the owner channel and ruled at **10546** that an owner-channel send does
 * **not** debit the tenant's SMS credit grant: the 500 monthly messages are
 * priced for review invites, missed-call text-back and the chat bot, and
 * `PlatformMailer::send()`'s unpermitted account-holder mail was already
 * unmetered for the identical reason. `App\Services\Sms\PlatformTexter::sendToOwner()`
 * is the door and it takes no `SendCredits` debit;
 * `tests/Feature/Billing/OwnerChannelGrantTest.php` is what reddens if that
 * moves. ⚠️ **10546 is a lane's judgement call and says so** — *"the alternative
 * … is equally defensible and is the owner's to choose if this is revisited"* —
 * so what changed is which side is in force, not that the question closed.
 *
 * ⚠️ **THE REFUSAL STANDS, ON THE OTHER TWO REASONS AND ON ONE 9399 DID NOT
 * HAVE** (10941):
 *
 *   1. **The consent this platform captured does not disclose a purchase
 *      offer.** `App\Services\Consent\OwnerNotifyDisclosure::TEXT` is *"about
 *      your own account — things like an urgent message from a customer, or
 *      something that needs your reply"*. ⛔ **FIVE of the six SMS rungs ask the
 *      recipient to buy** — all three usage beats and the two middle trial
 *      beats say *"add a card"* or *"add your card now"*; only `trial_ended`
 *      does not. ⚠️ **That figure was written here as "four" and machine-counted
 *      to five before this docblock was committed**, which is the defect this
 *      whole correction is about happening inside the correction — so it is
 *      pinned by `tests/Feature/Billing/OwnerChannelGrantTest.php` rather than
 *      typed. A solicitation to buy is a marketing message: `CLAUDE.md` is
 *      explicit that an established business relationship is not prior express
 *      written consent for one (2100), and the wording somebody agreed to is
 *      the scope of what they agreed to.
 *   2. **The complaint rate still lands on our own 10DLC registration** (2101).
 *      10546 is about who pays, never about who is complained about.
 *   3. ⛔ **THIS SCHEDULE IS 05:45 AND A TEXT AT 05:45 IS INSIDE FEDERAL QUIET
 *      HOURS.** `routes/console.php` runs `billing:send-trial-reminders`
 *      `dailyAt('05:45')` because mail has no such band.
 *      `App\Services\Consent\OwnerConsentService` deliberately does not
 *      consult `App\Services\Consent\StateMessagingRules`, on an argument
 *      scoped in its own docblock to *"a transactional account notice"* — which
 *      four of these six are not. **So the SMS half cannot ride this schedule at
 *      all**, and that is a fact about the clock rather than an opinion about
 *      the copy.
 *
 * ✅ **The email is free, it is transactional, and it reaches the same person.**
 *
 * ## ⚠️ THE COMPOSITION CAN REFUSE, AND A REFUSAL MUST NOT CLAIM
 *
 * Two rungs carry `{guarantee}` and {@see LifecycleLadder} refuses to compose
 * them while `legal.guarantee_sentence` is empty — 5258's fail-closed ruling,
 * which is not this class's to reverse. So composition happens **before** the
 * claim: a rung that cannot be composed leaves the row untouched rather than
 * burning its one chance, and the run reports the refusal instead of dying
 * inside a sweep over every tenant on the platform.
 */
final class TrialReminders
{
    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly PlatformMailer $mailer,
        private readonly LifecycleLadder $ladder,
        private readonly PlanCharges $charges = new PlanCharges,
    ) {}

    /**
     * The rung this business is owed today, or null.
     *
     * ⚠️ **EQUALITY ON A WHOLE-DAY COUNT, AND EVERY WORD OF THAT IS
     * LOAD-BEARING.** *Whole-day*, because the trial ends at an instant partway
     * through a calendar day and a message that says *"4 days left"* is a
     * statement about days rather than about hours. *Equality*, because `<=`
     * would mail the landing rung to every abandoned signup in the database on
     * the first scheduled run — about a pause that happened weeks earlier — and
     * because a missed sweep costing one warning is the failure this ladder is
     * allowed to have.
     *
     * ⚠️ **AT MOST ONE RUNG A DAY BY CONSTRUCTION**, which is R32's channel law
     * (*"never the same message on two channels the same day"*) holding without
     * a second mechanism: the count is a single number and each rung is claimed
     * by a single number. Where two rungs collide on a short trial the first in
     * authoring order wins and the other never fires, which is one message
     * rather than two.
     */
    public function due(Business $business): ?LifecycleRung
    {
        $subscription = $this->subscriptions->for($business);

        // Null for every account that is not on the no-card trial: one past
        // Checkout, one with no subscription row, one whose registration moment
        // we cannot read. No screen and no message may invent a date for any of
        // the three, and neither may this.
        $endsAt = $this->subscriptions->noCardTrialEndsAt($business, $subscription);

        if (! $endsAt instanceof Carbon) {
            return null;
        }

        $trialDays = $this->charges->trialDays();

        $remaining = (int) Carbon::now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false);

        foreach (LifecycleRung::trialRungs() as $rung) {
            if ($rung->daysRemainingAtSend($trialDays) === $remaining) {
                return $rung;
            }
        }

        return null;
    }

    /**
     * Send today's rung, and record which one it was.
     *
     * ⛔ **THE ORDER OF THE FIVE GATES IS THE DESIGN.** Address and
     * deliverability first, because both are meant to leave the rung still due
     * rather than to spend anything (`FirstWeekPath`'s gate, 1880).
     * **Composition next**, so a rung that refuses over an unset guarantee
     * leaves the row untouched. **The claim last**, because it is the only line
     * here that arbitrates anything and a claim spent on a run that was never
     * going to send buys nothing.
     *
     * ## ⛔ And the claim was spent on a send that could not fail — 10992
     *
     * ⚠️ **THE OLD SENTENCE, KEPT AND DATED** (4368): the paragraph above read
     * *"(`FirstWeekPath`'s gate, 1880 — `send()` queues and deliberately cannot
     * fail on us, 702)"*, and that parenthesis was the whole problem rather
     * than an aside. `PlatformMailer::send()` swallows every throwable and
     * returns `void` (9500), so `claimTrialRung()` — which writes
     * `subscriptions.trial_rung_sent` **before** the send and, unlike the
     * renewal claim, **never lapses** (9396) — spent this rung permanently for a
     * message that may never have reached the queue.
     *
     * ⛔ **`canDeliver()` DOES NOT COVER IT, WHICH IS THE PART THAT MATTERS.**
     * It answers about the transport, the from address and the sending domain
     * and about nothing else, so the two refusals it cannot see are an unstated
     * 24-hour ceiling and **a ceiling already reached** — and the second of
     * those is an ordinary Tuesday on a sandboxed SES account whose ceiling is
     * 200. **It clears at midnight**, so losing the rung for ever was the wrong
     * trade for a condition that fixes itself.
     *
     * ✅ **THE SEND IS NOW {@see PlatformMailer::deliverNow()}, AND A
     * PRE-TRANSPORT REFUSAL GIVES THE CLAIM BACK** through
     * {@see Subscriptions::releaseTrialRung()}. ⛔ **9396's ruling is untouched
     * and this is not a lapse**: the claim still never expires on a clock; what
     * is given back is a claim on an arm where the transport is provably never
     * contacted, because every `MailNotDeliverable` factory is thrown above the
     * send inside `deliverNow()`. ⚠️ **Anything else that throws — a
     * `TransportException` from SMTP, which can accept a message and then fail
     * on the response — keeps 9396's trade and loses the rung**, because that is
     * the case where *"sent twice"* and *"not sent"* genuinely cannot be told
     * apart.
     *
     * ⛔ **AND IT RETHROWS EITHER WAY**, so the sweep counts it and exits
     * non-zero. `OperatorAlertKind::PlatformMailUndeliverable` is raised only
     * inside `App\Jobs\DeliverPlatformMail::failed()` and this method no longer
     * reaches that job, so `OperatorAlertKind::ScheduledRunFailed` through
     * `App\Services\Ops\ScheduledRunMeter` is what an operator hears — and it
     * is that meter's `backgroundFinished()` arm, because this entry takes
     * `runInBackground()`.
     *
     * @return ?LifecycleRung the rung that went out, or null
     *
     * @throws MailNotDeliverable when the transport refuses the message
     */
    public function remind(Business $business): ?LifecycleRung
    {
        $rung = $this->due($business);

        if ($rung === null) {
            return null;
        }

        $owner = $business->owner;
        $address = $owner?->email;

        if (! is_string($address) || $address === '') {
            return null;
        }

        if (! $this->mailer->canDeliver()) {
            return null;
        }

        $subscription = $this->subscriptions->for($business);
        $endsAt = $this->subscriptions->noCardTrialEndsAt($business, $subscription);

        if (! $endsAt instanceof Carbon) {
            // `due()` has just read the same clock through the same method, so
            // this is unreachable rather than defensive — asserted because the
            // alternative is a formatted date built from null.
            return null;
        }

        $endsOn = $endsAt->translatedFormat('j F Y');
        $cardUrl = route('billing.index');

        try {
            $composed = $this->ladder->email(
                $rung,
                (string) $business->name,
                // Not nullsafe: `is_string($address)` above narrows $owner to
                // non-null, because the address was read from it.
                $owner->name,
                $cardUrl,
                $endsOn,
            );
        } catch (MessageCannotBeComposed $refusal) {
            // ⛔ CAUGHT RATHER THAN RAISED, AND THE SWEEP IS WHY. This runs over
            // every tenant on the platform; an uncaught refusal on one business
            // would end the walk and every account after it in the chunk would
            // hear nothing. ⚠️ The message names the rung and the slot and
            // carries no customer content — see `MessageCannotBeComposed`.
            Log::warning('a trial ladder rung could not be composed', [
                'business_id' => $business->id,
                'rung' => $rung->value,
                'reason' => $refusal->getMessage(),
            ]);

            return null;
        }

        // ⛔ THE CLAIM, AND IT IS THE ONLY THING IN THIS METHOD THAT ARBITRATES
        // ANYTHING (6969, 7100). Everything above is a read, and a read cannot
        // stop a second reader: two sweeps inside this window both see "nobody
        // has sent this rung" and both send. `withoutOverlapping` in
        // `routes/console.php` is a mutex in one process with an expiry, and a
        // person typing the command by hand takes no lock at all.
        if (! $this->subscriptions->claimTrialRung($business, $rung)) {
            return null;
        }

        try {
            // ⛔ `deliverNow()` RATHER THAN `send()`, AND THE CLAIM ABOVE IS WHY
            // — 10992. `send()` swallows and returns `void`, so a message never
            // handed to the queue left `trial_rung_sent` naming this rung for
            // ever; the claim does not lapse, on purpose (9396).
            $this->mailer->deliverNow($address, new TrialReminder(
                subject: $composed['subject'],
                guarantee: $composed['insert'],
                endsOn: $endsOn,
                // The landing rung fires the day *after* the clock ran out, so it is
                // the one rung whose pause has already happened. Reading the clock
                // rather than the rung would answer differently either side of the
                // hour the trial ends on.
                hasPaused: $rung === LifecycleRung::TrialEnded,
                cardUrl: $cardUrl,
            ));
        } catch (MailNotDeliverable $refusal) {
            // ⛔ **THIS TYPE AND NO OTHER, AND THE NARROWNESS IS THE ARGUMENT.**
            // Every `MailNotDeliverable` factory is thrown above
            // `Notifications::route(...)->notifyNow(...)` inside `deliverNow()`
            // — the transport check, the from address, the sending domain, the
            // ceiling, the headroom, the CAN-SPAM class and the footer — so on
            // this arm the transport was never contacted and the rung is
            // genuinely unsent. A `Symfony\…\TransportException` is the
            // opposite: SMTP can accept a message and then fail on the
            // response, so it is deliberately not caught and keeps 9396's
            // trade.
            //
            // ⚠️ `$subscription->trial_rung_sent` was read before the claim, so
            // it is the value to put back. Nulling the column instead would
            // make every earlier rung claimable again.
            $this->subscriptions->releaseTrialRung($business, $rung, $subscription?->trial_rung_sent);

            // ⚠️ **RETHROWN AFTER THE RELEASE, NEVER SWALLOWED.** The rung is
            // safe; the deployment is not, and the sweep's non-zero exit is the
            // only thing that tells anybody.
            throw $refusal;
        }

        return $rung;
    }
}
