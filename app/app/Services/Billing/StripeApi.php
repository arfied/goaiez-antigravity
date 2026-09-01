<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Exceptions\StripeRequestFailed;
use App\Support\PlatformCredentials;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The one place this application opens a socket to Stripe.
 *
 * ## Why this is Laravel's HTTP client and not `stripe/stripe-php` (decision 680)
 *
 * The SDK is installed — Cashier depends on it — and using it here would be the
 * obvious choice. Decision 277 already refused that argument for the AI
 * providers and the reasoning does not weaken for the vendor that takes money:
 * `Http::preventStrayRequests()` and `40` Part 8's outbound lint both see
 * through `Http::`, and **neither would see an SDK's own bundled Guzzle**. A
 * test that reaches live Stripe is not a hypothetical — it is a charge.
 *
 * ⚠️ **THE SPLIT IS DELIBERATE AND ONLY ONE HALF IS HERE.** Webhook signature
 * verification *does* use the SDK, in {@see StripeWebhooks}: it opens no socket,
 * so there is nothing for either guard to see, and hand-rolling an HMAC compare
 * plus a timestamp tolerance is the one part of this integration where a subtle
 * bug is completely silent. **Outbound is a socket and lives here; verification
 * is arithmetic and lives there.**
 *
 * ## The API version is pinned, and pinning it is load-bearing (decision 684)
 *
 * Read from Stripe's live documentation rather than remembered: at
 * `2025-08-27.basil` the Subscription object carries `status`, `trial_end`,
 * `cancel_at_period_end` and `canceled_at` at the top level and **no
 * `current_period_end`** — that moved onto `items.data[]`. A floating version
 * means the next such move lands on a Tuesday with no deploy of ours, and the
 * symptom is a column silently filling with null.
 *
 * ⚠️ **THIS HEADER GOVERNS OUR READS AND NOT STRIPE'S WEBHOOK PAYLOADS.** The
 * shape of an inbound event is fixed by the API version configured on the
 * account or on the endpoint, which no code here can set. {@see StripeWebhooks}
 * therefore reads that field from both locations rather than trusting one.
 */
final class StripeApi
{
    /**
     * ⚠️ A LITERAL, DELIBERATELY, AND NOT A CONFIG KEY.
     *
     * The outbound-host lint reads host literals out of the files on its
     * permitted list and compares them against `docs/SUBPROCESSOR-INVENTORY.md`
     * (428–433). A base URL supplied from config would still be scanned, but one
     * supplied from `.env` would not be — and decision 438 predicted this exact
     * file's arrival as the thing that would redden the build. It does not,
     * because the host is written here where the scan can read it.
     */
    private const string BASE = 'https://api.stripe.com/v1';

    /**
     * The API version every request declares.
     *
     * Held here rather than taken from `Laravel\Cashier\Cashier::STRIPE_VERSION`,
     * which resolves to whatever `stripe/stripe-php` currently ships: that value
     * moves on `composer update`, which is precisely the unannounced move this
     * constant exists to prevent. Raising it is a deliberate act with the
     * changelog open.
     */
    public const string API_VERSION = '2025-08-27.basil';

    /**
     * The one credential every call from this class carries.
     *
     * ⚠️ **NAMED ONCE SO THE GUARD AND THE READ CANNOT DRIFT ONTO TWO
     * LITERALS** — `TurnstileVerifier::SECRET_KEY`'s house style, and the shape
     * `Architecture\CredentialsTest`'s census resolves.
     */
    public const string CREDENTIAL = 'stripe_secret';

    /**
     * Whether a call from this class can be made at all.
     *
     * ⚠️ **A NAMED QUESTION RATHER THAN A `has()` AT EACH DOOR**, for
     * {@see AuthorizeNetApi::isConfigured()}'s reason: a hand-typed key set at a
     * door is what covered two of four on the other gateway for the life of this
     * application (9295). One key is one key today; the derivation is what keeps
     * that true if a second ever arrives.
     */
    public static function isLive(): bool
    {
        if (! self::isConfigured()) {
            return false;
        }

        $key = PlatformCredentials::get(self::CREDENTIAL);

        return str_starts_with($key, 'sk_live_');
    }

    public static function isConfigured(): bool
    {
        return PlatformCredentials::has(self::CREDENTIAL);
    }

