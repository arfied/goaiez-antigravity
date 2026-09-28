<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Console\Commands\SendRenewalReminders;
use App\Enums\BillingTerm;
use App\Enums\PaymentGateway;
use App\Enums\SubscriptionStatus;
use App\Exceptions\MailNotDeliverable;
use App\Models\Business;
use App\Models\Subscription;
use App\Notifications\RenewalReminder;
use App\Services\Config\DefaultsRegistry;
use App\Services\Mail\PlatformMailer;
use App\Support\PlanPricing;
use Illuminate\Support\Carbon;

/**
 * The notice before a year renews itself (2980–2999).
 *
 * ⛔ **THE COLUMN AND THE WRITER ARRIVED WITH NO SENDER, WHICH IS `CLAUDE.md`'s
 * MOST-REPEATED FAILURE.** `subscriptions.renewal_reminded_for` and
 * `Subscriptions::recordRenewalReminder()` were both written before this class
 * existed and **nothing in `app/` called either**. A gate on a counter nothing
 * increments reads as a live control and is a decoration — 2496–2499's shape.
 * This class and {@see SendRenewalReminders} are the
 * writer, and `routes/console.php` is what makes them reachable from production
 * rather than from a test.
 *
 * ## Who gets one, and why the population is this narrow
 *
 * California's Automatic Renewal Law requires a separate reminder for a
 * subscription whose term is **a year or longer** and which renews by itself,
 * 15–45 days before the renewal. It is not a duty on every subscription and
 * inventing one would be its own kind of wrong: a monthly plan that emailed
 * every tenant every month about a $179.99 charge they chose is noise, and
 * `20` §9's one-email discipline is the standing rule against exactly that.
 *
 * So the population is: **annual term, bought outright, still live, nobody has
 * asked to cancel.**
 *
 *   - **Monthly is excluded** because its term is 30 days (147) and the statute's
 *     trigger is a year.
 *   - ⛔ **An annual bought in instalments is excluded because it does not renew
 *     at all** (2749). Three ARB payments complete inside the first quarter and
 *     the vendor's schedule ends; there is nothing to give notice of, and a
 *     notice claiming otherwise would be the misrepresentation the statute
 *     exists to prevent.
 *   - **A tenant who has already asked to cancel is excluded**, because the
 *     notice's entire subject is a charge that is no longer coming.
 *
 * ## Why the send is checked before the record, and not after
 *
 * `Subscriptions::recordRenewalReminder()`'s docblock states the asymmetry:
 * writing first and failing to send leaves a tenant with **no notice and nothing
 * that will ever try again**, while sending first and failing to write leaves
 * them with two notices. One of those is a statutory failure; the other is an
 * annoyance.
 *
 * ## ⛔ AND THAT ORDERING BOUGHT NOTHING UNTIL 2026-08-28, BECAUSE THE SEND
 * COULD NOT FAIL — 10980
 *
 * ⛔ **THE PARAGRAPH ABOVE WEIGHS TWO OUTCOMES AND ONLY ONE OF THEM WAS
 * REACHABLE.** This class sent through `PlatformMailer::send()`, which is
 * `DeliverPlatformMail::dispatch()` inside a `catch (Throwable)` that logs and
 * returns `void` (9500) — so a message that was **never handed to the queue at
 * all** returned normally, `recordRenewalReminder()` ran on the next line, and
 * `renewal_reminded_for` was stamped with this term's date. {@see self::due()}
 * gates on date equality, so **that term's** notice was then suppressed for
 * ever: next year's date differs and the duty recurs, but the notice this
 * customer was owed never does. **The ordering was chosen to trade toward
 * duplicates and delivered exactly the arm this docblock calls a statutory
 * failure.**
 *
 * ⚠️ **THE OLD SENTENCE, KEPT AND DATED** (4368), because what has to change
 * is the reasoning rather than a word: *"`PlatformMailer::send()` queues and
 * cannot fail on us by design (702), so 'after the send' would otherwise mean
 * 'after a push into a mailer that may deliver nothing' — `FirstWeekPath`'s
 * exact problem (1880), and `canDeliver()` is its exact answer. It is **not a
 * delivery receipt** and this class does not treat it as one."* ⛔ **702 is an
 * argument about an *unauthenticated* path** — a login form that has to answer
 * identically for an address with an account and one without — and this is a
 * daily sweep over annual subscriptions with nobody typing anything. **The
 * swallow was inherited here as a property of the mailer rather than chosen as a
 * property of this caller.** ⚠️ **The last sentence stayed true throughout and
 * is the one that made the defect invisible**: `canDeliver()` is not a delivery
 * receipt, this class never treated it as one, and it was nevertheless the last
 * thing asked before a write asserting delivery.
 *
 * ✅ **THE SEND IS NOW {@see PlatformMailer::deliverNow()}, WHICH THROWS**, so
 * the write on the line after it runs only when the transport accepted the
 * message. That is `SendOwnerWeeklyDigests`' repair (10852) and
 * `OperatorAlerts::email()`'s before it, applied to the one site in this tree
 * where the lost message is a Civil Code §1637 obligation.
 * ⚠️ **`canDeliver()` STAYS IN FRONT OF IT AND THE TWO ARE NOT DUPLICATES.** It
 * answers *is this deployment's mail system configured at all* — a `.env` fact,
 * identical for every business in the sweep, so a refusal there is not a
 * per-business fault and must not be counted as one. `deliverNow()` answers *did
 * the transport take this message*, which covers an unstated 24-hour ceiling, a
 * ceiling already reached and a refused SMTP connection — none of which
 * `canDeliver()` structurally can see.
 *
 * ⚠️ **THE RETRY LADDER GIVEN UP IS REPLACED BY A BETTER ONE, AND THE CLAIM IS
 * WHAT MAKES THAT TRUE.** `DeliverPlatformMail`'s three attempts are gone; the
 * daily sweep re-runs against a window **sixteen days wide** (30 → 15), and
 * {@see app(\App\Services\Billing\Subscriptions::class)->renewalReminderClaimMinutes()} lapses after an hour — so
 * tomorrow's run reclaims the row and tries again, up to sixteen times, all
 * inside the lawful window. **The claim's deliberate lapse was written for a run
 * that died between claiming and dispatching (7100), and it is exactly the
 * mechanism a refused transport needs.**
 *
 * ⛔ **WHAT STOPS RINGING, STATED BECAUSE A CALLER MOVED OFF `send()` LEAVES A
 * BELL BEHIND IT.** `OperatorAlertKind::PlatformMailUndeliverable` is raised
 * **only** inside `DeliverPlatformMail::failed()`, and this class no longer
 * reaches that job at all. ✅ **What rings instead is
 * `OperatorAlertKind::ScheduledRunFailed`**, through
 * `App\Services\Ops\ScheduledRunMeter`: {@see SendRenewalReminders} catches the
 * throw per business, counts it and exits non-zero, and that meter's
 * `backgroundFinished()` arm is what reads the exit code of a
 * `runInBackground()` entry — which `ScheduledTaskFailed` never sees.
 * ⚠️ **Neither bell names a business and neither replaces the other**: the old
 * one fired once per mailer per 24 hours for any failed mail job on the
 * platform; this one fires for this sweep.
 *
 * ## ⛔ That ordering is kept, and it was never the thing that made the notice
 * singular (6969, 7100)
 *
 * The paragraph above weighs *"sent and not recorded"* against *"recorded and
 * not sent"* for **one** reader. It says nothing about two, and two was the
 * live defect: `due()`'s gate is a read, `recordRenewalReminder()` is a write,
 * and between them sat a send and no arbitration of any kind — no row lock, no
 * unique index (and none is available: `subscriptions.business_id` is itself
 * `unique()`, so a composite with `renewal_reminded_for` is trivially unique and
 * decides nothing), no idempotency claim on `DeliverPlatformMail`. The only
 * guard was `withoutOverlapping(360)` in `routes/console.php`, which is a mutex
 * in a process with an expiry, and which the schedule takes but a hand-typed
 * `php artisan billing:send-renewal-reminders` does not.
 *
 * {@see Subscriptions::claimRenewalReminder()} is the arbitration: one
 * conditional UPDATE that succeeds for the first reader and fails for the
 * second. ⚠️ **The claim lapses on purpose** — a permanent one would trade *"a
 * notice sent twice"* for *"a notice never sent"* on any run that died between
 * claiming and dispatching, and those two are not comparable. A duplicate is an
 * annoyance; a missing pre-renewal notice is a Civil Code §1637 failure with the
 * customer's remedy attached. **So the surviving failure here is still the
 * duplicate, and it is the one that was chosen rather than the one that was
 * overlooked.**
 *
 * ## ✅ The amount in the notice is the price this tenant agreed to, and the debt
 * this docblock recorded is paid (3443, 3444)
 *
 * The statute wants the **amount of the charge** in the notice, and until
 * 2026-08-14 there was no stored one to read: decision 689 kept the registry the
 * only place a price was written. This docblock said what that would cost —
 * *"the first annual price change breaks that, and nothing here would notice"* —
 * and named the fix: *"a stored charged-amount on the row… a writer on both
 * subscribe paths and an answer for rows that predate it."* The owner then ruled
 * that an existing customer keeps their price for ever (3443), which turned owed
 * work into required work. All three parts exist: `subscriptions.price_cents`
 * and its three companions, {@see Subscriptions} writing them on both checkout
 * paths, and {@see PlanCharges::agreedPriceFor()} falling back to the registry
 * for a row that predates them.
 *
 * ⛔ **THIS CLASS MUST NEVER ASK `priceFor()` AGAIN**, and the difference is in
 * the argument rather than in the name: `priceFor()` takes a `PlanSelection` —
 * something somebody is about to buy — while `agreedPriceFor()` takes a
 * `Subscription`, which is something they already have. Everybody this class
 * emails is in the second group by definition.
 *
 * ⚠️ **The divergence is invisible today, which is why it is written down.** The
 * annual term landed 2026-08-12 (2680) and the annual figures were set
 * 2026-08-11 (2053, 2054), so no annual subscription predates the current price
 * and both reads return the same number for every tenant who exists. A test that
 * does not **move the registry price** therefore proves nothing about this —
 * `RenewalReminderTest` moves it.
 *
 * ⚠️ **The location arithmetic is the row's now rather than absent.** This class
 * used to do none, because every subscription it could see carried zero
 * additional locations and counting a tenant's `locations` rows *now* would
 * invent a figure nobody was charged. That reasoning is unchanged and is exactly
 * why `additional_locations` is stored: the count comes from what was sold, so
 * `agreedPriceFor()` can add the locations on without this class guessing.
 */
