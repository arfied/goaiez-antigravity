<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingTerm;
use App\Enums\PaymentGateway;
use App\Enums\Plan;
use App\Exceptions\StripeRequestFailed;
use App\Models\Business;
use App\Models\Subscription;
use App\Support\PlanQuote;
use App\Support\PlanSelection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Opens the Checkout Session that collects the card decision 98 requires.
 *
 * `29` §11.2 row 22's slice B, and the half of decision 98 slice A could not
 * write: "14-day free trial, **credit card required**" (544, restoring `18`).
 * Slice A recorded the trial starting at registration with no card behind it and
 * said at the write that this was not the product (586). This is where it
 * becomes the product.
 *
 * ## Nothing here is called inside the registration transaction
 *
 * ⚠️ **AND THAT IS THE OPPOSITE OF WHAT PROVISIONING DOES, DELIBERATELY.**
 * `TenantProvisioner` runs inside `CreateNewUser`'s transaction because a user
 * without a business is unrecoverable (271). A Stripe call inside that same
 * transaction would hold a database transaction open across a network round trip
 * to a third party — and worse, a rollback after Stripe answered would destroy
 * our record of a customer that now exists on their side, permanently, with
 * nothing to reconcile from. So registration commits, and the browser is then
 * redirected here. Stripe being unreachable at that moment costs a retry, not
 * an account.
 *
 * ## The price is built from the registry, not from a Stripe Price object
 *
 * ⚠️ **THERE ARE NO STRIPE PRICE OBJECTS AND THAT IS DECISION 689.** `38` D-151
 * imagines an Ops price edit creating the matching Stripe Price in the same
 * breath, and decision 516 recorded that we had no way to honour it and left
 * `setEntitlement()`'s edit path with no caller for exactly that reason. Inline
 * `price_data` removes the problem rather than solving it: **the registry stays
 * the only place a price is written**, and Stripe is told the number at the
 * moment a subscription is created rather than holding a copy that can disagree
 * with the page selling it (512). Grandfathering still works — a Stripe
 * subscription keeps the price it was created with, which is what 507 versions
 * `plan_entitlements` to preserve.
 *
 * ## The annual term arrives, and the instalment plan does not (decision 2680)
 *
 * `Plan::Base` monthly **or** annual, chosen by the caller. `Free` and `Limited`
 * are still not purchasable: Free bills nothing and needs no Checkout at all, and
 * Limited's price is one of the two figures the owner has not set and which may
 * not be guessed (502).
 *
 * ⛔ **THE INSTALMENT PLAN IS REFUSED ON THIS GATEWAY, AND THE REASON IS THE
 * VENDOR'S RATHER THAN A DECISION OF OURS.** 2055's annual is three payments
 * whose last one carries the remainder, and a Checkout Session cannot express
 * that in any of its shapes. Verified against the pinned `stripe/stripe-php`'s
 * own parameter contract on 2026-08-12 rather than from memory:
 * `Checkout\SessionService::create()`'s `subscription_data` accepts
 * `billing_cycle_anchor`, `trial_end`, `trial_period_days`, `metadata` and their
 * siblings — **and no `cancel_at` and no phases** — so a Checkout subscription
 * cannot be told to stop after three payments, and one `price_data` carries one
 * `unit_amount`, so the payments cannot differ from each other.
 *
 * What Stripe *does* have is `/v1/subscription_schedules` with phases, and it
 * needs three things this slice will not add on the side: a **Product object**
 * (a phase's `price_data` takes a `product` id, not the inline `product_data`
 * that decision 689 relies on), a card captured through a `setup`-mode Checkout
 * first, and a webhook branch that creates the schedule — which would make an
 * inbound event the thing that starts charging somebody. That is a slice, not a
 * paragraph, and it is named in the decisions block rather than half-built here.
 *
 * ⚠️ **AUTHORIZE.NET IS THE PRIMARY GATEWAY (2056) AND IT CAN EXPRESS THE
 * INSTALMENT PLAN EXACTLY**, through `trialAmount`/`trialOccurrences` — see
 * {@see AuthorizeNetApi::createSubscription()}. So the plan is buyable; it is
 * buyable on one gateway.
 */
