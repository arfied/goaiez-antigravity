<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingTerm;
use App\Enums\Plan;
use App\Exceptions\AuthorizeNetRequestFailed;
use App\Exceptions\QuotedPriceChanged;
use App\Models\Business;
use App\Models\Subscription;
use App\Support\CardholderName;
use App\Support\Money;
use App\Support\PlanSelection;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Turning an Accept.js nonce into a live ARB subscription (T137 SL-11).
 *
 * The Authorize.Net counterpart of {@see BillingCheckout}, and the differences
 * from it are the interesting part rather than the similarities.
 *
 * ## The card never reaches this application, and it reaches it differently
 *
 * Stripe's hosted Checkout takes the browser to Stripe's own domain. Accept.js
 * keeps the form on our page and posts the card **from the browser directly to
 * Authorize.Net**, handing back an opaque nonce — `dataDescriptor`
 * `COMMON.ACCEPT.INAPP.PAYMENT`, `dataValue` a token, valid **15 minutes**
 * (verified against the live Accept.js documentation, read 2026-08-11). Our form
 * submits that nonce and no card field. **SAQ-A is preserved on both paths and
 * no card number touches this application** — decision 2056.
 *
 * ⚠️ **THE 15-MINUTE EXPIRY IS A REAL FAILURE MODE AND IT HAS A GOOD SYMPTOM
 * AND A BAD ONE.** A person who tokenises and then wanders off returns to a
 * nonce that no longer works, which is fine and recoverable. What is not fine is
 * a *queued* exchange: putting this on a job that runs when the queue drains
 * turns a working integration into one that fails whenever the queue is busy,
 * intermittently, at signup. **This runs synchronously in the request**, which
 * is the opposite of the default for anything touching a vendor, and it is
 * deliberate.
 *
 * ## Idempotency is ours, because the vendor has none
 *
 * There is no `Idempotency-Key` on this gateway (see {@see AuthorizeNetApi}), so
 * a double-click is refused **structurally**: a business already holding an ARB
 * subscription id is refused a second one, and a business holding a customer
 * profile reuses it rather than creating another. A lost create response is
 * recovered rather than retried — the profile is looked up by
 * `merchantCustomerId`, which is the business id.
 *
 * ## The trial is a future start date
 *
 * ARB expresses a trial as whole occurrences of the billing interval, so with
 * decision 147's 30-day cycle the shortest trial it can describe is 30 days and
 * this product sells 14 (2065). The subscription is therefore created with
 * `startDate` = today + `billing.trial_days` and no trial fields at all. See
 * decision 2138.
 *
 * ## The annual term and the instalment plan (decision 2680)
 *
 * ⚠️ **THIS CLASS REFUSED BOTH UNTIL 2026-08-12 AND THE REFUSAL WAS RIGHT AT THE
 * TIME.** 582's rule — no branch without a caller — kept the annual path out
 * while there was no plan picker, and 2149 recorded that `App\Support\Instalments`
 * derived payments nothing charged. The owner's answer was to build the
 * machinery before the price: the retail schedule in `DefaultsManifest` is what
 * is billed here, and **founder rates are still an offer with no home
 * until `plan_offers` exists** (2090, untouched).
 *
 * The three shapes this gateway now creates, and the vendor field that carries
 * each:
 *
 *   monthly      `interval` 30 days, `totalOccurrences` 9999 — unchanged.
 *   annual       `interval` 12 months, `totalOccurrences` 9999. A calendar
 *                anniversary, which is the only annual form the vendor documents
 *                (`months` accepts 1–12; `days` stops at 365).
 *   instalments  `interval` 30 days, `totalOccurrences` 3, `trialOccurrences` 2 —
 *                the vendor's own "bill a different amount for the first N
 *                payments" mechanism, which is exactly 2055's three payments.
 *                See {@see AuthorizeNetApi::createSubscription()} for the
 *                verbatim vendor wording and for why the remainder-last ordering
 *                is the only one ARB can express.
 *
 * ⚠️ **AN INSTALMENT SUBSCRIPTION ENDS WHEN IT IS PAID, AND THE TERM IT BOUGHT
 * DOES NOT.** Three payments a cycle apart finish inside the first quarter and
 * the vendor then reports `expired`. `subscriptions.annual_term_ends_on` records
 * what was sold so that `Subscriptions::applyAuthorizeNetSubscription()` can tell
 * "fully paid" from "the year ran out" — without it, a tenant who paid $997 up
 * front is cancelled three months in.
 */
