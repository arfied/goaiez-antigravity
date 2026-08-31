<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\AuthorizeNetSubscriptionStatus;
use App\Enums\AutopilotActionType;
use App\Enums\CardReplacementOffer;
use App\Enums\ImpersonationCapability;
use App\Enums\PaymentGateway;
use App\Enums\SubscriptionStatus;
use App\Exceptions\AuthorizeNetRequestFailed;
use App\Exceptions\ImpersonationRefused;
use App\Models\Business;
use App\Models\Subscription;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Services\Impersonation\Impersonation;
use App\Services\Tenant\TenantSuspension;
use App\Support\CardholderName;

/**
 * Putting a working card behind a plan that already exists (6500–6519).
 *
 * ⛔ **THE HOLE 6404 NAMED AND COULD NOT FILL.**
 * {@see AuthorizeNetGateway::replacePaymentMethod()} describes itself as *"the
 * dunning remedy, and the **only** recovery that actually works on this
 * gateway"* and had **no caller anywhere in `app/`** — no route, no controller,
 * no screen, no Ops action. Meanwhile {@see DunningNotices} began telling
 * tenants their plan was about to end. This class is the caller.
 *
 * ## ⛔ Why the obvious button was worse than no button
 *
 * `billing.card` is the **signup** screen and its POST ends in
 * {@see AuthorizeNetGateway::subscribe()}, which throws
 * *"This business already has a live subscription"* for any row holding a
 * subscription id at either gateway — **which is every business a dunning notice
 * is sent to**. So *"Update my card"* pointing at the checkout renders, is
 * pressed by somebody trying to save their plan, and 500s. That refusal is
 * correct and must stay: on a vendor with no idempotency key it is the only
 * thing standing between a double-click and two subscriptions. The fix is a
 * second door, not a wider one.
 *
 * ## The vendor call is ours; the resulting state is still the webhook's
 *
 * {@see SubscriptionCancellation}'s rule, unbent (2056). This class calls the
 * gateway, records **that a person did it**, and writes **no** status, no
 * `ends_at`, no dunning attempt and no schedule change. Nothing here closes a
 * dunning schedule: {@see Dunning::advanceIfDue()} already re-reads the vendor
 * before every attempt for exactly this case — *"a tenant can fix their card in
 * the merchant interface… asking before each attempt is what stops the schedule
 * suspending somebody who is already paying"* — and a second closer here would
 * be a race with it over the same head row.
 *
 * ## ⚠️ What the vendor does next, verified rather than assumed (read 2026-08-21)
 *
 * ⛔ **THERE IS NO `ARBReactivateSubscriptionRequest`.** The Authorize.Net XML
 * schema (`apitest.authorize.net/xml/v1/schema/AnetApiSchema.xsd`) declares
 * exactly six ARB operations — create, update, cancel, get, get status, get list
 * — so the Merchant Interface's *"Reactivate Subscription"* button has no API
 * equivalent and this application cannot press it. `ARBUpdateSubscriptionRequest`
 * with a new payment profile is therefore **the whole of the API-reachable
 * remedy**, which is what makes `replacePaymentMethod()`'s claim about itself
 * true rather than merely confident.
 *
 * ⚠️ **WHETHER THAT ALONE RECOVERS THE SUBSCRIPTION IS A MERCHANT-ACCOUNT
 * SETTING NO API CAN READ.** With **Automatic Retry** enabled, the vendor
 * *"will automatically retry the most recent declined payment"* after the
 * payment information is updated, *"every night at 2 am while it still has
 * billing occurrences left"*, and *"will never terminate your subscriptions due
 * to delays in updating the subscription"*
 * (`account.authorize.net/help/Tools/Automated_Recurring_Billing/Automatic_Retry.htm`).
 * Without it, the correction has to land before the next run date or the
 * subscription is terminated. `getMerchantDetailsResponse` does not carry the
 * flag, so nothing in `app/` can tell which account we are on — it is raised as
 * the owner's at 6510 rather than guessed at here.
 *
 * ⚠️ **THIS IS ALSO WHY NO COPY ANYWHERE PROMISES THE PAYMENT WILL BE TAKEN
 * TODAY.** The screen says the outstanding payment *will be taken from the new
 * card*, with no date on it, because the date is the vendor's and depends on a
 * setting we cannot see.
 *
 * ## Money moves, so CONFIRM applies (`CLAUDE.md`)
 *
 * Replacing a card is not itself a charge — and on this vendor it causes one, at
 * a moment nobody here chooses. `CLAUDE.md` reserves CONFIRM for *anything that
 * spends money*, so the screen names the amount before the press rather than
 * afterwards, and it names it from `PlanCharges::agreedPriceFor()` (3443) with
 * the pre-columns fallback arm **withheld** on {@see DunningNotices::amountFor()}'s
 * reasoning (6406): this is a claim about a charge, and on that arm the figure is
 * today's registry.
 */