    /**
     * Create a Stripe customer.
     *
     * ⚠️ **THE IDEMPOTENCY KEY IS DETERMINISTIC HERE AND RANDOM FOR CHECKOUT,
     * AND THE DIFFERENCE IS THE POINT** (decision 688). A business must have
     * exactly one Stripe customer ever, so keying on its id means a retry after
     * a timeout returns the customer the lost request created rather than
     * minting a second one that the first subscription is not attached to.
     *
     * @param  array<string, scalar>  $params
     * @return array<string, mixed>
     *
     * @throws StripeRequestFailed
     */
    public function createCustomer(array $params, int $businessId): array
    {
        return $this->post('/customers', $params, "customer:business:{$businessId}", $businessId);
    }

    /**
     * Open a Checkout Session.
     *
     * ⚠️ **A RANDOM IDEMPOTENCY KEY, AND A DETERMINISTIC ONE WOULD BE A BUG.**
     * Sessions expire (24 hours) and are single-use, so replaying the first one
     * back at somebody who returned a week later hands them a dead URL that
     * looks like ours being broken. Each attempt is genuinely a new operation.
     * What stops a double-click becoming two subscriptions is not this key but
     * {@see BillingCheckout}, which refuses to open a session for a business
     * that already has a subscription id.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws StripeRequestFailed
     */
    public function createCheckoutSession(array $params, int $businessId, string $attempt): array
    {
        return $this->post('/checkout/sessions', $params, "checkout:{$attempt}", $businessId);
    }

    /**
     * Open a **one-time payment** Checkout Session — the top-up (3301–3303).
     *
     * ⛔ **A TOP-UP IS NOT A SUBSCRIPTION AND `mode` IS THE WHOLE DIFFERENCE.**
     * Read from Stripe's live Checkout Sessions reference on **2026-08-14** rather
     * than from the subscription call above it: `mode` is `payment` for a one-time
     * charge and `subscription` for a recurring one, and the enum's own wording is
     * *"Pass `subscription` if the Checkout Session includes at least one recurring
     * item."* So a top-up's `price_data` carries **no `recurring` object at all** —
     * adding one, by copying {@see BillingCheckout}'s params, silently sells a
     * subscription to somebody who bought a pack once.
     *
     * ⚠️ **`payment_method_collection` IS NOT SET HERE AND MUST NOT BE.** The same
     * reference states it *"can only be set in `subscription` mode"*. Carrying it
     * over from the subscription params is a 400 from Stripe on every top-up.
     *
     * ⚠️ **`payment_method_types` IS PINNED TO `card`, WHICH BUYS A GUARANTEE
     * RATHER THAN A RESTRICTION.** Left unset, Stripe serves whatever the account
     * has enabled, including delayed-notification methods whose sessions complete
     * `unpaid` and settle later through `checkout.session.async_payment_succeeded`.
     * Pinning card means `checkout.session.completed` with `payment_status = paid`
     * is the *only* success path, so {@see StripeWebhooks} needs one branch rather
     * than three — and a future account-level change cannot quietly introduce a
     * fourth that nothing handles.
     *
     * ⚠️ **THE IDEMPOTENCY KEY IS DETERMINISTIC ON OUR OWN REFERENCE, WHICH IS THE
     * OPPOSITE OF `createCheckoutSession()`'s AND RIGHT FOR THE SAME UNDERLYING
     * REASON** (688). That one is random because a *subscription* session belongs
     * to a business that has one for ever, so replaying a 24-hour-expired session
     * would hand a returning customer a dead URL. Here every attempt opens its own
     * `credit_purchases` row with its own reference, so a retry of *this* call is
     * genuinely the same operation — a lost response must not leave a session
     * Stripe created and we never saw, because that session can still be paid.
     *
     * @param  array<string, mixed>  $params
     * @param  string  $reference  Our own purchase handle. It is the idempotency
     *                             key *and* rides in the session metadata, which
     *                             is how the webhook finds the purchase.
     * @return array<string, mixed>
     *
     * @throws StripeRequestFailed
     */
    public function createTopUpSession(array $params, int $businessId, string $reference): array
    {
        return $this->post('/checkout/sessions', $params, "topup:{$reference}", $businessId);
    }