final class RenewalReminders
{
    /**
     * The earliest and latest the notice may go out, in days before renewal.
     *
     * ⚠️ **A SUBRANGE OF THE STATUTE'S 15–45, DELIBERATELY.** The sweep is
     * daily, so a window of exactly 15–45 would be honoured by a single run at
     * either edge and lost entirely by a scheduler that skipped it. Opening at
     * 30 leaves **sixteen daily chances** to land inside the lawful window
     * before the 15-day floor, so a fortnight of missed cron runs still produces
     * a compliant notice rather than none.
     */
    public const int OPENS_DAYS_BEFORE = 30;

    public const int CLOSES_DAYS_BEFORE = 15;

    public function __construct(
        private readonly DefaultsRegistry $defaults,
        private readonly Subscriptions $subscriptions,
        private readonly PlatformMailer $mailer,
        private readonly PlanCharges $charges = new PlanCharges,
    ) {}

    /**
     * The renewal this tenant is owed a notice about, or null.
     *
     * Reads our own row only. ⚠️ **It never asks a gateway**: this runs for
     * every tenant on a daily sweep, and a vendor round trip per business per
     * day is a cost with no answer in it — the renewal date is already projected
     * onto our row by the webhook that is the source of truth (2056).
     */
    public function dueFor(Business $business): ?Carbon
    {
        return $this->due($business)['renewsOn'] ?? null;
    }

