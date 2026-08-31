<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\VerifiesWebhookSenders;
use App\Enums\CreditClawbackOutcome;
use App\Enums\CreditReversalCause;
use App\Enums\CreditSettlement;
use App\Enums\GatewayEventOutcome;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\StripeEvent;
use App\Support\CredentialManifest;
use App\Support\Money;
use App\Support\PlatformCredentials;
use App\Support\Tenancy;
use App\Support\WebhookMaterial;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * The one place an inbound Stripe event is verified, deduplicated and applied.
 *
 * ⚠️ **WEBHOOKS ARE THE SOURCE OF TRUTH, NEVER THE POST-CHECKOUT REDIRECT** —
 * the `cashier-billing` skill states it and it decides the shape of everything
 * here. A customer who closes the tab mid-redirect is still subscribed; one who
 * reaches the success page may still have a failed payment. So nothing in this
 * application promotes a subscription out of `pending_checkout` except an event
 * that arrived here and verified.
 *
 * ## Verification uses the SDK, and it is the only thing that does (680)
 *
 * `Stripe\Webhook::constructEvent()` is HMAC-SHA256 over `timestamp.payload`, a
 * constant-time compare, and a five-minute tolerance against replay. It opens no
 * socket, so `Http::preventStrayRequests()` and `40` Part 8's outbound lint have
 * nothing to see — which is the entire objection decision 277 raised to SDKs,
 * and it does not apply. The alternative is hand-rolling the one part of this
 * integration whose bugs are completely silent, and Stripe's own documentation
 * treats the manual path as a fallback.
 *
 * ## Three refusals, and each fails in the safe direction
 *
 *   unverifiable   no secret, no signature, a bad signature, or a payload
 *                  outside the tolerance window → nothing is read and nothing is
 *                  written. ⚠️ An endpoint that accepts unverified billing
 *                  events is an endpoint anybody can post a paid subscription
 *                  to.
 *   already seen   `stripe_events.stripe_event_id` is unique, and the insert is
 *                  the claim. Stripe retries for three days and delivers
 *                  duplicates by documented design.
 *   unmodelled     a status this product has no meaning for. `unpaid`, `paused`
 *                  and `incomplete_expired` exist upstream and
 *                  {@see SubscriptionStatus} deliberately omits them, because
 *                  `CLAUDE.md` says plainly that nobody has decided what a
 *                  delinquent tenant loses. Rounding one off to the nearest case
 *                  would answer that question by accident.
 */
final class StripeWebhooks implements VerifiesWebhookSenders
{
    /**
     * The signing secret every Stripe event is judged with.
     *
     * ⚠️ Named once so the declaration below and the read in {@see self::verify()}
     * cannot drift onto two literals.
     */
    public const string CREDENTIAL = 'stripe_webhook_secret';

    /**
     * ⚠️ **THE ABSENCE OF THIS ONE LOOKS LIKE A DIFFERENT BUG ENTIRELY**, which
     * is why it is worth reading at rest: Checkout works perfectly, Stripe
     * retries for three days and gives up, and the visible symptom is
     * subscriptions that never leave `pending_checkout`.
     * {@see CredentialManifest} states it for the operator; nothing
     * said it before a customer had already paid.
     */
    public static function verifyingMaterial(): WebhookMaterial
    {
        return WebhookMaterial::credentials([self::CREDENTIAL]);
    }