final class BillingCheckout
{
    /**
     * ⚠️ NOT `config('app.name')`, WHICH IS "Laravel" IN `.env.example`.
     *
     * This string is what a customer reads on their card statement line and on
     * every Stripe receipt. Taking it from a config key that ships with a
     * framework default means the first deployment that forgets to set
     * `APP_NAME` charges people under the name of a PHP framework, and nothing
     * in this repository would fail.
     */
    private const string PRODUCT_NAME = 'GO AI EZ';

    public function __construct(
        private readonly StripeApi $stripe,
        private readonly Subscriptions $subscriptions,
        private readonly PlanCharges $charges = new PlanCharges,
    ) {}

    /**
     * The URL to send this business to, or a refusal that says which.
     *
     * @param  ?PlanSelection  $selection  What they are buying. Null is the
     *                                     monthly plan — the shape every caller
     *                                     had before decision 2680, kept as the
     *                                     default so that "no choice made" cannot
     *                                     accidentally become the annual charge.
     *
     * @throws StripeRequestFailed Stripe refused or could not be reached.
     * @throws RuntimeException This business must not be sent to Checkout.
     */
    public function sessionUrlFor(
        Business $business,
        string $successUrl,
        string $cancelUrl,
        ?PlanSelection $selection = null,
    ): string {
        $selection ??= PlanSelection::monthly();

        if ($selection->inInstalments) {
            // ⛔ See the class docblock: this is Stripe's shape, not our policy.
            // Refused here rather than silently collapsed to a single annual
            // charge, because the difference between "three payments" and "$997
            // today" is the whole reason somebody chose it.
            throw new RuntimeException(
                'Stripe Checkout cannot express an instalment plan: a Session carries one '
                .'amount per price and cannot end a subscription after a fixed number of '
                .'payments. The instalment plan is bought on Authorize.Net.'
            );
        }

        $subscription = $this->subscriptions->for($business);

        // ⚠️ THE GUARD THAT STOPS A DOUBLE-CLICK BECOMING TWO SUBSCRIPTIONS.
        // Checkout Sessions carry a *random* idempotency key by design
        // (StripeApi::createCheckoutSession says why), so nothing at the vendor
        // boundary refuses a second one — Stripe would happily create a second
        // subscription for the same customer and invoice both. This is the only
        // thing in the way, which is why it reads the persisted row rather than
        // anything held in a request.
        if ($subscription instanceof Subscription && $subscription->stripe_subscription_id !== null) {
            throw new RuntimeException(
                'This business already has a Stripe subscription. Opening a second '
                .'Checkout Session would create a second subscription and invoice both.'
            );
        }

        // ⛔ AND THE SECOND GUARD, WHICH IS THE ONE THAT STOPS A CARD BEING
        // CHARGED FOR A SUBSCRIPTION THIS APPLICATION IS GOING TO REFUSE
        // AFTERWARDS (8967, 9092).
        //
        // The guard above reads the *Stripe* id, so for years it answered a
        // question nobody was asking of a returning Authorize.Net customer: they
        // have no Stripe id, they pass, the Session opens, and their card is
        // charged. The refusal then arrives at
        // `Subscriptions::applyStripeSubscription()`, which writes a Stripe id
        // and never writes `gateway`, as a Postgres CHECK violation
        // (`subscriptions_gateway_matches_its_ids`) — in the webhook, after the
        // charge. Money taken, nothing recorded, and the customer is told
        // nothing because a webhook has no reader.
        //
        // ⛔ **SO THIS READS THE GATEWAY AND DELIBERATELY NOT THE VENDOR IDS.**
        // The symmetric-looking fix — `|| $subscription->authorize_net_subscription_id !== null`
        // — is the one to refuse. `gateway` is written by
        // `Subscriptions::linkAuthorizeNetCustomer()`, which runs *before* the
        // vendor call that mints the subscription id, so a tenant whose
        // Authorize.Net attempt failed after tokenisation carries
        // `gateway = authorize_net` with **both ids null**. An id-shaped guard
        // lets them through to be charged and refused exactly as before —
        // and registration redirects to this very route, so the URL is in the
        // browser history of everybody who then used the card page. The
        // predicate that matches the schema is the one the schema uses.
        //
        // ⚠️ **IT DOES NOT ASK WHETHER THE SUBSCRIPTION IS LIVE OR CANCELLED,
        // AND THAT IS THE POINT.** What makes the write impossible is the row's
        // gateway, not its state — a cancelled Authorize.Net row keeps its
        // gateway and its id for ever, because no writer in `app/` nulls either.
        // A guard reasoning about what we believe is live rather than about what
        // the write will do is the same class of mistake as the one above it.
        // What a cancelled customer is owed instead is a way back, and **nothing
        // in this application recreates a subscription** (8975) — that is the
        // owner's to rule on and is deliberately not invented here.
        $gateway = $subscription?->gateway;

        if ($gateway !== null && $gateway !== PaymentGateway::Stripe) {
            throw new RuntimeException(
                "This business's subscription is on {$gateway->label()}. Opening a Stripe "
                .'Checkout Session would charge their card for a subscription this '
                .'application could not then record: a row carries one gateway and that '
                ."gateway's ids, so the webhook would be refused by "
                .'`subscriptions_gateway_matches_its_ids` after the money had gone. A '
                .'business bought on one gateway is renewed, cancelled and — when '
                .'somebody rules on how — restarted on that gateway.'
            );
        }

        $customerId = $this->subscriptions->stripeCustomerFor($business)
            ?? $this->createCustomer($business);

        // ⛔ RESOLVED ONCE AND CARRIED (4346). The Session was built from this
        // object's `PlanCharges` and the row was then written from
        // `Subscriptions`' own — two memos of the live offers, with a network
        // round trip between them. See {@see PlanQuote}.
        $quote = $this->charges->quote($selection);

        $session = $this->stripe->createCheckoutSession(
            $this->sessionParams($business, $customerId, $successUrl, $cancelUrl, $quote),
            $business->id,
            Str::uuid()->toString(),
        );

        // ⚠️ WHAT WE QUOTED, WRITTEN BEFORE THE PERSON EVER REACHES STRIPE, AND
        // IT IS NOT A STATUS. `29`'s webhooks-are-the-source-of-truth rule is
        // about *subscription state* and is untouched — nothing here writes a
        // status, an id or a period. What it records is which of our two prices
        // this Session was built from, because **Stripe cannot tell us**: the
        // price is inline `price_data` (689), so the event that comes back
        // carries an amount and an interval and no name for the term behind
        // them. The same reasoning as `authorize_net_starts_on` (2138) — store
        // what we sent, so the state is provable rather than inferred.
        //
        // ⚠️ THE WHOLE SELECTION RATHER THAN ITS TERM SINCE 3443: the price this
        // Session was built from is stored beside the term that names it, because
        // an existing customer keeps the price they signed up at and the registry
        // figure will not still be that number. Nothing about the *charge* changes
        // — Stripe holds it and its webhooks stay the source of truth (2056).
        $this->subscriptions->recordQuotedSelection($business, $quote);

        $url = $session['url'] ?? null;

        if (! is_string($url) || $url === '') {
            // A 200 with no URL is not a shape Stripe documents, and guessing
            // one would send a person to a blank page. Decision 277's third
            // outcome, applied here: answered, failed, and *unusable*.
            throw new RuntimeException('Stripe returned a Checkout Session with no URL.');
        }

        return $url;
    }