    /**
     * Stop a subscription renewing, at the end of the period already paid for
     * (2980–2999).
     *
     * ⚠️ **`cancel_at_period_end`, NOT `DELETE /v1/subscriptions/{id}`.** The
     * delete is an immediate cancellation and Stripe does not prorate it by
     * default, so a tenant who cancels on day two of a month they have paid for
     * loses twenty-eight days they are owed. California's law asks that
     * cancellation be *easy*, not that it be punitive, and "never bill by
     * surprise" has an obvious mirror image.
     *
     * ⚠️ **AND IT KEEPS WEBHOOKS THE SOURCE OF TRUTH RATHER THAN WORKING
     * AROUND THEM** (2056). Stripe answers this call with the updated
     * subscription *and* sends `customer.subscription.updated` carrying
     * `cancel_at`; {@see StripeWebhooks} projects that onto `ends_at`, and the
     * row only reaches `canceled` when `customer.subscription.deleted` arrives
     * at the end of the period. Nothing here writes our own row.
     *
     * ⚠️ **THE IDEMPOTENCY KEY IS DETERMINISTIC**, which is the opposite of
     * `createCheckoutSession()`'s and right for the same underlying reason:
     * cancelling twice is not two operations, and a retry after a lost response
     * must not be able to mean anything different from the first attempt.
     *
     * @return array<string, mixed>
     *
     * @throws StripeRequestFailed
     */
    public function cancelSubscriptionAtPeriodEnd(string $subscriptionId, int $businessId): array
    {
        return $this->post(
            '/subscriptions/'.$subscriptionId,
            ['cancel_at_period_end' => 'true'],
            "cancel:{$subscriptionId}",
            $businessId,
        );
    }

