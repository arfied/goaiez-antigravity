<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\GatewayWebhookReceiver;
use App\Contracts\VerifiesWebhookSenders;
use App\Enums\AuthorizeNetSubscriptionStatus;
use App\Enums\CreditClawbackOutcome;
use App\Enums\CreditReversalCause;
use App\Enums\CreditSettlement;
use App\Enums\GatewayEventOutcome;
use App\Enums\PaymentGateway;
use App\Models\AuthorizeNetEvent;
use App\Models\Business;
use App\Services\Config\DefaultsRegistry;
use App\Support\Money;
use App\Support\PlatformCredentials;
use App\Support\Tenancy;
use App\Support\WebhookMaterial;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use UnexpectedValueException;

/**
 * The one place an inbound Authorize.Net notification is verified, deduplicated
 * and applied.
 *
 * ⚠️ **WEBHOOKS ARE THE SOURCE OF TRUTH ON BOTH GATEWAYS** — decision 2056 says
 * so explicitly, and it decides the shape of everything here exactly as it did
 * for Stripe. Nothing promotes a subscription out of `pending_checkout` except
 * an event that arrived here and verified.
 *
 * ## The signature, verified against the vendor's live documentation (2026-08-11)
 *
 * HMAC-SHA512 over the **raw notification body**, keyed with the merchant's
 * **Signature Key**, delivered in a custom header `X-ANET-Signature`. All of
 * that is in Authorize.Net's own webhooks reference.
 *
 * ⚠️ **THREE DETAILS THE VENDOR'S REFERENCE DOES NOT STATE, AND EVERY ONE OF
 * THEM IS LOAD-BEARING (decision 2141).** The page says only that the hash is
 * "sent in a custom header"; it shows no example value and no sample code.
 * `CLAUDE.md`'s rule is to verify a vendor string against the raw artefact, and
 * here **the raw artefact is silent** — so each of these is handled defensively
 * rather than guessed:
 *
 *   the prefix   The header value is `sha512=<hex>` in practice. Rather than
 *                assume it, this strips an optional `sha512=` and accepts a bare
 *                hex string too. Assuming the prefix and meeting a bare hex —
 *                or the reverse — refuses every genuine notification while the
 *                key is perfectly correct.
 *   the case     Observed uppercase; `hash_hmac()` produces lowercase. Both are
 *                normalised before comparison, because a case mismatch is a
 *                total, silent refusal that looks exactly like a wrong key.
 *   the key      Used as the **raw string** from the merchant interface, not
 *                hex-decoded. ⚠️ The neighbouring `transHashSHA2` transaction
 *                hash *does* require the key hex-decoded, and the two are
 *                documented on the same page — which is how an implementation
 *                ends up decoding this one and refusing everything.
 *
 * ⚠️ **THERE IS NO TIMESTAMP AND THEREFORE NO REPLAY WINDOW.** Stripe's
 * signature covers `timestamp.payload` and its SDK enforces a five-minute
 * tolerance; Authorize.Net's covers the body alone, so a captured notification
 * replays forever. **The deduplication row is the whole replay defence on this
 * gateway** — `authorize_net_events.notification_id` is unique — which is why
 * that table's model refuses deletes in stronger terms than the Stripe one does.
 *
 * ⚠️ **AND THE SIGNATURE IS COMPARED WITH `hash_equals`.** A `===` on two
 * hex strings leaks timing, and this is the one comparison on the endpoint that
 * decides whether an unauthenticated caller may move a subscription.
 *
 * ## Why this hand-rolls the HMAC when the Stripe half does not
 *
 * Decision 680 sends Stripe's verification through `Stripe\Webhook` precisely
 * because hand-rolling an HMAC plus a timestamp tolerance is where a silent bug
 * lives. **Authorize.Net ships no verifier at all** — its PHP SDK has none, and
 * the vendor's own page offers no sample code — so there is nothing to delegate
 * to. What is left is to write the three lines carefully, say what was verified
 * and what was not, and pin the behaviour with tests that drive each refusal.
 */