    /**
     * Create the Stripe customer and record the mapping both places it belongs.
     *
     * @throws StripeRequestFailed
     */
    private function createCustomer(Business $business): string
    {
        $customer = $this->stripe->createCustomer([
            'name' => $business->name,

            // ⚠️ `business_id`, NEVER THE OWNER'S EMAIL AS THE LINK. Stripe's
            // customer email is a convenience for their receipts; metadata is
            // what survives the owner changing their address, and it is what
            // makes a `customer.subscription.*` event resolvable without
            // depending on the Checkout event having arrived first — which
            // Stripe does not guarantee.
            'metadata[business_id]' => $business->id,
        ], $business->id);

        $customerId = $customer['id'] ?? null;

        if (! is_string($customerId) || $customerId === '') {
            throw new RuntimeException('Stripe returned a customer with no id.');
        }

        $this->subscriptions->linkStripeCustomer($business, $customerId);

        return $customerId;
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionParams(
        Business $business,
        string $customerId,
        string $successUrl,
        string $cancelUrl,
        PlanQuote $quote,
    ): array {
        $selection = $quote->selection;
        $price = $quote->total();
        $annual = $selection->term === BillingTerm::Annual;

        return [
            'mode' => 'subscription',
            'customer' => $customerId,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,

            // ⚠️ STATED RATHER THAN LEFT TO THE DEFAULT, BECAUSE IT IS THE
            // DECISION. Subscription mode already defaults to `always`, but
            // `if_required` is one word away and it is exactly what a future
            // reader would reach for on being told the trial charges nothing
            // today — and it would silently drop the card requirement decision
            // 98 exists to impose. Writing it makes deleting it a visible act.
            'payment_method_collection' => 'always',

            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($price->currency),
            'line_items[0][price_data][unit_amount]' => $price->minorUnits,
            // ⚠️ THE LOCATION COUNT IS IN THE PRODUCT NAME ON
            // `AuthorizeNetGateway::subscriptionName()`'s stated reasoning, and it
            // matters more here: this string is what a customer reads on their
            // Stripe receipt, and since 2753's SKU became buyable two tenants on
            // the same term can be charged different amounts. Only stated when
            // there is something to state, so a single-location receipt is
            // unchanged.
            'line_items[0][price_data][product_data][name]' => self::PRODUCT_NAME.' — '
                .Plan::Base->label().', '.strtolower($selection->term->label())
                .($selection->additionalLocations === 0
                    ? ''
                    : ', '.($selection->additionalLocations + 1).' locations'),

            // Decision 147: every 30 days, not calendar months. `interval=month`
            // is a different promise — 28 to 31 days, drifting with the anchor —
            // and reading the number from the registry is what gives
            // `billing.cycle_days` the reader CFG1 refused to seed it without.
            //
            // ⚠️ AND THE ANNUAL TERM IS `year`, WHICH IS NOT 147 BEING UNDONE.
            // 147 refuses `month` because a month is 28–31 days and the plan
            // sells 12.17 cycles a year; a year has no such ambiguity — the
            // anniversary of a date is the same date, which is what "$997/year"
            // means. `day` with a count of 365 would be the literal parallel and
            // Stripe's own spec does not enumerate days at that magnitude ("3
            // years, 36 months, or 156 weeks", read 2026-08-12), so it would be a
            // parameter guessed rather than verified — `CLAUDE.md`'s rule about
            // vendor values, on the field that decides when somebody is charged.
            'line_items[0][price_data][recurring][interval]' => $annual ? 'year' : 'day',
            'line_items[0][price_data][recurring][interval_count]' => $annual ? 1 : $this->charges->cycleDays(),

            'subscription_data[trial_period_days]' => $this->charges->trialDays(),

            // ⚠️ THE TERM ON THE VENDOR'S OWN OBJECT, FOR A HUMAN RATHER THAN
            // FOR CODE. Nothing reads it back — our row is the authority — but a
            // subscription in Stripe's dashboard otherwise shows an amount and an
            // interval with no way to say which of our prices it came from,
            // which is the question a billing dispute starts with.
            'subscription_data[metadata][term]' => $selection->term->value,

            // The same link as on the customer, on the object the webhooks
            // actually carry. A `customer.subscription.created` names its
            // customer and its own metadata and nothing else.
            'subscription_data[metadata][business_id]' => $business->id,

            // Present on `checkout.session.completed`. Belt to the metadata's
            // braces: it is what lets that one event write the customer index
            // even if the customer create's response was the thing that was lost.
            'client_reference_id' => (string) $business->id,
        ];
    }

    /*
     * `currency()`, `trialDays()` AND `cycleDays()` LIVED HERE AND HAVE MOVED TO
     * PlanCharges, WITH THE PRICE.
     *
     * All three were duplicated word-for-word in `AuthorizeNetGateway`, which is
     * two readers of the keys that decide when somebody's card is charged and
     * what it is denominated in — decision 505's shape, and 518 the first time it
     * bit. They still fail rather than falling back to a plausible number; what
     * changed is that there is now one of each.
     */
}