    /**
     * Every payment carrying one of our purchase references (decision 3483's
     * sweep).
     *
     * ⛔ **THE PAYMENT INTENT AND NOT THE CHECKOUT SESSION, BECAUSE WE DO NOT KEEP
     * THE SESSION ID.** {@see CreditTopUps::checkoutUrl()} returns Stripe's URL and
     * stores nothing else, and Checkout Sessions cannot be listed by metadata —
     * `GET /v1/checkout/sessions` filters by `customer`, `payment_intent`,
     * `subscription` and `status`, none of which we hold for an uncredited
     * purchase. **The Search API is the only route back to the money**, and it
     * reaches it because `payment_intent_data[metadata][credit_purchase]` puts our
     * reference on the PaymentIntent as well as on the session.
     *
     * Read from Stripe's live Search and Search PaymentIntents references on
     * **2026-08-14**:
     *
     *   endpoint    `GET /v1/payment_intents/search`, one `query` parameter.
     *   metadata    `metadata["<key>"]:"<value>"` is a supported query field for
     *               PaymentIntents and is a **token** type: exact match only.
     *   freshness   *"Don't use search in read-after-write flows … data is
     *               searchable in less than a minute [and] propagation … can be up
     *               to an hour behind during outages."* The caller's minimum age
     *               before a purchase is swept exists for this sentence.
     *   rate limit  20 read operations per second across every search endpoint.
     *
     * ⛔ **`status` IS DELIBERATELY NOT IN THE QUERY, AND PUTTING IT THERE IS THE
     * TRAP.** The same reference: *"the Search API filters using a cached version
     * of the PaymentIntent `status`, but returns data based on the latest
     * version"* — so a query for `status:"succeeded"` can miss a payment that has
     * succeeded, which on this path is a tenant who paid and is never credited.
     * **The filter is the reference alone; the status is read off the object that
     * comes back.**
     *
     * ⚠️ **`expand[]=data.latest_charge` IS NOT A CONVENIENCE.** A refunded payment
     * leaves its PaymentIntent `succeeded` with `amount_received` unchanged — the
     * refund lives on the Charge. Without the expansion this method cannot tell a
     * payment from a payment that was given back, and the caller treats an
     * unexpanded charge as ambiguous rather than as money.
     *
     * ⚠️ **THE REFERENCE IS PATTERN-CHECKED BEFORE IT IS INTERPOLATED.** Ours are
     * minted by `CreditPurchases::reference()` as `b<id>-<random>`; a value
     * carrying a quote would be a query-language injection into somebody else's
     * search, so anything outside that alphabet is refused here rather than sent.
     *
     * @return list<array<string, mixed>> Every matching PaymentIntent. **More than
     *                                    one is possible and is not resolved
     *                                    here** — two payments against one
     *                                    reference is a question for a person.
     *
     * @throws StripeRequestFailed
     */
    public function paymentIntentsForReference(string $reference, int $businessId): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $reference) !== 1) {
            throw StripeRequestFailed::unreachable('unsearchable_reference');
        }

        $body = $this->get('/payment_intents/search', [
            'query' => 'metadata["credit_purchase"]:"'.$reference.'"',
            'expand' => ['data.latest_charge'],

            // Two is enough to *notice* a second payment against one reference,
            // which is all this method promises. Asking for a hundred would cost
            // the same and would invite a caller to try to reconcile them.
            'limit' => 2,
        ], $businessId);

        $data = $body['data'] ?? null;

        if (! is_array($data)) {
            return [];
        }

        return array_values(array_filter($data, is_array(...)));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws StripeRequestFailed
     */
    private function post(string $path, array $params, string $idempotencyKey, int $businessId): array
    {
        $url = self::BASE.$path;

        $this->assertConfigured('POST', $url, $businessId);

        try {
            $response = VendorLog::timed(
                'stripe',
                'POST',
                $url,
                fn (): Response => Http::withToken(PlatformCredentials::get(self::CREDENTIAL))
                    ->withHeaders([
                        'Stripe-Version' => self::API_VERSION,

                        // Stripe's own header, not a body field. It makes a
                        // retry of a request that may already have succeeded
                        // safe for 24 hours — which covers the case this
                        // integration actually meets: a timeout where the
                        // customer was created and the response was lost.
                        'Idempotency-Key' => $idempotencyKey,
                    ])
                    ->timeout((int) config('services.stripe.timeout', 15))
                    ->asForm()
                    ->post($url, $params),
                $businessId,
            );
        } catch (ConnectionException $e) {
            // The class name, never the message: a connection exception's
            // message carries the full request URI (VendorLog's rule).
            VendorLog::failure('stripe', 'POST', $url, $e::class, $businessId);

            throw StripeRequestFailed::unreachable($e::class);
        }

        if ($response->failed()) {
            $failure = StripeRequestFailed::from($response);

            VendorLog::failure('stripe', 'POST', $url, $failure->reason, $businessId);

            throw $failure;
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        return $body;
    }

    /**
     * One read.
     *
     * ⚠️ **NO `Idempotency-Key`, AND ITS ABSENCE IS THE POINT RATHER THAN AN
     * OMISSION.** Stripe's key replays the *response* of a mutating request for 24
     * hours; a reconciliation read that replayed a day-old answer would be worse
     * than no reconciliation at all, because the whole question it asks is what is
     * true **now**. `Stripe-Version` still rides, for decision 684's reason.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws StripeRequestFailed
     */
    private function get(string $path, array $params, int $businessId): array
    {
        $url = self::BASE.$path;

        $this->assertConfigured('GET', $url, $businessId);

        try {
            $response = VendorLog::timed(
                'stripe',
                'GET',
                $url,
                fn (): Response => Http::withToken(PlatformCredentials::get(self::CREDENTIAL))
                    ->withHeaders(['Stripe-Version' => self::API_VERSION])
                    ->timeout((int) config('services.stripe.timeout', 15))
                    ->get($url, $params),
                $businessId,
            );
        } catch (ConnectionException $e) {
            VendorLog::failure('stripe', 'GET', $url, $e::class, $businessId);

            throw StripeRequestFailed::unreachable($e::class);
        }

        if ($response->failed()) {
            $failure = StripeRequestFailed::from($response);

            VendorLog::failure('stripe', 'GET', $url, $failure->reason, $businessId);

            throw $failure;
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        return $body;
    }

    /**
     * Refuse before a socket is opened, in this class's own vocabulary.
     *
     * ⛔ **THE READ WAS INSIDE A `try` THAT CATCHES `ConnectionException` ALONE,
     * WHICH IS NOT THE SAME AS BEING CLASSIFIED — 9294.** It also sits inside
     * the closure `VendorLog::timed()` invokes, so the exception escaped
     * *through* the instrument: `timed()` is `$response = $call();` and then
     * `self::call(...)`, so not even the log line was written. Every `catch
     * (StripeRequestFailed …)` in the tree therefore missed an unset key, and
     * `Account\Credit::buy()` — which catches this class by name — answered a
     * **500** rendered by Livewire as a full-page modal over the credit screen.
     *
     * ⚠️ **THE ABSENCE IS RECORDED HERE**, `GooglePlacesClient::isConfigured()`'s
     * line for the same reason: from here down there is no request to attribute
     * it to. A fixed label, never which key — the Ops credentials board is the
     * authority for that.
     *
     * @throws StripeRequestFailed
     */
    private function assertConfigured(string $method, string $url, int $businessId): void
    {
        if (self::isConfigured()) {
            return;
        }

        VendorLog::failure('stripe', $method, $url, 'credential_missing', $businessId);

        throw StripeRequestFailed::unconfigured();
    }
}
