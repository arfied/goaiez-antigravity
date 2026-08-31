<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\AuthorizeNetSubscriptionStatus;
use App\Enums\BillingTerm;
use App\Enums\CancellationOutcome;
use App\Enums\ImpersonationCapability;
use App\Enums\PaymentGateway;
use App\Enums\SubscriptionStatus;
use App\Exceptions\AuthorizeNetRequestFailed;
use App\Exceptions\StripeRequestFailed;
use App\Models\Business;
use App\Models\Subscription;
use App\Services\AuditService;
use App\Services\Impersonation\Impersonation;
use Illuminate\Support\Carbon;

/**
 * The cancellation this application promised and did not have (2980–2999).
 *
 * ⛔ **`grep -rn "cancel" routes/*.php` RETURNED NOTHING.** Every cancellation
 * in this codebase arrived *inbound*: `AuthorizeNetWebhooks` projecting a
 * vendor-side end, `Dunning` exhausting its schedule into
 * `suspendForNonPayment()`. There was no route, no controller, no Stripe
 * billing-portal path, and nothing a tenant could press — while
 * `billing/authorize-net.blade.php` told every buyer "you can cancel any time".
 * California's Automatic Renewal Law exists to forbid exactly that gap, and a
 * promise on a checkout page that the application cannot perform is worse than
 * a missing feature: it is the representation the statute is about.
 *
 * ## The vendor call is ours; the resulting state is still the webhook's
 *
 * 2056 keeps webhooks the source of truth on both gateways and this class does
 * not bend it. It calls the gateway, records **that a person asked**, and
 * writes no status, no vendor id and no end date. A row reaches `canceled`
 * when a verified notification says so and at no other moment — because the
 * alternative is a tenant locked out of a product that is still being billed,
 * in every case where our call succeeded and the notification did not arrive,
 * and worse where our call failed.
 *
 * ## Why the request is recorded *before* the vendor is called
 *
 * ⚠️ **THIS ORDERING IS LOAD-BEARING AND THE OBVIOUS ONE IS WRONG.** Writing
 * the request after a successful call reads as the careful version — record
 * only what happened — and it loses a race this application actually runs.
 * `Subscriptions::applyAuthorizeNetSubscription()`'s paid-term arm is scoped to
 * `cancellation_requested_at` being set; the vendor's `cancelled` notification
 * can land while our HTTP response is still in flight, and with the column
 * still null that arm would not fire — cancelling a tenant nine months into a
 * year they had paid for. The cost of the chosen order is a recorded request
 * behind a call that failed, which changes no state and is retried by pressing
 * the button again.
 *
 * ## What is deliberately not here
 *
 * **No refund and no forfeiture.** A tenant who cancels part-way through an
 * annual plan bought in instalments (2055's three payments) has paid a third of
 * a year and holds a year. Charging the remaining instalments after a
 * cancellation is billing by surprise and is what "easy cancellation" forbids;
 * withdrawing the term is 2748's take-the-money-and-remove-the-product; and
 * refunding is a money policy nobody has chosen. So the money stops, the term
 * stands, and the question is written down (`DECISIONS.md` 2980–2999) rather
 * than answered here.
 */