    /**
     * The subscription owed a notice and the date it renews, or null.
     *
     * ⚠️ **THE ROW COMES BACK WITH THE DATE BECAUSE THE NOTICE NEEDS BOTH SINCE
     * 3443.** `remind()` used to need only the date, and could quote a price from
     * the registry without ever holding the subscription. It cannot any more — the
     * amount is now a fact about the row — and re-reading the row in `remind()`
     * would be two queries answering one question, with a window between them in
     * which the two halves of one notice come from different states.
     *
     * @return ?array{subscription: Subscription, renewsOn: Carbon}
     */
    private function due(Business $business): ?array
    {
        $subscription = $this->subscriptions->for($business);

        if (! $subscription instanceof Subscription) {
            return null;
        }

        if (! $this->renewsAutomatically($subscription)) {
            return null;
        }

        // Somebody who has asked to cancel is not owed notice of a charge that
        // is not coming. ⚠️ The column records **our own act** and never the
        // subscription's state (2056), which is exactly why it is readable here:
        // the vendor's confirmation may not have arrived yet, and the person
        // still asked.
        if ($subscription->cancellation_requested_at !== null) {
            return null;
        }

        $renewsOn = $this->renewalDate($subscription);

        if (! $renewsOn instanceof Carbon) {
            return null;
        }

        // ⚠️ THE GATE IS DATE EQUALITY, WHICH IS WHY THE COLUMN IS A DATE. A
        // boolean would silence next year's notice for ever and a send timestamp
        // would need arithmetic against a moving anniversary — see the column's
        // own migration.
        //
        // ⛔ AND THIS READ IS NOT WHAT MAKES THE NOTICE SINGULAR — 6969, 7100.
        // It is a `SELECT`, so whatever it answers is already history by the
        // time `remind()` acts on it, and two readers inside the window both get
        // "nobody has sent this". It stays because it is the cheap answer for
        // the overwhelmingly common case; what actually arbitrates is
        // `Subscriptions::claimRenewalReminder()` in `remind()`. Deleting that
        // call leaves this line looking exactly as protective as it does now.
        if ($subscription->renewal_reminded_for?->isSameDay($renewsOn) === true) {
            return null;
        }

        $days = Carbon::now()->startOfDay()->diffInDays($renewsOn->copy()->startOfDay(), false);

        if ($days > $this->opensDaysBefore() || $days < $this->closesDaysBefore()) {
            return null;
        }

        return ['subscription' => $subscription, 'renewsOn' => $renewsOn];
    }

