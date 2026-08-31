<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\CreditProduct;
use App\Enums\CreditTopUpTier;
use App\Enums\PaymentGateway;
use App\Exceptions\AuthorizeNetRequestFailed;
use App\Exceptions\CreditChargeUnconfirmed;
use App\Exceptions\CreditPurchaseRefused;
use App\Exceptions\StripeRequestFailed;
use App\Models\Business;
use App\Models\CreditPurchase;
use App\Models\Subscription;
use App\Support\Billing\PurchaseConfirmation;
use App\Support\Billing\TopUpSku;
use RuntimeException;

/**
 * Buying credit, on either gateway (decisions 3301–3303, 3426, 3447).
 *
 * ⛔ **NEITHER GATEWAY HAD A ONE-OFF CHARGE PATH BEFORE THIS.**
 * `AuthorizeNetGateway` had `subscribe()` and `replacePaymentMethod()`;
 * `StripeApi` had `createCustomer()` and a subscription-mode Checkout Session.
 * **A top-up is a single charge, and on both vendors that is a different object
 * from a subscription** — `createTransactionRequest` rather than
 * `ARBCreateSubscriptionRequest`, and `mode=payment` rather than
 * `mode=subscription`. Both were read from the vendors' live references on
 * 2026-08-14 and the differences are recorded where they are made.
 *
 * ## The two gateways are asymmetric and it is the vendors' asymmetry
 *
 *   Stripe          the browser goes to Stripe's own hosted page and pays there,
 *                   so **the first thing this application hears is the webhook**.
 *                   There is no synchronous "it worked".
 *   Authorize.Net   the charge runs **here**, against the profile the tenant
 *                   already has, and answers immediately with a transaction id —
 *                   and the webhook still arrives afterwards and is still what
 *                   writes the credit.
 *
 * ⛔ **SAQ-A IS PRESERVED ON BOTH AND NO CARD NUMBER TOUCHES THIS APPLICATION.**
 * Stripe's page is Stripe's. Authorize.Net charges a **stored profile** — the one
 * created at signup from an Accept.js nonce — so there is no card field, no nonce
 * parameter and no method here that could take one. ⚠️ **The consequence is
 * stated rather than hidden**: a tenant with no stored Authorize.Net profile
 * cannot top up on that gateway at all, and is refused with a sentence saying so
 * rather than being offered a card form this slice would have to build.
 *
 * ## A lost response is not a failure, and this class had that wrong
 *
 * ⛔ **AN UNANSWERED CHARGE LEAVES THE PURCHASE `pending` AND THROWS
 * {@see CreditChargeUnconfirmed}.** It used to call `recordFailure()`, which is
 * terminal — the row left the `pending`/`authorized` set
 * {@see PurchaseReconciliation} scans, so nothing revisited it; the caller was
 * told "declined"; and the standing arrangement above it counted no money, saw no
 * charge in flight and charged again the next night. **Three lost responses were
 * $150 taken from an account whose agreement says $50**, which is the surprise
 * 3306 and rule 43's surviving half both exist to prevent.
 *
 * ⚠️ **THE DISCRIMINATOR IS `AuthorizeNetRequestFailed::$outcomeUnknown`, NOT
 * `$retryable`.** An unreadable body is unknown *and* not retryable; reading
 * `retryable` as "did the money move" would file that one as a decline.
 *
 * ## Buying credit needs a plan that is running
 *
 * ⛔ **BOTH PATHS REFUSE A TENANT WHOSE PLAN IS NOT ENTITLED** (3441). Purchased
 * credit cannot be *spent* without a running plan, so selling it into that state
 * is taking money for something the buyer provably cannot use — a refund request
 * with a receipt. {@see AutoTopUps::refusalFor()} already argued this for the
 * automatic path in exactly those words; the gate belongs on the purchase however
 * the purchase was started, and a screen is not where it lives (398).
 *
 * ⚠️ **A TENANT WITH NO SUBSCRIPTION ROW IS ENTITLED**, which is
 * {@see Subscriptions::isEntitled()}'s answer and not a special case here: an
 * account still in trial has never had one and may buy.
 *
 * ## What this class does not do
 *
 * ⚠️ **BOTH SKU TIERS ARE BOUGHT THE SAME WAY HERE.** "Automatic" names the
 * increment an arrangement charges (3306); this class is the charge either way,
 * and {@see AutoTopUps} is what makes one of them repeat.
 */
final class CreditTopUps
{
    /**
     * ⚠️ NOT `config('app.name')`, WHICH IS "Laravel" IN `.env.example`.
     *
     * `BillingCheckout`'s and `AuthorizeNetGateway`'s reasoning, unchanged: this
     * string reaches the cardholder's statement and both vendors' receipts, and
     * taking it from a config key that ships with a framework default means the
     * first deployment that forgets `APP_NAME` charges people under the name of a
     * PHP framework.
     */
    private const string PRODUCT_NAME = 'GO AI EZ';