final class AuthorizeNetWebhooks implements GatewayWebhookReceiver, VerifiesWebhookSenders
{
    /**
     * The signing key every Authorize.Net notification is judged with.
     *
     * ⛔ **IT IS NOT THE TRANSACTION KEY AND THE TWO SIT NEXT TO EACH OTHER IN
     * THE VENDOR'S CONSOLE**, which is exactly how they get swapped — and a
     * swap refuses every webhook while outbound calls keep working.
     * ⚠️ **AND NEITHER OF THEM HAS EVER HAD A LINE IN `.env.example`** (11655):
     * an operator setting this platform up from that file had nothing to fill
     * in for the **primary** gateway's signing key, and the absence lands in
     * this class's collapsed refusal as *"signature verification failed"*.
     */
    public const string CREDENTIAL = 'authorize_net_signature_key';

    /**
     * ⚠️ **THE ENDPOINT THAT CARRIES SUBSCRIPTION STATE**, and its absence is
     * silent in both directions: nothing here retries, and Authorize.Net's own
     * redelivery cannot help a key we do not hold.
     */
    public static function verifyingMaterial(): WebhookMaterial
    {
        return WebhookMaterial::credentials([self::CREDENTIAL]);
    }

    /**
     * The event types this endpoint acts on.
     *
     * ⚠️ **`net.authorize.customer.subscription.failed` IS THE ONE THAT MATTERS
     * AND IT IS THE ONE STRIPE HAS NO EQUIVALENT OF ON THIS PATH.** On Stripe a
     * failed payment moves `customer.subscription.updated` to `past_due` and
     * Stripe retries on its own schedule; here the vendor **does not retry**,
     * and this notification is the only signal that dunning has to start.
     * Missing it means a suspended subscription nobody acts on, which the vendor
     * terminates before the next run date.
     *
     * ⚠️ **`payment.authcapture.created` IS NOW HERE AND ITS OLD EXCLUSION STILL
     * HOLDS FOR SUBSCRIPTIONS.** This note used to read *"not here, on purpose"*,
     * and the argument was that a successful **recurring** charge also moves the
     * subscription's own status, so acting on both would be two writers racing to
     * describe one state. That is untouched: {@see self::applyPayment()} settles a
     * **credit top-up** and nothing else, and a transaction that matches no
     * purchase of ours is filed `Unlinked` without going near a subscription. The
     * event is the only signal a one-off charge produces on this gateway, so
     * refusing it would mean a top-up that could never be credited.
     *
     * ## The two reversal events, read from the vendor's own list (2026-08-20)
     *
     * ⛔ **THERE IS NO CHARGEBACK OR DISPUTE EVENT ON THIS GATEWAY AT ALL.** The
     * whole `net.authorize.*` catalogue was read: nine payment events, six
     * customer and subscription events, six partner boarding events, and not one
     * of them names a chargeback, a dispute or a returned item. **An event name
     * invented to fill that gap would subscribe this endpoint to nothing, with a
     * green suite** — 255/277/684/1349's failure, which this codebase has taken
     * four times. What the vendor does expose is a `transactionStatus` of
     * `chargeback` or `returnedItem` on the *original* transaction, readable only
     * through the Transaction Details API, and `PurchaseReconciliation` already
     * names both — but it asks only about purchases that never credited. Decision
     * 6392 records what that leaves open.
     *
     * ⚠️ **`refund.created` AND `void.created` ARE DIFFERENT FACTS AND BOTH ARE
     * HERE.** A refund returns money from a settled transaction; a void cancels
     * one before the nightly batch closes, so the money never leaves. Either way
     * the credit was written and has to come back, which is why they share a
     * handler and differ only in {@see CreditReversalCause}.
     *
     * @var list<string>
     */
    private const array HANDLED = [
        'net.authorize.customer.subscription.created',
        'net.authorize.customer.subscription.updated',
        'net.authorize.customer.subscription.suspended',
        'net.authorize.customer.subscription.cancelled',
        'net.authorize.customer.subscription.terminated',
        'net.authorize.customer.subscription.expired',
        'net.authorize.customer.subscription.failed',
        'net.authorize.payment.authcapture.created',
        'net.authorize.payment.refund.created',
        'net.authorize.payment.void.created',
    ];

    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly Dunning $dunning,
        private readonly CreditPurchases $purchases = new CreditPurchases,
        private readonly CreditClawbacks $clawbacks = new CreditClawbacks,
    ) {}

    public function gateway(): PaymentGateway
    {
        return PaymentGateway::AuthorizeNet;
    }

    /**
     * Verify a raw request body, or fail.
     *
     * @return array<string, mixed>
     *
     * @throws UnexpectedValueException The signature did not verify.
     * @throws RuntimeException The signing key is not configured.
     */
    public function verify(string $payload, ?string $signature): array
    {
        // ⚠️ THROWS FOR AN ABSENT KEY RATHER THAN SKIPPING THE CHECK, and the
        // controller turns that into a refusal. An endpoint that verifies only
        // when configured is unverified on every deployment's first day —
        // `CredentialManifest` states the symptom for the operator.
        $key = PlatformCredentials::get(self::CREDENTIAL);

        $provided = $this->normaliseSignature($signature);

        if ($provided === null) {
            throw new UnexpectedValueException('The notification carried no usable X-ANET-Signature header.');
        }

        $expected = strtolower(hash_hmac('sha512', $payload, $key));

        if (! hash_equals($expected, $provided)) {
            throw new UnexpectedValueException('The notification signature did not verify.');
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            // Signed with our own key and still not JSON. Not a thing to guess
            // at, and not a thing to retry either.
            throw new UnexpectedValueException('A verified Authorize.Net notification was not a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * ⚠️ The header, reduced to lowercase hex, or null.
     *
     * See the class docblock: the vendor's reference states neither the prefix
     * nor the case, so both are accepted rather than assumed. The one thing this
     * does *not* do is accept anything that is not hex of the right length — a
     * lax parse here is a lax signature check.
     */
    private function normaliseSignature(?string $header): ?string
    {
        if (! is_string($header)) {
            return null;
        }

        $value = trim($header);

        // `sha512=` if it is there, in any case. Nothing else is stripped: a
        // header carrying some other algorithm's name is not a header this
        // endpoint understands, and silently ignoring the prefix would accept
        // one.
        if (preg_match('/^sha512=/i', $value) === 1) {
            $value = substr($value, 7);
        }

        $value = strtolower($value);

        // SHA-512 is 64 bytes — 128 hex characters. Length and alphabet are
        // checked so that a truncated or padded header is refused here rather
        // than surviving to a `hash_equals` that would refuse it anyway but
        // would have read it first.
        return preg_match('/^[0-9a-f]{128}$/', $value) === 1 ? $value : null;
    }

    /**
     * Apply a verified notification exactly once. Null means already done.
     *
     * @param  array<string, mixed>  $event
     */
    public function process(array $event): ?GatewayEventOutcome
    {
        $id = $this->stringOrNull($event['notificationId'] ?? null);
        $type = $this->stringOrNull($event['eventType'] ?? null);

        if ($id === null || $type === null) {
            // Verified by signature and still shapeless. Without an id there is
            // nothing to deduplicate on — and on this vendor the id is the only
            // replay defence there is, because the signature carries no
            // timestamp.
            throw new UnexpectedValueException('A verified Authorize.Net notification carried no id or no type.');
        }

        return DB::transaction(function () use ($id, $type, $event): ?GatewayEventOutcome {
            // The claim is an INSERT, not a SELECT — 350's lesson, that a
            // check-then-insert holds only sequentially. `insertOrIgnore` rather
            // than a caught unique violation, because a failed statement aborts
            // the whole Postgres transaction (383's `25P02`).
            $claimed = AuthorizeNetEvent::query()->insertOrIgnore([
                'notification_id' => $id,
                'type' => $type,
                // A placeholder, overwritten below in this same transaction.
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
        /** @var array<string, mixed> $payload */
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];

        if ($type === 'net.authorize.payment.authcapture.created') {
            return $this->applyPayment($payload);
        }

        if ($type === 'net.authorize.payment.refund.created') {
            return $this->applyReversal($payload, CreditReversalCause::Refunded);
        }

        if ($type === 'net.authorize.payment.void.created') {
            return $this->applyReversal($payload, CreditReversalCause::Voided);
        }

        $subscriptionId = $this->stringOrNull($payload['id'] ?? null)
            ?? $this->stringOrNull($payload['subscriptionId'] ?? null);

        if ($subscriptionId === null) {
            return GatewayEventOutcome::Unlinked;
        }

        $businessId = $this->subscriptions->businessIdForAuthorizeNetSubscription($subscriptionId);

        if ($businessId === null) {
            // ⚠️ ORDINARY, NOT AN ERROR — the same reading `Unlinked` has on the
            // Stripe path. One merchant account serves whatever else it serves.
            // A *sustained* run of these means the index has lost rows, which is
            // a real defect; a scattering means nothing.
            return GatewayEventOutcome::Unlinked;
        }

        $observedAt = $this->observedAt($event);

        return Tenancy::actingAs($businessId, function () use (
            $businessId,
            $type,
            $payload,
            $subscriptionId,
            $observedAt,
        ): GatewayEventOutcome {
            $business = Business::query()->find($businessId);

            if (! $business instanceof Business) {
                return GatewayEventOutcome::Unlinked;
            }

            if ($type === 'net.authorize.customer.subscription.failed') {
                return $this->openDunning($business, $observedAt);
            }

            return $this->applyStatus($business, $type, $payload, $subscriptionId, $observedAt);
        });
    }

    /**
     * `net.authorize.payment.authcapture.created` — a captured charge, which for
     * us means a credit top-up and nothing else (the funder, 3102).
     *
     * ## What the payload actually carries, read from the vendor (2026-08-14)
     *
     * The reference's own sample notification for this event is, verbatim in the
     * fields that matter: `payload.id` (the transaction id), `payload.authAmount`
     * (**a decimal number of dollars, not cents**), `payload.responseCode`,
     * `payload.merchantReferenceId` and `payload.entityName`. ⛔ **There is no
     * `invoiceNumber` in it**, which matters because `order.invoiceNumber` is the
     * field a from-memory implementation would correlate on — and it would resolve
     * nothing, on every notification, silently.
     *
     * ⚠️ **`merchantReferenceId` IS OUR `refId` COMING BACK, AND IT HAS A PUBLIC
     * HISTORY OF GOING MISSING.** It is documented as the way to match a
     * notification to the request that caused it, and the vendor's own community
     * carries reports of the field disappearing from live payloads for days at a
     * time. **So it is the fallback and not the handle**: the transaction id we
     * recorded from the synchronous charge response is asked first, because that
     * one is on the row before the notification exists.
     *
     * ⛔ **`authAmount` IS DOLLARS AND EVERYTHING IN THIS APPLICATION IS INTEGER
     * CENTS.** `45.00` in the vendor's own sample. It is converted here, once, by
     * string arithmetic rather than by `(int) ($amount * 100)` — a float multiply
     * on `0.29` gives `28`, which would turn every settlement into a mismatch and
     * would do it only for some amounts.
     *
     * ⚠️ **AND THE AMOUNT IS NEVER WHAT DECIDES WHAT TO CREDIT.** It is handed to
     * {@see CreditPurchases::settle()} to be compared against the price recorded
     * when the purchase was opened.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyPayment(array $payload): GatewayEventOutcome
    {
        $transactionId = $this->stringOrNull($payload['id'] ?? null);
        $reference = $this->stringOrNull($payload['merchantReferenceId'] ?? null);

        $index = $this->purchases->referenceFor($transactionId, $reference);

        if ($index === null || $transactionId === null) {
            // ⚠️ ORDINARY, NOT AN ERROR. One merchant account serves whatever
            // else it serves, and every recurring subscription charge lands here
            // too — those are handled through the subscription's own status
            // events and must not be touched from this branch.
            return GatewayEventOutcome::Unlinked;
        }

        $paid = $this->authAmount($payload);

        if (! $paid instanceof Money) {
            // Signed by our own key, matching a purchase of ours, and carrying no
            // readable amount. Nothing is credited: the comparison this
            // settlement turns on cannot be made, and crediting anyway would be
            // the free-credit hole with an extra step.
            Log::warning('authorize.net payment notification carried no readable amount', [
                'authorize_net_transaction_id' => $transactionId,
            ]);

            return GatewayEventOutcome::Unmodelled;
        }

        return Tenancy::actingAs(
            $index->business_id,
            fn (): GatewayEventOutcome => match ($this->purchases->settle($index->reference, $transactionId, $paid)) {
                CreditSettlement::Credited => GatewayEventOutcome::Applied,
                // ⚠️ A REPLAY IS A SUCCESS. This gateway's signature carries no
                // timestamp, so a captured notification replays for ever and the
                // deduplication row is the whole replay defence — but two
                // *different* notifications can describe one payment, which is
                // what the purchase claim underneath this catches.
                CreditSettlement::AlreadySettled => GatewayEventOutcome::Superseded,
                CreditSettlement::Unknown => GatewayEventOutcome::Unlinked,
                CreditSettlement::Mismatched => GatewayEventOutcome::Unmodelled,
            },
        );
    }

    /**
     * `net.authorize.payment.refund.created` / `.void.created` — the money went
     * back, so the credit it bought does too.
     *
     * ## ⛔ The vendor's reference is SILENT on this payload, exactly as it is on
     * the signature header (2141)
     *
     * Its webhooks page shows **one** payment sample — the authcapture one quoted
     * in {@see self::applyPayment()} — and gives no example for a refund or a
     * void. Read 2026-08-20. So what follows is handled defensively rather than
     * guessed, and the guesses that were *not* made are the point:
     *
     *   the handle    ⛔ **`payload.id` IS NOT ASSUMED TO BE THE ORIGINAL
     *                 TRANSACTION.** A refund on this gateway is issued with
     *                 `refTransId` naming the original and produces a
     *                 transaction of its own, so `payload.id` on a refund
     *                 notification is very likely the **refund's** id and matches
     *                 nothing we hold. A void modifies the original and its id
     *                 plausibly *is* ours. Both are simply offered to
     *                 {@see CreditPurchases::referenceFor()}, which is the same
     *                 resolution the settlement path uses — and a miss is
     *                 `Unlinked` with a warning, never a fallback that reaches
     *                 for the nearest purchase.
     *   the reference `merchantReferenceId` is our `refId` coming back, and it is
     *                 the `refId` of **the request that created the transaction
     *                 being described**. A refund raised by a person in the
     *                 Merchant Interface has no `refId` of ours at all, so on
     *                 that — the ordinary case, since nothing here issues
     *                 refunds — this resolves nothing either. Decision 6393.
     *   the amount    `authAmount`, through the same converter the settlement
     *                 path uses, because it is the same decimal-dollars field and
     *                 a second conversion is how the factor drifts.
     *
     * ⚠️ **SO AN UNRESOLVED REVERSAL IS THE EXPECTED OUTCOME HERE AND IS LOGGED
     * LOUDLY.** On a settlement, `Unlinked` is ordinary and means somebody else's
     * traffic. Here it means money was returned and the credit may still be
     * sitting in a balance, which is the whole defect this class exists to close
     * arriving through the vendor's own documentation gap.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyReversal(array $payload, CreditReversalCause $cause): GatewayEventOutcome
    {
        $transactionId = $this->stringOrNull($payload['id'] ?? null);
        $reference = $this->stringOrNull($payload['merchantReferenceId'] ?? null);

        $index = $this->purchases->referenceFor($transactionId, $reference);

        if ($index === null) {
            Log::warning('a reversed authorize.net payment matched no credit purchase', [
                'authorize_net_transaction_id' => $transactionId,
                'cause' => $cause->value,
            ]);

            return GatewayEventOutcome::Unlinked;
        }

        // Null when the payload carried no amount this application can read
        // exactly. Handed on rather than refused here, because `CreditClawbacks`
        // is where "the comparison cannot be made" is a recorded outcome instead
        // of a second refusal in a second place.
        $reversed = $this->authAmount($payload);

        return Tenancy::actingAs(
            $index->business_id,
            fn (): GatewayEventOutcome => match ($this->clawbacks->reverse(
                $index->reference,
                $reversed,
                $cause,
                'gateway:'.PaymentGateway::AuthorizeNet->value,
            )) {
                // ⚠️ A SHORTFALL IS `Applied`. The handler took what was there and
                // recorded what was not; redelivering finds the same empty
                // balance. The record that money was lost is the audit entry.
                CreditClawbackOutcome::Clawed,
                CreditClawbackOutcome::Shortfall => GatewayEventOutcome::Applied,
                // A replay, or a second notification describing one reversal.
                CreditClawbackOutcome::Nothing => GatewayEventOutcome::Superseded,
                CreditClawbackOutcome::Unknown => GatewayEventOutcome::Unlinked,
                CreditClawbackOutcome::NotCredited,
                CreditClawbackOutcome::Unreadable => GatewayEventOutcome::Unmodelled,
            },
        );
    }

    /**
     * `payload.authAmount` — the vendor's decimal dollars — as integer cents.
     *
     * ⛔ **NO FLOAT ARITHMETIC ON MONEY** (`18` §Money handling, row 22's
     * build-failing gate). `(int) (0.29 * 100)` is `28` in IEEE-754 and the error
     * appears for some amounts and not others, which is the worst possible
     * distribution for a comparison that decides whether somebody is credited.
     * The value is normalised as a string and split on the decimal point.
     *
     * ⚠️ **THE CURRENCY IS THE MERCHANT ACCOUNT'S AND THE PAYLOAD DOES NOT STATE
     * IT.** It is taken from `billing.currency`, which is the same key the price
     * this is compared against was quoted in — so a mismatch in currency is
     * impossible by construction rather than checked. That is a real limitation
     * the day a second merchant account in another currency exists (2058's
     * multi-currency, unbuilt), and it is written down rather than assumed away.
     *
     * @param  array<string, mixed>  $payload
     */
    private function authAmount(array $payload): ?Money
    {
        $raw = $payload['authAmount'] ?? null;

        if (! is_int($raw) && ! is_float($raw) && ! is_string($raw)) {
            return null;
        }

        // `number_format` on the float the JSON decoder produced, to two places,
        // is a string conversion rather than an arithmetic one: the rounding
        // happens in the formatter at a fixed scale instead of in a multiply
        // whose error depends on the value.
        $normalised = is_string($raw) ? trim($raw) : number_format((float) $raw, 2, '.', '');

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $normalised, $matches) !== 1) {
            return null;
        }

        $cents = (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '0', 2, '0');

        $currency = app(DefaultsRegistry::class)->value('billing.currency');

        if (! is_string($currency) || $currency === '') {
            return null;
        }

        return Money::of($cents, $currency);
    }

    /**
     * A declined recurring payment. **The vendor will not retry it.**
     *
     * ⚠️ **THE ONLY HANDLER HERE THAT DOES SOMETHING RATHER THAN RECORDING
     * SOMETHING**, and the asymmetry is the vendor's rather than ours: on
     * Stripe, `past_due` is a state Stripe is already working on, so projecting
     * it is the whole job. Here nothing is working on it until we start.
     */
    private function openDunning(Business $business, Carbon $observedAt): GatewayEventOutcome
    {
        $this->subscriptions->markAuthorizeNetPastDue($business, $observedAt);

        $this->dunning->open($business);

        return GatewayEventOutcome::Applied;
    }

    /**
     * `net.authorize.customer.subscription.*` — the projection.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyStatus(
        Business $business,
        string $type,
        array $payload,
        string $subscriptionId,
        Carbon $observedAt,
    ): GatewayEventOutcome {
        $status = AuthorizeNetSubscriptionStatus::fromVendor(
            $this->stringOrNull($payload['status'] ?? null)
        ) ?? $this->statusFromEventType($type);

        // ⚠️ REFUSED RATHER THAN ROUNDED, and answered 2xx rather than retried —
        // `SubscriptionStatus`'s standing promise. Redelivering will not make
        // the status one we model; what is needed is a person, and the row in
        // `authorize_net_events` is that record.
        if (! $status instanceof AuthorizeNetSubscriptionStatus) {
            Log::warning('authorize.net subscription in an unmodelled state', [
                'authorize_net_subscription_id' => $subscriptionId,
                'event_type' => $type,
            ]);

            return GatewayEventOutcome::Unmodelled;
        }

        $applied = $this->subscriptions->applyAuthorizeNetSubscription(
            $business,
            $subscriptionId,
            $status,
            $observedAt,
        );

        if ($applied) {
            /*
             * ⚠️ AN OPEN SCHEDULE ENDS ON EVERY STATUS THAT ENDS THE
             * SUBSCRIPTION, NOT ONLY ON `active` (decision 2685).
             *
             * Until this slice only the recovery arm existed, so a
             * `subscription.cancelled` — or a `terminated`, which is what this
             * vendor does to a suspended subscription nobody acts on — left the
             * schedule stepping through its remaining attempts and eventually
             * calling `suspendForNonPayment()` on a row that was already
             * `Canceled`. **The distinction is the deliverable**: a dunning
             * history is read during a billing dispute, and "the gateway
             * cancelled it" and "we exhausted our retries" are different facts
             * about who ended the relationship.
             *
             * ⚠️ THE `default => null` ARM IS THE INTERESTING ONE. `suspended` is
             * exactly the state dunning exists for and must leave the schedule
             * running; a `match` with no default would have made that a compile
             * error rather than a decision, and one with a wrong default would
             * close the schedule on the notification that opens it.
             */
            match ($status) {
                AuthorizeNetSubscriptionStatus::Active => $this->dunning->closeIfOpen($business),
                AuthorizeNetSubscriptionStatus::Canceled,
                AuthorizeNetSubscriptionStatus::Terminated,
                AuthorizeNetSubscriptionStatus::Expired => $this->dunning->closeAsCanceledAtGateway($business),
                AuthorizeNetSubscriptionStatus::Suspended => null,
            };
        }

        return $applied ? GatewayEventOutcome::Applied : GatewayEventOutcome::Superseded;
    }

    /**
     * The status a notification implies when its payload does not carry one.
     *
     * ⚠️ **NOT A GUESS — THE EVENT TYPE *IS* THE STATUS FOR FOUR OF THESE, AND
     * THE PAYLOAD IS OFTEN THINNER THAN THE DOCUMENTATION'S EXAMPLE.**
     * `net.authorize.customer.subscription.suspended` cannot mean anything other
     * than suspended. The two that genuinely need the payload — `created` and
     * `updated` — deliberately return null here, because for those the type
     * carries no status at all and inventing `active` would reinstate a
     * subscription an `updated` was reporting the cancellation of.
     */
    private function statusFromEventType(string $type): ?AuthorizeNetSubscriptionStatus
    {
        return match ($type) {
            'net.authorize.customer.subscription.suspended' => AuthorizeNetSubscriptionStatus::Suspended,
            'net.authorize.customer.subscription.cancelled' => AuthorizeNetSubscriptionStatus::Canceled,
            'net.authorize.customer.subscription.terminated' => AuthorizeNetSubscriptionStatus::Terminated,
            'net.authorize.customer.subscription.expired' => AuthorizeNetSubscriptionStatus::Expired,
            default => null,
        };
    }

    /**
     * The notification's own `eventDate` — the watermark.
     *
     * ⚠️ **AN ISO 8601 STRING, NOT A UNIX TIMESTAMP.** Stripe sends `created` as
     * an integer and the Stripe handler reads it as one; Authorize.Net sends
     * `eventDate` as `2026-08-11T07:56:42.1234567Z`. A from-memory
     * implementation that reuses the integer reader gets null and silently loses
     * every watermark, which turns out-of-order delivery back into the defect
     * decision 692 closed.
     *
     * Falls back to now when absent or unparseable — accepting the event rather
     * than discarding it, which is the right direction: a missing timestamp
     * makes ordering unknowable, and refusing every such event would mean
     * refusing every event if the vendor renamed the field.
     *
     * @param  array<string, mixed>  $event
     */
    private function observedAt(array $event): Carbon
    {
        $raw = $this->stringOrNull($event['eventDate'] ?? null);

        if ($raw === null) {
            return Carbon::now();
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return Carbon::now();
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        // The vendor returns a subscription id as a JSON number in some payload
        // shapes and a string in others — the same normalisation the API client
        // makes, for the same reason.
        return is_int($value) ? (string) $value : null;
    }

    /**
     * Write what actually happened onto the claim row.
     *
     * Loaded and saved as a model rather than updated through the builder, so
     * that the append-only guard is genuinely on this path. A builder update
     * would bypass it, and a guard the only writer routes around is decision
     * 314–316's "a protection layer asserted before it is true".
     */
    private function record(string $notificationId, GatewayEventOutcome $outcome): void
    {
        $row = AuthorizeNetEvent::query()->where('notification_id', $notificationId)->first();

        if ($row instanceof AuthorizeNetEvent) {
            $row->outcome = $outcome;
            $row->save();
        }
    }
}