final class PaymentMethodReplacement
{
    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly AuthorizeNetGateway $gateway,
        private readonly AuditService $audit,
        private readonly Impersonation $impersonation,
        private readonly TenantSuspension $suspension,
        private readonly ActivityService $activity = new ActivityService,
        private readonly AuthorizeNetApi $api = new AuthorizeNetApi,
    ) {}

    /**
     * What this account may be offered, without calling anybody.
     *
     * ⚠️ **OUR OWN ROW ONLY** — {@see SubscriptionCancellation::preview()}'s
     * rule. A `GET` that opened a socket to a payment gateway would be a vendor
     * round trip on every view of a page people land on to read and leave, and
     * `/account/plan` is that page.
     *
     * ⚠️ **IT CAN BE WRONG IN ONE DIRECTION AND THAT IS ACCEPTED**, exactly as
     * `preview()`'s can: a subscription the vendor has already expired or
     * terminated still reads {@see CardReplacementOffer::Available} here. The
     * press finds out — see {@see self::replace()}, which reads the vendor
     * **before** it creates anything — and the person is told the true answer
     * then. The direction matters: this errs towards showing a form that turns
     * out to be unnecessary, never towards hiding one somebody needs.
     */
    public function offer(Business $business): CardReplacementOffer
    {
        // ⛔ FIRST, AND NOT BY ACCIDENT. `28` §9.5's suspension is done to a
        // tenant for cause and its answer is *talk to us*; a card field would
        // also be a control whose press cannot land, because
        // `SuspendedTenantStatus` exempts `account.plan` and nothing else, so a
        // Livewire action on this screen is sent to the on-hold page.
        if ($this->suspension->isSuspended($business)) {
            return CardReplacementOffer::AccountOnHold;
        }

        $subscription = $this->subscriptions->for($business);

        if (! $subscription instanceof Subscription) {
            return CardReplacementOffer::NoPlanToPayFor;
        }

        if ($subscription->gateway === PaymentGateway::Stripe
            || $subscription->stripe_subscription_id !== null) {
            // ⚠️ BOTH CONDITIONS, AND THE SECOND IS THE LOAD-BEARING ONE.
            // `gateway` is nullable with no default because most rows predate
            // the column (`Subscription`'s own docblock), so a Stripe row from
            // before it landed answers null — and would fall through to the
            // Authorize.Net arm below, which reads ids it does not have.
            return CardReplacementOffer::HandledByStripe;
        }

        if ($subscription->status === SubscriptionStatus::Canceled) {
            // Ended, by the customer, by us, or by `Dunning` exhausting its
            // schedule. On this vendor that is terminal — there is no
            // reactivate call — so no card field is offered.
            return CardReplacementOffer::PlanHasEnded;
        }

        if ($subscription->authorize_net_subscription_id === null
            || $subscription->authorize_net_customer_profile_id === null) {
            // ⚠️ THE PAIR `replacePaymentMethod()` ITSELF REQUIRES, ASKED HERE
            // SO THE SCREEN AND THE SERVICE AGREE. A `pending_checkout` row is
            // the ordinary occupant: registered, never bought, nothing to pay.
            return CardReplacementOffer::NoPlanToPayFor;
        }

        return CardReplacementOffer::Available;
    }

    /**
     * Move the plan onto a new card, or say why it cannot be moved.
     *
     * Returns the offer it acted on. {@see CardReplacementOffer::Available}
     * means the vendor accepted the change; **every other value means nothing
     * was sent and nothing was created**, and the caller renders that case's own
     * sentence.
     *
     * ⛔ **THIS IS THE ONLY GUARD, AND THAT IS DELIBERATE** (`CLAUDE.md` 398).
     * The screen decides whether to *render* a form; it does not re-ask this
     * question before pressing, because a caller-side check would make the check
     * here unfalsifiable — delete it and the suite stays green while the vendor
     * gets a request for a subscription that has ended.
     *
     * ⚠️ **THE VENDOR IS READ BEFORE ANYTHING IS CREATED, AND THE ROUND TRIP IS
     * NOT DEFENSIVE CLUTTER.** {@see AuthorizeNetGateway::replacePaymentMethod()}
     * creates a payment profile and *then* points the subscription at it, so on
     * a subscription the vendor has already terminated the first call succeeds
     * and the second fails — leaving a stored card at the vendor, attached to
     * nothing, which nothing in this application would ever clean up. Reading
     * first costs one call on a rare, human-initiated action and is what makes
     * the refusal free of side effects. It is `SubscriptionCancellation`'s own
     * argument for the same read, one method over.
     *
     * @param  string  $opaqueDataValue  The Accept.js nonce. ⚠️ **Never a card
     *                                   number**: no method on this class, on
     *                                   {@see AuthorizeNetGateway} or on
     *                                   {@see AuthorizeNetApi} accepts one, so
     *                                   there is no parameter for somebody in a
     *                                   hurry to fill in, and SAQ-A is preserved
     *                                   on this path exactly as it is at signup.
     * @param  CardholderName  $cardholder  ⛔ **THE `billTo` THE NEW PAYMENT
     *                                      PROFILE IS CREATED WITH.** It is the
     *                                      **cardholder**, who need not be the
     *                                      person pressing the button and need
     *                                      not be the account owner — a business
     *                                      in dunning routinely fixes its plan
     *                                      with a different person's card. It is
     *                                      therefore collected on the screen and
     *                                      never derived from `users.name`.
     * @param  string  $actor  `user:<id>` — what the audit row is *for*.
     *
     * @throws AuthorizeNetRequestFailed The vendor refused or could not be reached.
     * @throws ImpersonationRefused A support session tried this.
     */
    public function replace(
        Business $business,
        string $opaqueDataValue,
        CardholderName $cardholder,
        string $actor,
    ): CardReplacementOffer {
        // ⚠️ SUPPORT MAY NOT PUT A CARD ON SOMEBODY'S ACCOUNT. Taking a card
        // over the phone and typing it into the owner's own session is exactly
        // the MOTO path SAQ-A does not cover, and the person who would feel none
        // of the consequence is the agent doing it. The refusal names what to do
        // instead — see `ImpersonationCapability::ManagePaymentMethods`.
        $this->impersonation->refuse(ImpersonationCapability::ManagePaymentMethods);

        $offer = $this->offer($business);

        if (! $offer->offersAForm()) {
            return $offer;
        }

        $subscription = $this->subscriptions->for($business);

        // Unreachable through `offer()`, which has just answered `Available` on
        // the strength of both ids. Stated rather than assumed because the
        // narrowing is what the two calls below depend on.
        if (! $subscription instanceof Subscription) {
            return CardReplacementOffer::NoPlanToPayFor;
        }

        $vendorStatus = AuthorizeNetSubscriptionStatus::fromVendor($this->api->subscriptionStatus(
            $business->id,
            (string) $subscription->authorize_net_subscription_id,
        ));

        // ⚠️ A NULL STATUS IS NOT ROUNDED OFF. `fromVendor()` answers null for a
        // word this application does not model, and treating that as "ended"
        // would refuse a card to a live subscription because the vendor added a
        // sixth status. The direction here is the opposite of
        // `SubscriptionCancellation`'s null handling and for the same underlying
        // reason: both choose the arm that cannot leave somebody billed for a
        // product they are shut out of.
        if ($vendorStatus === AuthorizeNetSubscriptionStatus::Expired) {
            return CardReplacementOffer::NothingIsDue;
        }

        if ($vendorStatus === AuthorizeNetSubscriptionStatus::Canceled
            || $vendorStatus === AuthorizeNetSubscriptionStatus::Terminated) {
            return CardReplacementOffer::PlanHasEnded;
        }

        $this->gateway->replacePaymentMethod($business, $opaqueDataValue, $cardholder);

        // ⛔ AFTER THE VENDOR, AND ONLY WHAT IS TRUE. `29` §2 rule 42's record is
        // of the act: who moved the plan onto a new card and when. ⚠️ **NO CARD
        // DETAIL, NO NONCE AND NO VENDOR PROFILE ID** — the audit log is never
        // pruned, and a payment-profile id is a handle to a stored card.
        $this->audit->record('billing.payment_method_replaced', $actor, $subscription, [
            'gateway' => PaymentGateway::AuthorizeNet->value,
            'vendor_status_before' => $vendorStatus?->value,
        ]);

        // ⚠️ `SystemMessage` WITH ITS OWN TITLE, `CreditPurchases`' precedent: the
        // owner's own act reaching them from the outside. ⛔ **NOT
        // `OwnerActionNeeded`** — that is what `DunningNotices` files when the
        // payment fails, and this is the entry that closes it. Filing the fix
        // under the same case would put a second "something needs your attention"
        // in the feed of somebody who had just attended to it.
        $this->activity->record(
            AutopilotActionType::SystemMessage,
            title: 'You put a new card on your plan',
        );

        return CardReplacementOffer::Available;
    }
}