final class AuthorizeNetGateway
{
    /**
     * ⚠️ NOT `config('app.name')`, WHICH IS "Laravel" IN `.env.example`.
     *
     * `BillingCheckout`'s reasoning, unchanged: this string reaches the
     * cardholder's statement and the vendor's own receipts, and taking it from a
     * config key that ships with a framework default means the first deployment
     * that forgets `APP_NAME` charges people under the name of a PHP framework.
     */
    private const string PRODUCT_NAME = 'GO AI EZ';

    /**
     * ⚠️ ARB's "runs until cancelled", and it is a magic number rather than an
     * absence.
     *
     * `totalOccurrences` has no "forever" value; `9999` is the vendor's own
     * documented way to say it, and it is what their examples use for an ongoing
     * subscription. Omitting the field is not the same thing — the request is
     * rejected without it.
     */
    private const int RUNS_UNTIL_CANCELLED = 9999;

    /**
     * The annual term, as an ARB interval.
     *
     * Not a registry key and not a plan figure: it is what "annual" means, and
     * the vendor's `months` unit accepts 1–12 (verified 2026-08-12).
     */
    private const int MONTHS_IN_A_YEAR = 12;

    public function __construct(
        private readonly AuthorizeNetApi $api,
        private readonly Subscriptions $subscriptions,
        private readonly PlanCharges $charges = new PlanCharges,
    ) {}