    /**
     * Send the notice and record which renewal it was for.
     *
     * ⛔ **IT THROWS WHEN THE TRANSPORT REFUSES, AND THE CALLER IS REQUIRED TO
     * CATCH IT** — 10980, {@see SendRenewalReminders::remindForOwner()}. The
     * throw is the whole repair: it is what stops
     * `Subscriptions::recordRenewalReminder()` on the next line from stamping
     * `renewal_reminded_for` for a notice nobody accepted, and stamping it is
     * what suppresses that term's notice permanently. **A caller that swallowed
     * this would restore the defect exactly**, which is why the sweep counts it
     * and exits non-zero rather than logging and continuing quietly.
     *
     * ⚠️ **A REFUSAL LEAVES THE NOTICE STILL DUE**, and none of the four
     * earlier returns spends anything either (2995): no subscription, no
     * address, an undeliverable deployment and a lost claim all leave
     * `renewal_reminded_for` untouched, so the next daily run inside the
     * sixteen-day window tries again.
     *
     * @return bool whether a notice actually went out
     *
     * @throws MailNotDeliverable when the transport refuses the message
     */
    public function remind(Business $business): bool
    {
        $due = $this->due($business);

        if ($due === null) {
            return false;
        }

        $renewsOn = $due['renewsOn'];

        $address = $business->owner?->email;

        if (! is_string($address) || $address === '') {
            // An owner with no address is not a failure of this sweep to report
            // on — nothing is recorded, so the day the address exists the notice
            // is still due.
            return false;
        }

        // ⚠️ ASKED BEFORE ANYTHING IS RECORDED, `FirstWeekPath`'s gate (1880) —
        // and it is no longer the only thing between a broken mailer and a
        // stamped column (10980). It answers a `.env` question that is identical
        // for every business in this sweep, so refusing here costs no claim, no
        // record and no failure count: this is a deployment that cannot send at
        // all, not a business that could not be reached. The per-message
        // question is `deliverNow()`'s below, and it throws.
        if (! $this->mailer->canDeliver()) {
            return false;
        }

        // ⛔ THE CLAIM, AND IT IS THE ONLY THING IN THIS METHOD THAT ARBITRATES
        // ANYTHING (6969, 7100). Everything above is a read, and a read cannot
        // stop a second reader: until this line the only guard against two
        // sweeps sending the same statutory notice was `withoutOverlapping(360)`
        // in `routes/console.php` — a mutex, in one process, with an expiry, and
        // taken only by the *scheduled* invocation. A person typing
        // `php artisan billing:send-renewal-reminders` takes no lock at all.
        //
        // ⚠️ AFTER `canDeliver()` AND AFTER THE ADDRESS, DELIBERATELY. A claim
        // spent on a run that was never going to send would hold the row for an
        // hour and buy nothing — and both of those refusals are meant to leave
        // the notice still due (2995).
        if (! $this->subscriptions->claimRenewalReminder($business, $renewsOn)) {
            return false;
        }

        // ⛔ `deliverNow()` RATHER THAN `send()`, AND THE LINE BELOW IT IS WHY
        // — 10980. `send()` swallows every throwable and returns `void` (9500),
        // so a message never handed to the queue was indistinguishable from one
        // sent, and `recordRenewalReminder()` then suppressed **this term's**
        // statutory notice for ever. This throws; the record is not reached; the
        // claim lapses in an hour and tomorrow's sweep tries again. See this
        // class's own docblock for what stops ringing and what starts.
        $this->mailer->deliverNow($address, new RenewalReminder(
            renewsOn: $renewsOn->toFormattedDayDateString(),

            // ⛔ `agreedPriceFor($subscription)`, NEVER `priceFor(PlanSelection::annual())`
            // — 3443, and the line this replaces is the defect 3444 names. The
            // old call read today's registry figure into a notice sent to
            // somebody who bought at whatever the figure was on their signup day,
            // so the first price change would have emailed every existing
            // customer an amount the gateway is not going to charge them, with
            // both halves right on their own screen.
            price: PlanPricing::format($this->charges->agreedPriceFor($due['subscription'])),
            cancelUrl: route('account.plan'),
        ));

        // ⚠️ **AFTER THE SEND RETURNS, NEVER BEFORE IT**, which is what the
        // paragraph in this class's docblock was always asking for and what only
        // became true when the send acquired a way to fail.
        $this->subscriptions->recordRenewalReminder($business, $renewsOn);

        return true;
    }