    /**
     * The event types this endpoint acts on.
     *
     * ⚠️ **`invoice.*` IS NOT HERE ON PURPOSE.** A renewal, a payment failure
     * and a recovery all move `customer.subscription.updated` as well, carrying
     * the whole subscription rather than one invoice's view of it — so acting on
     * both would be two writers racing to describe one state. Dunning is slice
     * C's, and it is the slice that needs an invoice's own detail.
     *
     * ## The two reversal events, chosen from the vendor's live catalogue (2026-08-20)
     *
     * ⛔ **`charge.refunded` AND NOT `refund.created`, AND ONE OF THEM MUST NOT
     * BE ADDED BESIDE THE OTHER.** Stripe raises both for one refund — its own
     * page says *"Occurs whenever a charge is refunded, including partial
     * refunds. Listen to `refund.created` for information about the refund"* —
     * so subscribing to both would be two events describing one reversal, which
     * is exactly the double-debit {@see CreditClawbacks}' second idempotency
     * layer exists to absorb and is not a reason to invite it. `charge.refunded`
     * wins because the **charge** object carries `amount_refunded`, which is
     * *cumulative*: a second partial refund states the running total, so the
     * arithmetic is right across partials without this handler holding any state.
     * A Refund object carries only its own `amount`.
     *
     * ⛔ **`charge.dispute.funds_withdrawn` AND NOT `charge.dispute.created`.**
     * `created` fires for an **inquiry** as well as a chargeback — the vendor's
     * dispute reference lists `warning_needs_response` and `warning_closed`
     * among the statuses, and an inquiry closed without becoming a formal dispute
     * takes no money at all. Clawing credit back on `created` would punish a
     * tenant for a question their bank asked. `funds_withdrawn` is documented as
     * *"Occurs when funds are removed from your account due to a dispute"*, which
     * is the money actually leaving.
     *
     * ⚠️ **`charge.dispute.funds_reinstated` IS DELIBERATELY ABSENT AND IS OWED**
     * (6394). Winning a dispute returns the money and should return the credit;
     * the cumulative model here cannot express *"this reversal was undone"*
     * without a per-reversal record, and inventing one that also had to interact
     * with partial refunds is a slice of its own. A support `Adjust` restores it
     * today, and `CreditGrants` is SMS-only, so that path does not reach an email
     * or AI top-up.
     */
    private const array HANDLED = [
        'checkout.session.completed',
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'charge.refunded',
        'charge.dispute.funds_withdrawn',
    ];

    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly CreditPurchases $purchases = new CreditPurchases,
        private readonly CreditClawbacks $clawbacks = new CreditClawbacks,
    ) {}

    /**
     * Verify a raw request body, or fail.
     *
     * Takes the **raw** body rather than a decoded array, and that is not a
     * style choice: the signature covers the exact bytes Stripe sent, so any
     * re-encoding — a framework's JSON round trip, a normalised float, a
     * reordered key — invalidates a signature that was perfectly good.
     *
     * @return array<string, mixed>
     *
     * @throws SignatureVerificationException
     */
    public function verify(string $payload, ?string $signature): array
    {
        // Missing credential and missing header are the same refusal, and the
        // first one is worth naming: PlatformCredentials::get() throws for an
        // absent key, so an unconfigured deployment refuses every event rather
        // than accepting them all. That is the correct direction and it is also
        // the one that looks like a Checkout bug — see CredentialManifest.
        $event = Webhook::constructEvent(
            $payload,
            $signature ?? '',
            PlatformCredentials::get(self::CREDENTIAL),
        );

        /** @var array<string, mixed> $array */
        $array = $event->toArray();

        return $array;
    }

    /**
     * Apply a verified event exactly once. Null means it had already been done.
     *
     * ⚠️ **THE CLAIM AND THE WORK COMMIT TOGETHER, OR NEITHER DOES** (decision
     * 693), and that is decisions 351/356 read across into a different vendor. There the
     * idempotency claim was taken before the work and never released on the
     * paths that produced no verdict, so a review was permanently
     * un-analysable, silently. Here the same shape would be worse: a handler
     * that throws after claiming would leave a row saying "seen", every one of
     * Stripe's three days of retries would be refused as a duplicate, and a paid
     * subscription would sit at `pending_checkout` forever with the endpoint
     * answering 200 the whole time. Wrapping both in one transaction means a
     * throw rolls the claim back and the next retry genuinely retries.
     *
     * ⚠️ **THE CLAIM IS AN INSERT, NOT A SELECT** — decision 350's lesson, that
     * a check-then-insert holds only sequentially. And it is `insertOrIgnore`
     * rather than a caught unique violation, because a failed statement aborts
     * the whole Postgres transaction (decision 383's `25P02`): catching the
     * exception and carrying on inside the same transaction would leave every
     * later statement failing for a reason that names nothing.
     *
     * @param  array<string, mixed>  $event
     */
    public function process(array $event): ?GatewayEventOutcome
    {
        $id = $this->stringOrNull($event['id'] ?? null);
        $type = $this->stringOrNull($event['type'] ?? null);

        if ($id === null || $type === null) {
            // Verified by signature and still shapeless. Not a thing to guess
            // at: without an id there is nothing to deduplicate on, so accepting
            // it would mean acting on the same event as often as it is sent.
            throw new UnexpectedValueException('A verified Stripe event carried no id or no type.');
        }

        return DB::transaction(function () use ($id, $type, $event): ?GatewayEventOutcome {
            $claimed = StripeEvent::query()->insertOrIgnore([
                'stripe_event_id' => $id,
                'type' => $type,

                // A placeholder, overwritten below in this same transaction.
                // Nothing ever reads this value: either the transaction commits
                // with the real outcome, or it rolls back and the row is not
                // there to be read.
                'outcome' => GatewayEventOutcome::Ignored->value,
                'received_at' => Carbon::now(),
            ]) === 1;

            if (! $claimed) {
                return null;
            }

            $outcome = in_array($type, self::HANDLED, true)
                ? $this->apply($type, $event)
                : GatewayEventOutcome::Ignored;

            $this->record($id, $outcome);

            return $outcome;
        });
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function apply(string $type, array $event): GatewayEventOutcome
    {
        /** @var array<string, mixed> $object */
        $object = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];

        if ($type === 'charge.refunded') {
            return $this->clawBackRefund($object);
        }

        if ($type === 'charge.dispute.funds_withdrawn') {
            return $this->clawBackDispute($object);
        }

        if ($type === 'checkout.session.completed') {
            /*
             * ⚠️ ONE EVENT TYPE, TWO PRODUCTS, AND `mode` IS WHAT TELLS THEM
             * APART (the funder, 3102). A subscription Checkout and a credit
             * top-up both complete through this event; the first writes the
             * customer index, the second moves a balance. Reading them as one
             * thing would either link a customer on a top-up — harmless — or,
             * far worse, run a settlement against a subscription payment, which
             * would be a credit nobody bought.
             *
             * ⚠️ AN UNKNOWN `mode` FALLS THROUGH TO NEITHER. `setup` sessions
             * exist and this application opens none; treating one as a top-up
             * because it is "not a subscription" is the permissive default that
             * a `match` with no arm is written to refuse.
             */
            return match ($this->stringOrNull($object['mode'] ?? null)) {
                'subscription' => $this->linkFromSession($object),
                'payment' => $this->settleTopUp($object),
                default => GatewayEventOutcome::Ignored,
            };
        }

        return $this->applySubscription($object, $this->observedAt($event));
    }

    /**
     * `checkout.session.completed` in `payment` mode — a credit top-up.
     *
     * ⛔ **THIS IS WHERE THE CREDIT IS WRITTEN, AND NOT IN THE CALL THAT OPENED
     * THE SESSION** (2056). The tenant paid on Stripe's own page, so this event is
     * the first thing this application hears about it at all.
     *
     * ⚠️ **`payment_status` IS CHECKED AND `status` IS NOT ENOUGH.** A completed
     * session is not a paid one: Stripe's own object carries `payment_status` as
     * `paid`, `unpaid` or `no_payment_required`, and a delayed-notification method
     * completes `unpaid` and settles later. {@see CreditTopUps} pins
     * `payment_method_types` to `card` so that path cannot arise — and this check
     * is what makes the pinning safe rather than merely intended, because an
     * account-level change could reintroduce it without a deploy of ours.
     *
     * ⚠️ **THE AMOUNT COMES FROM THE EVENT AND IS NEVER TRUSTED TO DECIDE WHAT TO
     * CREDIT.** It is handed to {@see CreditPurchases::settle()} to be *compared*
     * against the SKU price recorded when the purchase was opened. A handler that
     * credited units from a notification's own figures would be a free-credit hole
     * reachable by anybody who could forge one.
     *
     * @param  array<string, mixed>  $session
     */
    private function settleTopUp(array $session): GatewayEventOutcome
    {
        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $reference = $this->stringOrNull($metadata['credit_purchase'] ?? null);

        // A payment-mode session of ours always carries the reference. One
        // without it belongs to something else on the same account, which is
        // `Unlinked`'s ordinary reading rather than an error.
        $index = $this->purchases->referenceFor(null, $reference);

        if ($index === null || $reference === null) {
            return GatewayEventOutcome::Unlinked;
        }

        if ($this->stringOrNull($session['payment_status'] ?? null) !== 'paid') {
            // Completed and not paid. Nothing is credited and nothing is failed
            // either — the purchase stays claimable, because an asynchronous
            // method may still succeed and would arrive on its own event.
            return GatewayEventOutcome::Unmodelled;
        }

        $paid = Money::of(
            is_int($session['amount_total'] ?? null) ? $session['amount_total'] : 0,
            strtoupper($this->stringOrNull($session['currency'] ?? null) ?? ''),
        );

        // The PaymentIntent id is the closest thing a Checkout Session has to a
        // transaction id, and it is what a refund is issued against.
        $transactionId = $this->stringOrNull($session['payment_intent'] ?? null)
            ?? $this->stringOrNull($session['id'] ?? null)
            ?? $reference;

        return Tenancy::actingAs(
            $index->business_id,
            fn (): GatewayEventOutcome => match ($this->purchases->settle($reference, $transactionId, $paid)) {
                CreditSettlement::Credited => GatewayEventOutcome::Applied,
                // ⚠️ A REPLAY IS A SUCCESS AND MUST BE ANSWERED 2xx. Stripe
                // retries for three days by documented design; treating the
                // second delivery as a failure asks for a third.
                CreditSettlement::AlreadySettled => GatewayEventOutcome::Superseded,
                CreditSettlement::Unknown => GatewayEventOutcome::Unlinked,
                // Money moved, nothing was credited, a person has to look. Not
                // retried, because the amount will be just as wrong next time.
                CreditSettlement::Mismatched => GatewayEventOutcome::Unmodelled,
            },
        );
    }

    /**
     * `charge.refunded` — the money went back, so the credit it bought does too.
     *
     * ⛔ **`payment_intent` IS THE ONLY HANDLE THIS EVENT SHARES WITH US, AND IT
     * WAS NOT ON THE INDEX UNTIL THIS SLICE** (6383). A top-up settles through
     * `metadata.credit_purchase` on the Checkout Session, and **a Charge does not
     * inherit a PaymentIntent's metadata** — the vendor's Charge reference
     * describes `metadata` as an ordinary map with no word about inheritance
     * (fetched 2026-08-20), so reading it here would resolve nothing on every
     * genuine refund, silently. {@see CreditPurchases::settle()} now writes the
     * PaymentIntent id onto the un-tenanted index, which is what makes this
     * lookup work at all.
     *
     * ⚠️ **`amount_refunded` IS CUMULATIVE AND `amount` IS THE CHARGE.** The
     * vendor defines the first as *"Amount in the smallest currency unit refunded
     * (can be less than the amount attribute on the charge if a partial refund
     * was issued)"*. Reading `amount` instead would claw back the whole purchase
     * on a partial refund, and it would look entirely correct in the common case
     * where the two are equal.
     *
     * @param  array<string, mixed>  $charge
     */
    private function clawBackRefund(array $charge): GatewayEventOutcome
    {
        return $this->clawBack(
            $this->stringOrNull($charge['payment_intent'] ?? null),
            $charge['amount_refunded'] ?? null,
            $this->stringOrNull($charge['currency'] ?? null),
            CreditReversalCause::Refunded,
            $this->stringOrNull($charge['id'] ?? null),
        );
    }

    /**
     * `charge.dispute.funds_withdrawn` — a chargeback took the money back.
     *
     * ⛔ **THE DISPUTE OBJECT'S `payment_intent` IS DOCUMENTED NULLABLE AND THE
     * SAMPLE IN THE VENDOR'S OWN REFERENCE SHOWS IT NULL** (fetched 2026-08-20).
     * It is populated for a charge created through a PaymentIntent, which is
     * every charge this application causes — but a null is a real possibility and
     * is answered with `Unlinked` and a warning naming the **charge** id, rather
     * than with a guess. ⚠️ **We cannot resolve through the charge id**: nothing
     * here has ever stored one, because a Checkout Session names its PaymentIntent
     * and not its charge. Decision 6395.
     *
     * ⚠️ **`amount` HERE IS THE DISPUTED AMOUNT, WHICH THE VENDOR SAYS CAN EXCEED
     * THE CHARGE** — *"usually because of currency fluctuation or because only
     * part of the order is disputed"*. {@see CreditClawbacks} caps the units at
     * what the purchase granted, so the high side cannot over-claw.
     *
     * @param  array<string, mixed>  $dispute
     */
    private function clawBackDispute(array $dispute): GatewayEventOutcome
    {
        $paymentIntent = $this->stringOrNull($dispute['payment_intent'] ?? null);

        if ($paymentIntent === null) {
            Log::warning('a stripe dispute withdrew funds and named no payment intent', [
                'stripe_charge_id' => $this->stringOrNull($dispute['charge'] ?? null),
                'stripe_dispute_id' => $this->stringOrNull($dispute['id'] ?? null),
            ]);

            return GatewayEventOutcome::Unlinked;
        }

        return $this->clawBack(
            $paymentIntent,
            $dispute['amount'] ?? null,
            $this->stringOrNull($dispute['currency'] ?? null),
            CreditReversalCause::ChargedBack,
            $this->stringOrNull($dispute['charge'] ?? null),
        );
    }

    /**
     * Resolve a reversal to a purchase and hand it to the unfunder.
     *
     * ⚠️ **STRIPE IS INTEGER MINOR UNITS ALREADY** — there is no decimal on this
     * gateway and nothing here converts one, which is the asymmetry
     * `AuthorizeNetWebhooks::authAmount()` exists to absorb on the other side.
     * A non-integer is refused rather than cast, because `(int) "45.00"` is 45.
     *
     * ⚠️ **AN UNRESOLVED REVERSAL IS LOGGED AND NOT MERELY FILED.** `Unlinked` is
     * ordinary on a settlement — one Stripe account serves whatever else it
     * serves — but here it means money went back and credit may not have, so a
     * sustained run of these is a real defect and the log is what makes it
     * visible before a balance report does.
     *
     * @param  string|null  $subjectId  The vendor's own id for the thing being
     *                                  reversed, for the log line only.
     */
    private function clawBack(
        ?string $paymentIntent,
        mixed $reversedMinorUnits,
        ?string $currency,
        CreditReversalCause $cause,
        ?string $subjectId,
    ): GatewayEventOutcome {
        $index = $this->purchases->referenceFor($paymentIntent, null);

        if ($index === null) {
            Log::warning('a reversed stripe payment matched no credit purchase', [
                'stripe_payment_intent' => $paymentIntent,
                'stripe_subject_id' => $subjectId,
                'cause' => $cause->value,
            ]);

            return GatewayEventOutcome::Unlinked;
        }

        $reversed = is_int($reversedMinorUnits) && $currency !== null
            ? Money::of($reversedMinorUnits, strtoupper($currency))
            : null;

        return Tenancy::actingAs(
            $index->business_id,
            fn (): GatewayEventOutcome => $this->outcomeFor(
                $this->clawbacks->reverse($index->reference, $reversed, $cause, 'gateway:stripe'),
            ),
        );
    }

    /**
     * One clawback outcome as this endpoint's answer.
     *
     * ⚠️ **`Shortfall` IS `Applied` AND THAT IS NOT A SHRUG.** The handler did
     * everything it could — it took what was there and recorded what was not.
     * Answering anything else would ask Stripe to redeliver for three days, and
     * the second delivery would find the same empty balance. The record that
     * money was lost is the audit entry and the warning
     * {@see CreditClawbacks::reverse()} writes, not this enum.
     *
     * ⚠️ **`NotCredited` IS `Unmodelled` BECAUSE IT NEEDS A PERSON**, not because
     * it is a failure: a payment reversed before its own settlement notification
     * arrived leaves a purchase that could still credit against money already
     * returned (6396).
     */
    private function outcomeFor(CreditClawbackOutcome $outcome): GatewayEventOutcome
    {
        return match ($outcome) {
            CreditClawbackOutcome::Clawed,
            CreditClawbackOutcome::Shortfall => GatewayEventOutcome::Applied,
            // A replay, or a second event describing one reversal. A success.
            CreditClawbackOutcome::Nothing => GatewayEventOutcome::Superseded,
            CreditClawbackOutcome::Unknown => GatewayEventOutcome::Unlinked,
            CreditClawbackOutcome::NotCredited,
            CreditClawbackOutcome::Unreadable => GatewayEventOutcome::Unmodelled,
        };
    }

    /**
     * `checkout.session.completed` — the customer index, and nothing else.
     *
     * ⚠️ **IT DELIBERATELY DOES NOT WRITE A SUBSCRIPTION STATE.** The session
     * object names its subscription by id and does not carry it, so writing one
     * from here would mean either a second outbound call or inventing a status.
     * `customer.subscription.created` carries the object in full and arrives for
     * the same Checkout — and because it also carries `metadata.business_id`, it
     * does not depend on this handler having run first, which matters because
     * Stripe does not guarantee the order.
     *
     * @param  array<string, mixed>  $session
     */
    private function linkFromSession(array $session): GatewayEventOutcome
    {
        $customerId = $this->stringOrNull($session['customer'] ?? null);
        $reference = $this->stringOrNull($session['client_reference_id'] ?? null);

        if ($customerId === null || $reference === null || ! ctype_digit($reference)) {
            return GatewayEventOutcome::Unlinked;
        }

        $businessId = (int) $reference;

        return Tenancy::actingAs($businessId, function () use ($businessId, $customerId): GatewayEventOutcome {
            $business = Business::query()->find($businessId);

            if (! $business instanceof Business) {
                return GatewayEventOutcome::Unlinked;
            }

            $this->subscriptions->linkStripeCustomer($business, $customerId);

            return GatewayEventOutcome::Applied;
        });
    }

    /**
     * `customer.subscription.*` — the projection.
     *
     * @param  array<string, mixed>  $subscription
     */
    private function applySubscription(array $subscription, Carbon $observedAt): GatewayEventOutcome
    {
        $subscriptionId = $this->stringOrNull($subscription['id'] ?? null);
        $customerId = $this->stringOrNull($subscription['customer'] ?? null);
        $rawStatus = $this->stringOrNull($subscription['status'] ?? null);

        if ($subscriptionId === null || $customerId === null || $rawStatus === null) {
            return GatewayEventOutcome::Unlinked;
        }

        $status = SubscriptionStatus::tryFrom($rawStatus);

        // ⚠️ REFUSED RATHER THAN ROUNDED, and answered 2xx rather than retried.
        // A status we do not model will not become one Stripe redelivers
        // differently, so retrying is three days of noise; what is needed is a
        // person. The row in `stripe_events` is that record.
        if (! $status instanceof SubscriptionStatus || $status === SubscriptionStatus::PendingCheckout) {
            Log::warning('stripe subscription in an unmodelled state', [
                'stripe_status' => $rawStatus,
                'stripe_subscription_id' => $subscriptionId,
            ]);

            return GatewayEventOutcome::Unmodelled;
        }

        $businessId = $this->businessIdFor($subscription, $customerId);

        if ($businessId === null) {
            return GatewayEventOutcome::Unlinked;
        }

        return Tenancy::actingAs($businessId, function () use (
            $businessId,
            $subscription,
            $subscriptionId,
            $customerId,
            $status,
            $observedAt,
        ): GatewayEventOutcome {
            $business = Business::query()->find($businessId);

            if (! $business instanceof Business) {
                return GatewayEventOutcome::Unlinked;
            }

            $applied = $this->subscriptions->applyStripeSubscription($business, new StripeSubscriptionState(
                subscriptionId: $subscriptionId,
                customerId: $customerId,
                status: $status,
                trialEndsAt: $this->timestamp($subscription['trial_end'] ?? null),
                currentPeriodEnd: $this->currentPeriodEnd($subscription),
                /*
                 * ⚠️ THREE FIELDS AND THE ORDER IS THE WHOLE OF IT (2980–2999).
                 *
                 * `ended_at`   it is already over. Nothing else can be righter.
                 * `cancel_at`  it is scheduled to end, and this is the field a
                 *              `cancel_at_period_end` cancellation sets. **It
                 *              was missing until self-serve cancellation
                 *              existed**, and without it the row for somebody
                 *              who had just cancelled read `ends_at` =
                 *              `canceled_at` = *now* while `status` was still
                 *              `active` — a subscription that says it ended
                 *              today and is entitled, which is neither state.
                 * `canceled_at` the fallback, and last on purpose: Stripe sets
                 *              it at the moment the cancellation is *requested*,
                 *              not at the moment service stops.
                 */
                endsAt: $this->timestamp($subscription['ended_at'] ?? null)
                    ?? $this->timestamp($subscription['cancel_at'] ?? null)
                    ?? $this->timestamp($subscription['canceled_at'] ?? null),
                observedAt: $observedAt,
            ));

            return $applied ? GatewayEventOutcome::Applied : GatewayEventOutcome::Superseded;
        });
    }

    /**
     * Which business this subscription belongs to.
     *
     * Metadata first, index second, and the order is the interesting part:
     * `subscription_data[metadata][business_id]` is set when the Checkout Session
     * is opened, so it is present on the very first event for a subscription —
     * including one that arrives *before* `checkout.session.completed` has
     * written the index. The index is what answers every later event, and every
     * event for a customer created some other way.
     *
     * @param  array<string, mixed>  $subscription
     */
    private function businessIdFor(array $subscription, string $customerId): ?int
    {
        $metadata = is_array($subscription['metadata'] ?? null) ? $subscription['metadata'] : [];
        $fromMetadata = $this->stringOrNull($metadata['business_id'] ?? null);

        if ($fromMetadata !== null && ctype_digit($fromMetadata)) {
            return (int) $fromMetadata;
        }

        return $this->subscriptions->businessIdForStripeCustomer($customerId);
    }

    /**
     * The end of the period currently running.
     *
     * ⚠️ **READ FROM THE SUBSCRIPTION ITEM FIRST, AND THE TOP LEVEL IS THE
     * FALLBACK** (decision 684). At the version `stripe/stripe-php` pins,
     * `current_period_end` lives on `items.data[]` and the Subscription object
     * has no such field — so the line a from-memory implementation writes
     * returns null with no error, forever, on the column a renewal date is read
     * from. Both are read because **this shape is not ours to pin**: the version
     * of an inbound event is fixed by the account's or the endpoint's API
     * version setting, which no code here can set, so an account still on an
     * older version delivers the old shape to the same endpoint.
     *
     * @param  array<string, mixed>  $subscription
     */
    private function currentPeriodEnd(array $subscription): ?Carbon
    {
        $items = is_array($subscription['items']['data'] ?? null) ? $subscription['items']['data'] : [];
        $first = is_array($items[0] ?? null) ? $items[0] : [];

        return $this->timestamp($first['current_period_end'] ?? null)
            ?? $this->timestamp($subscription['current_period_end'] ?? null);
    }

    /**
     * The event's own `created` time — the watermark of decision 692.
     *
     * Falls back to now when absent. That accepts the event rather than
     * discarding it, which is the right direction: a missing timestamp makes
     * ordering unknowable, and refusing every such event would mean refusing
     * every event if Stripe ever changed the field's name.
     *
     * @param  array<string, mixed>  $event
     */
    private function observedAt(array $event): Carbon
    {
        return $this->timestamp($event['created'] ?? null) ?? Carbon::now();
    }

    private function timestamp(mixed $value): ?Carbon
    {
        return is_int($value) ? Carbon::createFromTimestamp($value) : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Write what actually happened onto the claim row.
     *
     * Loaded and saved as a model rather than updated through the builder, so
     * that {@see StripeEvent}'s append-only guard is genuinely on this path. A
     * builder update would bypass it, and a guard the only writer routes around
     * is decision 314–316's "a protection layer asserted before it is true".
     */
    private function record(string $eventId, GatewayEventOutcome $outcome): void
    {
        $row = StripeEvent::query()->where('stripe_event_id', $eventId)->first();

        if ($row instanceof StripeEvent) {
            $row->outcome = $outcome;
            $row->save();
        }
    }
}