    /**
     * Tokenise, subscribe, and record — or refuse and change nothing.
     *
     * @param  string  $opaqueDataValue  The Accept.js nonce. ⚠️ Never a card
     *                                   number: no method on this class or on
     *                                   {@see AuthorizeNetApi} accepts one, so
     *                                   there is no parameter for somebody in a
     *                                   hurry to fill in.
     * @param  CardholderName  $cardholder  ⛔ **THE NAME ON THE CARD, AND THIS
     *                                      GATEWAY WILL NOT CREATE A
     *                                      SUBSCRIPTION WITHOUT IT.** It reaches
     *                                      whichever of the two profile creators
     *                                      {@see self::resolveProfile()} picks —
     *                                      which is the fork a fix confined to
     *                                      one of them walks straight past. See
     *                                      {@see CardholderName} for the three
     *                                      live probes that settled the shape.
     * @param  ?PlanSelection  $selection  What they are buying. Null is the
     *                                     monthly plan — the shape every caller
     *                                     had before decision 2680, kept as the
     *                                     default so that "no choice made" cannot
     *                                     accidentally become the annual charge.
     * @param  ?int  $quotedTotalMinorUnits  What the page that took the card
     *                                       **displayed** as the total (4640).
     *                                       ⛔ **A COMPARAND, NEVER AN AMOUNT** —
     *                                       nothing below sends it, stores it or
     *                                       falls back to it; the charge is always
     *                                       the figure this class derives. Null
     *                                       means nothing was quoted to anybody,
     *                                       which is what a direct service call
     *                                       is; the one HTTP caller always passes
     *                                       it, and its own form request makes the
     *                                       field `required` so a browser cannot
     *                                       opt out of the check.
     *
     * @throws AuthorizeNetRequestFailed The vendor refused or could not be reached.
     * @throws QuotedPriceChanged The price moved between the quote and this call.
     * @throws RuntimeException This business must not be subscribed again.
     */
    public function subscribe(
        Business $business,
        string $email,
        string $opaqueDataValue,
        CardholderName $cardholder,
        ?PlanSelection $selection = null,
        ?int $quotedTotalMinorUnits = null,
    ): Subscription {
        $selection ??= PlanSelection::monthly();

        $existing = $this->subscriptions->for($business);

        // ⚠️ THE GUARD THAT STOPS A DOUBLE-CLICK BECOMING TWO SUBSCRIPTIONS, and
        // on this gateway it is the *only* thing in the way — there is no
        // idempotency key at the vendor boundary to fall back on. It reads the
        // persisted row rather than anything held in the request, and it checks
        // both vendors' ids because a business already paying through Stripe
        // must not acquire a second live subscription here.
        //
        // ⛔ **IT READS IDS AND NOT `gateway`, AND THAT ASYMMETRY WITH
        // {@see BillingCheckout} IS DELIBERATE (9092).** There the predicate has
        // to be `gateway`, because that column is what
        // `subscriptions_gateway_matches_its_ids` refuses a Stripe id against.
        // Here it must not be: `Subscriptions::linkAuthorizeNetCustomer()` sets
        // `gateway`, and this method calls it further down — *before* the vendor
        // call that mints a subscription id — so a row on this gateway with no
        // id is somebody whose card was declined a minute ago. Refusing them would leave them no way to buy at
        // all — and nothing in this application recreates a subscription (8975),
        // so nothing could undo it. Pinned by *"the tenant whose first attempt
        // failed after tokenisation can still buy here"*.
        if ($existing instanceof Subscription
            && ($existing->authorize_net_subscription_id !== null || $existing->stripe_subscription_id !== null)) {
            throw new RuntimeException(
                'This business already has a live subscription. Creating a second one '
                .'would invoice them twice, and neither gateway would know about the other.'
            );
        }

        // ⛔ THE PRICE IS RESOLVED ONCE, HERE, AND CARRIED TO THE WRITER (4346).
        // `Subscriptions` used to price the same purchase again from its own
        // `PlanCharges` — a second memo of the live offers, consulted *after* the
        // vendor call below returns. A founder window closing in that gap created
        // an ARB subscription at the founder rate with a row stating the retail
        // one, which `RenewalReminders` then quotes back for ever (3444, with the
        // offer as its new cause).
        //
        // ⚠️ **IT IS RESOLVED BEFORE THE PROFILE RATHER THAN AFTER IT (4640)**, so
        // that a price which has moved out from under the page refuses without
        // having created a payment profile at the vendor first. The check below
        // is the last thing between a quote and a charge, and the earliest point
        // it can be made is the point the price becomes known.
        $quote = $this->charges->quote($selection);

        if ($quotedTotalMinorUnits !== null && $quote->total()->minorUnits !== $quotedTotalMinorUnits) {
            // ⛔ THE MEMO CANNOT REACH ACROSS A REQUEST BOUNDARY AND WAS NEVER
            // MEANT TO. A checkout renders its figures from one `PlanCharges` and
            // this class prices the charge from another, resolved later — inside
            // one request when the caller is the controller, and minutes later
            // when the caller is a person who left the tab open. Whatever the
            // gap, the rule is the same: the card is charged what the screen
            // said, or it is not charged.
            throw QuotedPriceChanged::between($quotedTotalMinorUnits, $quote->total()->minorUnits);
        }

        $profile = $this->resolveProfile($business, $email, $opaqueDataValue, $cardholder);

        $this->subscriptions->linkAuthorizeNetCustomer(
            $business,
            $profile['customerProfileId'],
            $profile['customerPaymentProfileId'],
        );

        $startsOn = $this->charges->firstChargeOn();

        $schedule = $this->paymentSchedule($selection);

        $subscriptionId = $this->api->createSubscription(
            businessId: $business->id,
            name: $this->subscriptionName($selection),
            amountMinorUnits: $schedule['amountMinorUnits'],
            intervalLength: $schedule['intervalLength'],
            intervalUnit: $schedule['intervalUnit'],
            // ARB's `startDate` is `YYYY-MM-DD` — a calendar day with no time
            // and no zone. Formatting it anywhere else would invite a
            // `toIso8601String()`, which the vendor rejects.
            startDate: $startsOn->toDateString(),
            totalOccurrences: $schedule['totalOccurrences'],
            customerProfileId: $profile['customerProfileId'],
            customerPaymentProfileId: $profile['customerPaymentProfileId'],
            trialOccurrences: $schedule['trialOccurrences'],
            trialAmountMinorUnits: $schedule['trialAmountMinorUnits'],
        );

        $this->subscriptions->startAuthorizeNetSubscription(
            $business,
            $subscriptionId,
            $startsOn,
            Carbon::now(),
            // ⚠️ THE WHOLE QUOTE RATHER THAN ITS TERM SINCE 3443, AND RATHER THAN
            // ITS SELECTION SINCE 4346 — the writer records the price this was
            // sold at beside the term that names it, and it records the figure
            // that was actually sent rather than one it looks up afterwards.
            $quote,
            $selection->inInstalments ? $schedule['totalOccurrences'] : null,
            $selection->term === BillingTerm::Annual
                ? $this->charges->termEndsOn($startsOn)
                : null,
        );

        $subscription = $this->subscriptions->for($business);

        if (! $subscription instanceof Subscription) {
            // Unreachable through the writer above, which creates the row if it
            // is missing. Stated rather than silently returning null, because a
            // null here would be read as "not subscribed" by a caller that has
            // just subscribed somebody.
            throw new RuntimeException('The subscription row vanished between writing and reading it.');
        }

        return $subscription;
    }

