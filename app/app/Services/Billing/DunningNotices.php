<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\AutopilotActionType;
use App\Enums\DunningNoticeDelivery;
use App\Models\Business;
use App\Models\Subscription;
use App\Notifications\PaymentFailed;
use App\Notifications\PlanEndedForNonPayment;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Services\Mail\PlatformMailer;
use App\Support\PlanPricing;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Telling the tenant their payment failed, and that their plan has ended (6400).
 *
 * ⛔ **THE DEBT {@see Dunning} RECORDED AGAINST ITSELF AND 2590 MADE URGENT.**
 * Its docblock: *"a schedule of three silent attempts and then a suspension is a
 * tenant losing the product with no warning. The notification is owed and is not
 * built here."* 2590 then made the schedule actually run, so the three silent
 * attempts became silent **and happening**. This is the missing half.
 *
 * ## ⚠️ It describes the policy and does not choose any of it
 *
 * How many attempts there are, how far apart, and when suspension lands are all
 * {@see Dunning}'s and were settled at 2142/2143. Nothing here reads
 * `SCHEDULE_HOURS`, counts attempts, or decides anything about the clock: the
 * caller hands over a date it has already written to `dunning_attempts`, and
 * this class turns it into a sentence. **A second opinion about the schedule
 * living in the copy layer is how the email and the database come to disagree.**
 *
 * ## Three things happen per notice, and only one of them is the email
 *
 *   1. **The activity feed**, always — `29` §2 rule 42, every automated action.
 *      ⚠️ It is written whether or not mail goes out, and it is worded as *the
 *      event* (*"We could not take the payment for your plan"*) rather than as
 *      *the send* (*"Sent you a notice"*), because the second is a claim about
 *      delivery this application cannot make (`PlatformMailer::canDeliver()`'s
 *      own docblock: *"true here is not a delivery"*).
 *   2. **The email**, when there is an address and the transport is not knowably
 *      undeliverable.
 *   3. **The audit log**, always, carrying {@see DunningNoticeDelivery}.
 *      ⛔ **That value is the point of the audit entry rather than a detail of
 *      it** — the append-only record of *"we took the product away and could
 *      not tell them"* is exactly the fact a billing dispute turns on, and
 *      `dunning_attempts` cannot answer it: it records the schedule, not the
 *      telling.
 *
 * ## ⛔ That value was a boolean called `emailed` and it was true for messages
 * nobody had accepted — 10990
 *
 * ⛔ **`emailed` WAS SET IMMEDIATELY AFTER `PlatformMailer::send()`, WHICH
 * SWALLOWS EVERY THROWABLE AND RETURNS `void`** (9500). So a message never
 * handed to the queue at all — the `jobs` table unwritable, Redis refusing a
 * connection — was recorded as `emailed: true`, **in an append-only record that
 * cannot be corrected later.** That is `CLAUDE.md`'s 9371 exactly: *a column
 * that records a dispatch is not a column that records a delivery*, here
 * wearing a boolean rather than a timestamp, and pointing in the flattering
 * direction.
 *
 * ✅ **The repair is a value that is true when it is written**, which for this
 * path means three states rather than two: handed to the queue, no address,
 * transport unavailable. ⛔ **The key is `mail` and not `emailed`** — rows
 * written before this cannot be rewritten, and reusing the name would make one
 * key a boolean on old rows and a string on new ones in the one record a
 * dispute is argued from.
 *
 * ⛔ **AND THE SEND STAYS `send()` RATHER THAN MOVING TO `deliverNow()`, WHICH
 * IS THE OPPOSITE OF WHAT `RenewalReminders` AND `NotifyOwnerOfVoicemailJob`
 * DID IN THE SAME SLICE** (10991). Two reasons, and neither is latency. First,
 * `AuthorizeNetWebhooks::process()` wraps `Dunning::open()` in an outer
 * transaction — so a synchronous send would put a real email in front of a
 * customer from inside a transaction that may still roll back, where a queued
 * job's `jobs` row rolls back with it. **A rollback that un-sends is worth more
 * than a stronger word in the audit log.** Second, this notice keeps
 * `DeliverPlatformMail`'s retry ladder **and** the
 * `OperatorAlertKind::PlatformMailUndeliverable` bell, which the two callers
 * that moved gave up; the notice itself is better protected here than it would
 * be after the move, and only the *record* was wrong.
 *
 * ## ⚠️ The send is not deferred to commit, and the reason is the harness
 *
 * `Dunning` calls this **after** its own `DB::transaction()` returns — but
 * `AuthorizeNetWebhooks::process()` wraps `open()` in an outer transaction, so on
 * that one path the inner "commit" is a savepoint release and the mail is
 * dispatched before the webhook's transaction commits. `DB::afterCommit()` is the
 * textbook fix and **cannot be used here**: every test runs inside a transaction
 * that never commits (`RefreshesTenantDatabase`, and `CLAUDE.md` 352/397/565), so
 * the callback would never fire under test and the notification would be pinned
 * by nothing. The exposure is bounded and is written down rather than hidden: a
 * webhook that rolls back after `open()` leaves a tenant with a true email about
 * a failed payment and no schedule behind it, and the vendor's redelivery re-runs
 * both.
 */