    public function __construct(
        private readonly TopUpCatalog $catalog = new TopUpCatalog,
        private readonly CreditPurchases $purchases = new CreditPurchases,
        private readonly StripeApi $stripe = new StripeApi,
        private readonly AuthorizeNetApi $authorizeNet = new AuthorizeNetApi,
        private readonly Subscriptions $subscriptions = new Subscriptions,
    ) {}

    /**
     * The Stripe URL to send a tenant to, having opened the purchase behind it.
     *
     * ⚠️ **THE PURCHASE ROW IS WRITTEN BEFORE THE SESSION IS OPENED**, so a
     * session that Stripe creates and whose response is lost still has a row this
     * application can settle when the webhook arrives. The reverse order would
     * leave a payable session pointing at nothing.
     *
     * @throws CreditPurchaseRefused The SKU or the confirmation is not usable.
     * @throws StripeRequestFailed Stripe refused or could not be reached.
     */
    public function checkoutUrl(
        Business $business,
        CreditProduct $product,
        CreditTopUpTier $tier,
        PurchaseConfirmation $confirmation,
        string $successUrl,
        string $cancelUrl,
    ): string {
        $this->refuseWithoutRunningPlan($business);

        $sku = $this->catalog->sku($product, $tier);
        $purchase = $this->purchases->open($sku, PaymentGateway::Stripe, $confirmation);

        try {
            $session = $this->stripe->createTopUpSession(
                $this->sessionParams($business, $sku, $purchase, $successUrl, $cancelUrl),
                $business->id,
                $purchase->reference,
            );
        } catch (StripeRequestFailed $e) {
            // ⛔ **THE ROW WAS LEFT `pending` FOR A PAYMENT NOBODY HAD EVEN
            // TRIED TO START — 9298.** With `stripe_secret` unset the press on
            // `/account/credit` raised out of `StripeApi` uncaught, and the
            // only trace of it was a `pending` `credit_purchases` row that
            // `PurchaseReconciliation` would then go asking Stripe about
            // for ever.
            //
            // ⛔ **AND IT IS FAILED ONLY WHEN NOTHING WAS SENT.**
            // `clientRefused` is the flag that says so (9145's flag, 9294's
            // constructor). A transport failure carries no such claim: a
            // Checkout Session may exist behind it, and `failed` is terminal at
            // `CreditPurchases::settle()` — so those rows stay `pending`, where
            // the notification and the reconciliation sweep can still reach
            // them, exactly as `chargeStoredCard()` leaves an unconfirmed
            // charge. **The wider question — a session opened whose response
            // was lost — is untouched by this slice and is unchanged.**
            if ($e->clientRefused) {
                $this->purchases->recordFailure(
                    $purchase,
                    'No payment gateway is configured, so no Checkout Session was opened.',
                );
            }

            throw $e;
        }

        $url = $session['url'] ?? null;

        if (! is_string($url) || $url === '') {
            // A 200 with no URL is not a shape Stripe documents, and guessing one
            // would send a person to a blank page — `BillingCheckout`'s reasoning.
            // The purchase is failed rather than left pending, because nothing can
            // ever pay for a session nobody can reach.
            $this->purchases->recordFailure($purchase, 'Stripe returned a Checkout Session with no URL.');

            throw new RuntimeException('Stripe returned a Checkout Session with no URL.');
        }

        return $url;
    }