    /**
     * Replace the card on an existing profile and point the subscription at it.
     *
     * The dunning remedy, and the **only** recovery that actually works on this
     * gateway — see `Dunning`'s docblock: charging the stored profile directly
     * would take the money without reinstating the suspended subscription,
     * leaving a tenant who has paid and is still suspended.
     *
     * ⚠️ **IT TAKES A CARDHOLDER NAME FOR THE SAME REASON THE SIGNUP PATH DOES,
     * AND THE REASON IS THE CREATOR RATHER THAN THE SURFACE.** This method
     * creates a **new payment profile** at the vendor, and a payment profile
     * with no `billTo` is what an `ARBCreateSubscriptionRequest` refuses with
     * `E00014`. Whether `ARBUpdateSubscriptionRequest` re-validates it against
     * the profile it is pointed at is **not proven by any probe we have**, so
     * the name is collected on this path rather than assumed unnecessary: the
     * population here is somebody in dunning trying to save their plan, and the
     * failure to be avoided is the one that leaves a stored card at the vendor
     * attached to nothing.
     *
     * @throws AuthorizeNetRequestFailed
     * @throws RuntimeException
     */
    public function replacePaymentMethod(
        Business $business,
        string $opaqueDataValue,
        CardholderName $cardholder,
    ): void {
        $subscription = $this->subscriptions->for($business);

        $customerProfileId = $subscription?->authorize_net_customer_profile_id;
        $subscriptionId = $subscription?->authorize_net_subscription_id;

        if ($customerProfileId === null || $subscriptionId === null) {
            throw new RuntimeException(
                'This business has no Authorize.Net subscription to move a card onto.'
            );
        }

        $paymentProfileId = $this->api->createPaymentProfile(
            $business->id,
            $customerProfileId,
            $opaqueDataValue,
            $cardholder,
        );

        $this->api->updateSubscriptionPaymentProfile(
            $business->id,
            $subscriptionId,
            $customerProfileId,
            $paymentProfileId,
        );

        $this->subscriptions->linkAuthorizeNetCustomer($business, $customerProfileId, $paymentProfileId);
    }

    /**
     * The profile to bill, created or adopted.
     *
     * ⚠️ **ADOPTING RATHER THAN CREATING IS THE RECOVERY THE MISSING IDEMPOTENCY
     * KEY FORCES.** A create whose response was lost leaves a profile at the
     * vendor and no row here; without this lookup the next attempt mints a second
     * profile, and the subscription then hangs off a profile our row does not
     * name. The lookup is by `merchantCustomerId`, which is the business id,
     * which is why {@see AuthorizeNetApi::createCustomerProfile()} sends it.
     *
     * @return array{customerProfileId: string, customerPaymentProfileId: string}
     *
     * @throws AuthorizeNetRequestFailed
     */
    private function resolveProfile(
        Business $business,
        string $email,
        string $opaqueDataValue,
        CardholderName $cardholder,
    ): array {
        $subscription = $this->subscriptions->for($business);

        // Our own row first, the vendor second. The second read is the recovery
        // above and costs a round trip, so it only happens for a business we
        // have no profile recorded for at all.
        $stored = $subscription instanceof Subscription
            ? $subscription->authorize_net_customer_profile_id
            : null;

        $stored ??= $this->api->findCustomerProfileByMerchantId($business->id);

        if ($stored !== null) {
            // The profile exists; the nonce in hand is a new card for it. This
            // also covers the ordinary case of somebody abandoning checkout and
            // coming back with a different card.
            return [
                'customerProfileId' => $stored,
                'customerPaymentProfileId' => $this->api->createPaymentProfile(
                    $business->id,
                    $stored,
                    $opaqueDataValue,
                    $cardholder,
                ),
            ];
        }

        return $this->api->createCustomerProfile(
            $business->id,
            $business->name,
            $email,
            $opaqueDataValue,
            $cardholder,
        );
    }