final class DunningNotices
{
    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly PlatformMailer $mailer,
        private readonly PlanCharges $charges = new PlanCharges,
        private readonly ActivityService $activity = new ActivityService,
        private readonly AuditService $audit = new AuditService,
    ) {}

    /**
     * A payment did not go through and the plan is still running.
     *
     * @param  ?Carbon  $endsOn  when the plan ends if nothing changes — non-null
     *                           on the last notice before suspension and on no
     *                           other, which is {@see Dunning}'s call and not this
     *                           class's
     */
    public function paymentFailed(Business $business, ?Carbon $endsOn = null): void
    {
        // ⚠️ `OwnerActionNeeded` WITH ITS OWN TITLE, NOT `TenantSuspended`.
        // That case is `28` §9.5's suspension — done to a tenant for cause, and
        // its own docblock says talking to us is the only way out of it. This is
        // a bill, and reusing the compliance vocabulary for it would put "we
        // stopped your account" in the feed of somebody whose card expired.
        // `CreditPurchases` sets the precedent for a caller-supplied title.
        $this->activity->record(
            AutopilotActionType::OwnerActionNeeded,
            title: 'We could not take the payment for your plan',
        );

        $this->deliver(
            $business,
            new PaymentFailed(
                amount: $this->amountFor($business),
                endsOn: $endsOn?->toFormattedDayDateString(),
            ),
            'billing.dunning_payment_failure_notified',
            ['ends_on' => $endsOn?->toDateString()],
        );
    }

    /**
     * The schedule is exhausted and `Subscriptions::suspendForNonPayment()` has run.
     */
    public function planEnded(Business $business): void
    {
        $this->activity->record(
            AutopilotActionType::OwnerActionNeeded,
            title: 'Your plan has ended because we could not take the payment',
        );

        $this->deliver(
            $business,
            new PlanEndedForNonPayment,
            'billing.dunning_suspension_notified',
            [],
        );
    }

    /**
     * The amount to print, or nothing at all.
     *
     * ⛔ **`agreedPriceFor()`, WHICH READS THE ROW — NEVER A LIVE QUOTE** (3443).
     * `RenewalReminders` is the surface 3444 was found on and the argument is the
     * same one: everybody this class writes to has already bought, so the live
     * registry figure is a number the gateway is not going to charge them.
     *
     * ## ⚠️ A row that predates the agreed-price columns is quoted NOTHING, which
     * is stricter than `Livewire\Account\Plan`'s answer to the same arm
     *
     * That screen renders the amount and withholds only the *promise* that it is
     * the price they agreed to (4844), and that is right for a page whose whole
     * subject is what they pay from here on. **This is a claim about a charge
     * that has already been attempted**, and on the fallback arm the figure is
     * today's registry — so printing it would state that we tried to take an
     * amount we did not. There is no sentence that makes that true, so the
     * amount goes rather than a caveat arriving beside it. The copy is written in
     * two shapes for exactly this ({@see PaymentFailed::toMail()}), and the
     * message loses nothing it needs: what the reader has to do is unchanged.
     *
     * ⚠️ **THE POPULATION IS THE ONE 3533 BOUNDS** — rows written before
     * 2026-08-14 — and it is small rather than empty.
     */
    private function amountFor(Business $business): ?string
    {
        $subscription = $this->subscriptions->for($business);

        if (! $subscription instanceof Subscription || $subscription->price_cents === null) {
            return null;
        }

        return PlanPricing::format($this->charges->agreedPriceFor($subscription));
    }

    /**
     * Send it if we can, and record either way.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function deliver(
        Business $business,
        Notification $notification,
        string $action,
        array $metadata,
    ): void {
        $address = $business->owner?->email;

        // ⚠️ ASKED BEFORE ANYTHING CLAIMS A SEND, `RenewalReminders`' gate (1880).
        // It answers a `.env` question — transport, from address, decision 30's
        // domain rule — and is identical for every business on the install, so
        // it is a platform state rather than a fact about this tenant. ⛔ **AND
        // IT IS NOT A DELIVERY RECEIPT**, which is why the value written below
        // says `queued` on the arm this branch takes rather than `emailed`.
        if (! is_string($address) || $address === '') {
            $delivery = DunningNoticeDelivery::NoAddress;
        } elseif (! $this->mailer->canDeliver()) {
            $delivery = DunningNoticeDelivery::TransportUnavailable;
        } else {
            $this->mailer->send($address, $notification);

            // ⛔ **`Queued` AND NOT `Emailed`, AND THE WORD IS THE WHOLE REPAIR**
            // — 10990. `send()` swallows a dispatch failure and returns `void`,
            // so even this is generous: on a `jobs` table that cannot be written
            // no job ever existed and this line still runs. What it honestly
            // asserts is that this application tried and did not refuse. See
            // {@see DunningNoticeDelivery::Queued} for why the stronger claim
            // `deliverNow()` would allow is refused on this path.
            $delivery = DunningNoticeDelivery::Queued;
        }

        // ⛔ NO ADDRESS IN THE METADATA. The audit log is not pruned and the
        // owner's email is personal data — what happened to the mail is the fact
        // worth keeping and the address is already on `users`.
        $this->audit->record($action, 'system', $this->subscriptions->for($business), [
            ...$metadata,
            'mail' => $delivery->value,
        ]);
    }
}