    /**
     * Charge the tenant's stored Authorize.Net profile once.
     *
     * ⛔ **THE MONEY MOVES HERE AND THE CREDIT DOES NOT** (2056). The transaction
     * id is recorded against the purchase and the row moves to `authorized`;
     * `net.authorize.payment.authcapture.created` is what credits it. A tenant
     * whose notification never arrives has an `authorized` row an operator can
     * see, which is the honest form of "paid but uncredited".
     *
     * @throws CreditPurchaseRefused No stored profile, no running plan, or the SKU is unusable.
     * @throws AuthorizeNetRequestFailed The vendor read the request and refused it.
     * @throws CreditChargeUnconfirmed The request was sent and never answered.
     */
    public function chargeStoredCard(
        Business $business,
        CreditProduct $product,
        CreditTopUpTier $tier,
        PurchaseConfirmation $confirmation,
    ): CreditPurchase {
        $this->refuseWithoutRunningPlan($business);

        $subscription = $this->subscriptions->for($business);

        $customerProfileId = $subscription instanceof Subscription
            ? $subscription->authorize_net_customer_profile_id
            : null;
        $paymentProfileId = $subscription instanceof Subscription
            ? $subscription->authorize_net_payment_profile_id
            : null;

        if ($customerProfileId === null || $paymentProfileId === null) {
            // ⛔ REFUSED RATHER THAN COLLECTING A CARD. Taking one here would mean
            // a card form in this application, and SAQ-A is preserved on both
            // gateways by there being no such form and no such parameter (2056).
            throw CreditPurchaseRefused::because(
                'This business has no stored Authorize.Net payment profile to charge. A '
                .'top-up bills the card already on file; collecting a new one is the '
                .'checkout path, not this one.'
            );
        }

        $sku = $this->catalog->sku($product, $tier);
        $purchase = $this->purchases->open($sku, PaymentGateway::AuthorizeNet, $confirmation);

        try {
            $transactionId = $this->authorizeNet->chargeCustomerProfile(
                businessId: $business->id,
                customerProfileId: $customerProfileId,
                customerPaymentProfileId: $paymentProfileId,
                amountMinorUnits: $sku->price->minorUnits,
                reference: $purchase->reference,
                description: self::PRODUCT_NAME.' credit top-up — '.$sku->label(),
            );
        } catch (AuthorizeNetRequestFailed $e) {
            if ($e->outcomeUnknown) {
                // ⛔ NOT `recordFailure()`. The request reached the vendor and the
                // answer did not come back, so the money may have moved — and
                // `failed` is terminal at `CreditPurchases::settle()`'s claim,
                // which would put this row beyond both the notification that may
                // still arrive and the reconciliation sweep that exists for
                // exactly this. It stays `pending`, where both can still reach it.
                //
                // ⚠️ AND THE PURCHASE TRAVELS WITH THE EXCEPTION, because the
                // caller that arranged this charge has to count money it may have
                // spent, and it has no other handle on the row.
                throw CreditChargeUnconfirmed::of($purchase, $e->reason);
            }

            // ⚠️ THE REASON, NEVER THE MESSAGE TEXT. `AuthorizeNetRequestFailed`
            // carries an error code precisely because the vendor's `text` quotes
            // the value it rejected — a card's last four, an email address — and
            // this string lands on a row an operator reads.
            //
            // ⛔ **AND IT MAY NOT SAY THE VENDOR REFUSED SOMETHING IT NEVER
            // SAW** (9298). `clientRefused` is true only when this application
            // declined to run at all — no credential — and writing *"Authorize.Net
            // refused the charge"* onto that row puts words in a vendor's mouth
            // about a request that never left this process, in the one place an
            // operator goes to find out what happened. ⚠️ `configuration` is
            // **not** the discriminator: it is also true of `E00007`, which is a
            // key that was sent and rejected.
            $this->purchases->recordFailure(
                $purchase,
                $e->clientRefused
                    ? 'No payment gateway is configured, so nothing was sent to Authorize.Net.'
                    : 'Authorize.Net refused the charge: '.$e->reason,
            );

            throw $e;
        }

        $this->purchases->recordAuthorization($purchase, $transactionId);

        return $purchase->refresh();
    }

    /**
     * ⛔ **SELLING CREDIT INTO AN ACCOUNT THAT CANNOT SPEND IT IS A REFUND
     * REQUEST** (3441). The refusal is the same on both gateways and on both
     * paths, because it is a fact about the account rather than about how the
     * purchase was started.
     *
     * @throws CreditPurchaseRefused
     */
    private function refuseWithoutRunningPlan(Business $business): void
    {
        if ($this->subscriptions->isEntitled($business)) {
            return;
        }

        throw CreditPurchaseRefused::because(
            'This account has no running plan, and credit that is bought cannot be spent '
            .'without one. Taking the payment now would be taking money for something the '
            .'account provably cannot use; starting a plan is what unlocks both.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionParams(
        Business $business,
        TopUpSku $sku,
        CreditPurchase $purchase,
        string $successUrl,
        string $cancelUrl,
    ): array {
        $params = [
            // ⛔ `payment`, NOT `subscription`. See StripeApi::createTopUpSession().
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,

            // ⚠️ PINNED, SO `checkout.session.completed` + `payment_status=paid`
            // IS THE ONLY SUCCESS PATH. Unset, the account's enabled methods
            // decide, and a delayed one settles through an event nothing handles.
            'payment_method_types[0]' => 'card',

            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($sku->price->currency),
            'line_items[0][price_data][unit_amount]' => $sku->price->minorUnits,
            'line_items[0][price_data][product_data][name]' => self::PRODUCT_NAME.' credit top-up — '.$sku->label(),

            // ⚠️ NO `recurring` OBJECT ANYWHERE IN THESE PARAMS. One would make
            // this a subscription in Stripe's own words, and the session would
            // still be created and still be payable.

            // The handle the webhook resolves through, on the session *and* on
            // the PaymentIntent it creates — two places, because a future handler
            // reading `payment_intent.succeeded` would otherwise find a payment
            // with no reference on it at all.
            'metadata[credit_purchase]' => $purchase->reference,
            'payment_intent_data[metadata][credit_purchase]' => $purchase->reference,

            // Belt to the metadata's braces, and the same field
            // `BillingCheckout` uses: present on `checkout.session.completed`.
            'client_reference_id' => (string) $business->id,
        ];

        $customerId = $this->subscriptions->stripeCustomerFor($business);

        if ($customerId !== null) {
            // Reused when there is one, and never created for a top-up: a
            // customer is minted by the subscription path (688's deterministic
            // idempotency key), and minting a second one here would give a
            // business two Stripe customers with its subscription on one of them.
            $params['customer'] = $customerId;
        }

        return $params;
    }
}