final class SubscriptionCancellation
{
    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly AuditService $audit,
        private readonly Impersonation $impersonation,
        private readonly StripeApi $stripe = new StripeApi,
        private readonly AuthorizeNetApi $authorizeNet = new AuthorizeNetApi,
    ) {}

    /**
     * What cancelling would do, without doing it or calling anybody.
     *
     * The confirm screen's question. It reads our own row only — a `GET` that
     * opened a socket to a payment gateway would be a vendor round trip on
     * every page view of a screen people land on to read it and leave.
     *
     * ⚠️ **IT CAN BE WRONG IN ONE DIRECTION AND THAT IS ACCEPTED.** Our row
     * does not know that an instalment schedule has finished until the vendor
     * says so, so a paid-in-full instalment tenant may be shown
     * {@see CancellationOutcome::KeepsPaidTerm} and then get
     * {@see CancellationOutcome::NoFurtherCharge}. Both sentences are true of
     * their situation, neither costs them anything, and the alternative is the
     * round trip above.
     */
    public function preview(Business $business): CancellationOutcome
    {
        $subscription = $this->subscriptions->for($business);

        if (! $subscription instanceof Subscription || ! $this->hasLiveSubscription($subscription)) {
            return CancellationOutcome::NothingToCancel;
        }

        if ($subscription->status === SubscriptionStatus::Canceled) {
            return CancellationOutcome::AlreadyEnded;
        }

        if ($subscription->gateway === PaymentGateway::Stripe) {
            return CancellationOutcome::StopsAtPeriodEnd;
        }

        return $this->keepsPaidTerm($subscription)
            ? CancellationOutcome::KeepsPaidTerm
            : CancellationOutcome::StopsNow;
    }

    /**
     * Cancel, or explain why there is nothing to cancel.
     *
     * @throws StripeRequestFailed The gateway refused or could not be reached.
     * @throws AuthorizeNetRequestFailed
     */
    public function request(Business $business, string $actor): CancellationOutcome
    {
        // ⚠️ SUPPORT CANNOT END SOMEBODY'S PLAN. On Authorize.Net it cannot be
        // undone, and an agent "helping" through a live act-as session is the
        // one caller who would not feel the consequence.
        $this->impersonation->refuse(ImpersonationCapability::CancelSubscription);

        $subscription = $this->subscriptions->for($business);

        if (! $subscription instanceof Subscription || ! $this->hasLiveSubscription($subscription)) {
            // Nothing recorded: there is no row to record it on in the first
            // case, and nothing was asked of any vendor in either.
            return CancellationOutcome::NothingToCancel;
        }

        if ($subscription->status === SubscriptionStatus::Canceled) {
            return CancellationOutcome::AlreadyEnded;
        }

        // ⚠️ BEFORE THE VENDOR CALL. See the class docblock — the notification
        // can beat our own response back, and the paid-term arm in
        // `Subscriptions::applyAuthorizeNetSubscription()` reads this column.
        $this->subscriptions->recordCancellationRequest($business, Carbon::now());

        $outcome = $subscription->gateway === PaymentGateway::Stripe
            ? $this->cancelAtStripe($business, $subscription)
            : $this->cancelAtAuthorizeNet($business, $subscription);

        // ⚠️ AFTER THE CALL, AND ONLY WHAT IS TRUE. The audit entry names the
        // outcome, so a billing dispute can be answered with what the tenant
        // was told rather than with an inference from a status column that has
        // since moved on. No card details, no vendor ids: `29` §2 rule 42's
        // record is of the act.
        $this->audit->record('billing.subscription_cancellation_requested', $actor, $subscription, [
            'gateway' => $subscription->gateway?->value,
            'term' => $subscription->term?->value,
            'outcome' => $outcome->value,
        ]);

        return $outcome;
    }

    /**
     * ⚠️ **THE ROW IS NOT WRITTEN HERE AND THE RETURN VALUE IS NOT READ AS
     * STATE.** Stripe answers with the updated subscription; we throw it away.
     * `customer.subscription.updated` carries the same object, verified, and
     * that is what moves `ends_at`.
     *
     * @throws StripeRequestFailed
     */
    private function cancelAtStripe(Business $business, Subscription $subscription): CancellationOutcome
    {
        $this->stripe->cancelSubscriptionAtPeriodEnd(
            (string) $subscription->stripe_subscription_id,
            $business->id,
        );

        return CancellationOutcome::StopsAtPeriodEnd;
    }

    /**
     * ⚠️ **THE STATUS IS READ BEFORE THE CANCEL, AND THAT ROUND TRIP IS NOT
     * DEFENSIVE CLUTTER.** An annual plan bought in instalments finishes paying
     * inside the first quarter and Authorize.Net then reports it `expired`
     * (2748) — while our own row still reads `active`, because that is exactly
     * what 2748's arm makes it read. `ARBCancelSubscriptionRequest` against a
     * finished subscription is an error, so without this read the tenant most
     * likely to press Cancel and be told something confusing is the one who has
     * already paid us for a whole year.
     *
     * `subscriptionStatus()` is the reconciliation read this gateway already
     * needs for its own reasons; using it here adds a round trip to a
     * deliberate, rare, human-initiated action and nothing else.
     *
     * @throws AuthorizeNetRequestFailed
     */
    private function cancelAtAuthorizeNet(Business $business, Subscription $subscription): CancellationOutcome
    {
        $subscriptionId = (string) $subscription->authorize_net_subscription_id;

        $vendorStatus = AuthorizeNetSubscriptionStatus::fromVendor(
            $this->authorizeNet->subscriptionStatus($business->id, $subscriptionId),
        );

        if ($vendorStatus === AuthorizeNetSubscriptionStatus::Expired) {
            // The schedule of payments is complete. There is nothing to cancel
            // and, by 2749, nothing that would have renewed it either.
            return CancellationOutcome::NoFurtherCharge;
        }

        if ($vendorStatus === AuthorizeNetSubscriptionStatus::Canceled
            || $vendorStatus === AuthorizeNetSubscriptionStatus::Terminated) {
            return CancellationOutcome::AlreadyEnded;
        }

        // ⚠️ A NULL STATUS IS NOT TREATED AS "ALREADY GONE". `fromVendor()`
        // answers null for a word this application does not model, and rounding
        // that off to "nothing to do" would silently leave a live subscription
        // billing somebody. Cancelling is the safe direction: the vendor's own
        // refusal is what stops a cancel that cannot be made.
        $this->authorizeNet->cancelSubscription($business->id, $subscriptionId);

        return $this->keepsPaidTerm($subscription)
            ? CancellationOutcome::KeepsPaidTerm
            : CancellationOutcome::StopsNow;
    }

    /**
     * Is there a year already bought that outlives the vendor's subscription?
     *
     * The condition the projection arm keys on, asked in the one place both the
     * preview and the outcome need it, so the screen's promise and the row's
     * behaviour cannot come apart.
     */
    private function keepsPaidTerm(Subscription $subscription): bool
    {
        if ($subscription->term !== BillingTerm::Annual) {
            return false;
        }

        $termEndsOn = $subscription->annual_term_ends_on;

        return $termEndsOn !== null && $termEndsOn->isFuture();
    }

    private function hasLiveSubscription(Subscription $subscription): bool
    {
        return $subscription->stripe_subscription_id !== null
            || $subscription->authorize_net_subscription_id !== null;
    }
}