    /**
     * Does this subscription renew by itself on a term of a year or more?
     *
     * ⚠️ **`instalment_payments` IS THE DISCRIMINATOR AND NOT `term`.** Both
     * annual shapes carry `BillingTerm::Annual`; only the one bought outright
     * renews (2749). Reading `term` alone would send a renewal notice to the one
     * population for whom the whole statement is false.
     */
    private function renewsAutomatically(Subscription $subscription): bool
    {
        if ($subscription->term !== BillingTerm::Annual) {
            return false;
        }

        if ($subscription->instalment_payments !== null) {
            return false;
        }

        if ($subscription->status === SubscriptionStatus::Canceled) {
            return false;
        }

        return $subscription->stripe_subscription_id !== null
            || $subscription->authorize_net_subscription_id !== null;
    }

    /**
     * When the year runs out, on whichever gateway holds it.
     *
     * ⚠️ **TWO COLUMNS BECAUSE THE TWO GATEWAYS PROJECT DIFFERENT THINGS.**
     * Stripe's `current_period_end` is the renewal moment and arrives on every
     * `customer.subscription.updated`; Authorize.Net publishes no such field, so
     * `annual_term_ends_on` — written from the schedule we sent (2138's rule:
     * store what we sent) — is the only date this application has for it.
     */
    private function renewalDate(Subscription $subscription): ?Carbon
    {
        if ($subscription->gateway === PaymentGateway::Stripe) {
            return $subscription->current_period_end;
        }

        return $subscription->annual_term_ends_on;
    }

    public function opensDaysBefore(): int
    {
        return $this->defaults->int('billing.renewal.opens_days_before');
    }

    public function closesDaysBefore(): int
    {
        return $this->defaults->int('billing.renewal.closes_days_before');
    }
}