    /**
     * The selection, as the five fields ARB's `paymentSchedule` needs.
     *
     * ⚠️ **THE INSTALMENT ARM ASSERTS THE SHAPE OF THE SPLIT RATHER THAN
     * ASSUMING IT, AND THAT ASSERTION IS ABOUT DECISION 2135.** The vendor can
     * express one reduced amount repeated N times followed by the full amount —
     * that and nothing else. `Instalments` puts the remainder last, which fits
     * exactly; **decision 543's ordering, with the remainder first, does not fit
     * at all.** If that ordering is ever chosen, this throws at the moment of
     * purchase instead of silently billing the first payment's figure for two of
     * the three. 2135 is not resolved here and this does not vote on it — it
     * makes the vendor's constraint visible where it bites.
     *
     * @return array{
     *     amountMinorUnits: int,
     *     intervalLength: int,
     *     intervalUnit: string,
     *     totalOccurrences: int,
     *     trialOccurrences: int,
     *     trialAmountMinorUnits: ?int,
     * }
     */
    private function paymentSchedule(PlanSelection $selection): array
    {
        if ($selection->inInstalments) {
            $payments = $this->charges->paymentsFor($selection);
            $final = array_pop($payments);
            $earlier = array_unique(array_map(
                static fn (Money $payment): int => $payment->minorUnits,
                $payments,
            ));

            if ($final === null || count($earlier) !== 1) {
                throw new RuntimeException(
                    'Authorize.Net can only bill one reduced amount for the first payments '
                    .'and the full amount for the rest, so an instalment plan whose earlier '
                    .'payments differ from each other cannot be created on this gateway.'
                );
            }

            return [
                'amountMinorUnits' => $final->minorUnits,
                'intervalLength' => $this->charges->cycleDays(),
                'intervalUnit' => AuthorizeNetApi::INTERVAL_DAYS,
                'totalOccurrences' => count($payments) + 1,
                'trialOccurrences' => count($payments),
                'trialAmountMinorUnits' => (int) reset($earlier),
            ];
        }

        $annual = $selection->term === BillingTerm::Annual;

        return [
            'amountMinorUnits' => $this->charges->priceFor($selection)->minorUnits,
            'intervalLength' => $annual ? self::MONTHS_IN_A_YEAR : $this->charges->cycleDays(),
            'intervalUnit' => $annual ? AuthorizeNetApi::INTERVAL_MONTHS : AuthorizeNetApi::INTERVAL_DAYS,
            // ⚠️ THE ONLY SHAPE HERE THAT RUNS FOR EVER. An instalment plan is a
            // fixed term and stops itself; a monthly or annual subscription
            // renews until somebody cancels it.
            'totalOccurrences' => self::RUNS_UNTIL_CANCELLED,
            'trialOccurrences' => 0,
            'trialAmountMinorUnits' => null,
        ];
    }

    /**
     * What the merchant interface calls this subscription.
     *
     * The term is in the name because the vendor's own screens are where a
     * support question is answered, and two tenants on different prices would
     * otherwise be one string. Truncation to 50 characters is the API client's.
     *
     * ⚠️ **AND THE LOCATION COUNT IS IN IT FOR THAT SAME STATED REASON, WHICH
     * NOW HAS A SECOND INSTANCE.** Since 2753's SKU became buyable, two tenants on
     * the same term can be on different amounts — and the amount is the first
     * thing a billing dispute asks about. Only stated when there is something to
     * state, so the string a single-location tenant carries is unchanged.
     */
    private function subscriptionName(PlanSelection $selection): string
    {
        $name = self::PRODUCT_NAME.' — '.Plan::Base->label().', '.strtolower($selection->term->label());

        return $selection->additionalLocations === 0
            ? $name
            : $name.', '.($selection->additionalLocations + 1).' locations';
    }

    /*
     * `currency()`, `trialDays()`, `cycleDays()` AND `firstChargeDate()` LIVED
     * HERE AND HAVE MOVED TO PlanCharges, WITH THE PRICE.
     *
     * This class no longer builds a price: it asks what the selection costs and
     * turns the answer into a vendor payload. All four were duplicated
     * word-for-word in `BillingCheckout`, which is two readers of the keys that
     * decide when somebody's card is charged — decision 505's shape, and 518 the
     * first time it bit. **The vendor's own ceiling did not move**: ARB's
     * `interval.unit` of `days` accepts 7 to 365 and that refusal now lives at
     * the vendor boundary, in {@see AuthorizeNetApi::createSubscription()}, which
     * is where a value becomes a request.
     */
}
