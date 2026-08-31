<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\Billing\GatewayRequestFailure;
use App\Livewire\Account\Credit;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * An outbound Stripe call did not succeed.
 *
 * Modelled on {@see GbpRequestFailed} and {@see ProviderRequestFailed}: a
 * machine-readable reason, a retryable flag, and **no response body**, because
 * Stripe's error envelope quotes the parameters it rejected and those include a
 * customer's email address and the business's own name.
 *
 * ⚠️ **THE `configuration` FLAG IS THE ONE THAT EARNS ITS PLACE.** Stripe
 * answers 401 for a missing or wrong secret key and 400 for a malformed
 * request, and the remedies have nothing in common: one is an operator pasting
 * a key into Ops, the other is a bug in this repository. Both would otherwise
 * surface to a person mid-signup as "payment is unavailable", and only one of
 * them is something waiting will fix.
 *
 * ⛔ **AND THE SENTENCE ABOVE DESCRIBED A DISTINCTION THIS CLASS COULD NOT
 * MAKE.** *"400 for a malformed request"* had **no flag**: `from()` produced
 * `retryable: false, configuration: false` for it, which is byte for byte what
 * a **declined card** produces — so the two screens catching this class in a
 * union with {@see AuthorizeNetRequestFailed} could not tell *"we sent Stripe
 * something it could not read"* from *"this person's card was refused"*, and
 * {@see Credit} told the buyer to check their card for both. **A docblock naming
 * a category the type cannot express is the category being carried in prose**,
 * which is `BUILDER-PROMPT` §19's rule from the other side.
 * {@see GatewayRequestFailure} is where the shape is now written down.
 *
 * ⚠️ **THE EVIDENCE FOR THE `invalid_request_error` PREDICATE IS THIS TREE'S OWN
 * FIXTURES AND NOT A FETCHED VENDOR DOCUMENT.** The lane that wrote it had no
 * network tool, and it says so rather than implying a fetch: `BillingCheckoutTest`
 * carries Stripe's shape for a rejected parameter (400, `parameter_invalid_empty`)
 * **and** for an expired key (401, `api_key_expired`) — and both come back typed
 * `invalid_request_error`, which is exactly why the status has to be consulted.
 * ⛔ **What is NOT established here is the complete list of statuses that type
 * can arrive with**; a 404 `resource_missing` is classified as malformed by this
 * predicate, and that is deliberate — it is still a request of ours naming an
 * object that is not there, and it is still not the buyer's card.
 */
final class StripeRequestFailed extends RuntimeException implements GatewayRequestFailure
{
    private function __construct(
        public readonly int $status,
        public readonly string $reason,
        public readonly bool $retryable,
        public readonly bool $configuration,
        public readonly bool $clientRefused = false,
        public readonly bool $malformedRequest = false,
    ) {
        parent::__construct("Stripe request failed ({$status}): {$reason}");
    }

    /**
     * Classify a response Stripe actually returned.
     *
     * The reason comes from the stable `error.code`, falling back to
     * `error.type`. ⚠️ **Never `error.message`** — that field is written for a
     * person, is documented as safe to show to a user, and routinely embeds the
     * offending value: "No such customer: cus_…", or the email that failed
     * validation. This message reaches logs and error trackers.
     */
    public static function from(Response $response): self
    {
        $status = $response->status();

        $code = $response->json('error.code');
        $type = $response->json('error.type');

        $reason = is_string($code) && $code !== ''
            ? $code
            : (is_string($type) && $type !== '' ? $type : 'unknown_error');

        $configuration = $status === 401 || $status === 403;

        return new self(
            status: $status,
            reason: $reason,
            // 429 is Stripe's rate limit and 5xx is theirs to fix. A 402
            // (card declined) is emphatically NOT retryable: the same request
            // will be declined again, and Stripe's own guidance is that the
            // customer must act.
            retryable: $status === 429 || $status >= 500,
            configuration: $configuration,
            // ⛔ **THE TYPE ALONE CANNOT ANSWER THIS AND THE STATUS IS WHAT
            // SEPARATES THEM — THIS TREE ALREADY HELD THE PROOF.**
            // `BillingCheckoutTest`'s *"a bad key is classified as configuration
            // rather than as something to retry"* fakes Stripe's own answer to
            // an expired secret key: **`type: invalid_request_error`, code
            // `api_key_expired`, status 401.** So a predicate on the type would
            // report an operator's unpasted credential as *"we sent a request
            // the vendor could not accept"*, which is the wrong half of the log
            // line and sends nobody anywhere useful. `configuration` is asked
            // first and wins.
            //
            // ⚠️ **A `card_error` IS THE ONE CASE THAT IS GENUINELY THE
            // BUYER'S** — Stripe's 402 — and it is the only reason the
            // card-blaming sentence has a population on this gateway at all.
            // Everything else in `invalid_request_error` is a parameter we
            // sent or an object we named, and none of it is theirs.
            malformedRequest: ! $configuration && $type === 'invalid_request_error',
        );
    }

    /**
     * The platform secret key is not set, so nothing was attempted.
     *
     * ⛔ **THE FAULT THIS CLASS COULD NOT EXPRESS, AND EVERY `catch` WRITTEN
     * AGAINST IT THEREFORE MISSED — 9294.** `PlatformCredentials::get()` raises
     * a bare `RuntimeException`, and it was raised *inside* the closure handed
     * to `VendorLog::timed()`, under a `try` that catches `ConnectionException`
     * alone. So an unset key escaped `StripeApi` unclassified: `Account\Credit`
     * catches this class and `CreditPurchaseRefused` and `CreditChargeUnconfirmed`,
     * none of which it was, and the press answered **500** — Livewire renders a
     * failed update in a full-page modal, so the person met Laravel's error page
     * over the credit screen. {@see GbpRequestFailed::unconfigured()} is the same
     * constructor for the same fault on the Google path, built at 9145; the two
     * payment gateways were the ones that wave did not reach.
     *
     * ⚠️ **NOT RETRYABLE, AND `configuration` IS WHAT A CALLER SHOWS SOMEBODY.**
     * A backoff ladder cannot paste a key, and *"try again"* on this fault sends
     * a customer round a loop nothing they do can break.
     *
     * ⛔ **`clientRefused` IS THE FLAG THAT SAYS NOTHING WAS SENT**, and it is
     * 9145's flag with 9145's meaning. `configuration` cannot do that job: it is
     * also true of a 401 from Stripe, which is a key that was *sent and
     * rejected*. A caller deciding whether to fail a `credit_purchases` row
     * needs to know whether a Checkout Session might exist, and only this
     * separates the two.
     */
    public static function unconfigured(): self
    {
        return new self(
            status: 0,
            reason: 'platform_credential_missing',
            retryable: false,
            configuration: true,
            clientRefused: true,
        );
    }

    /**
     * A transport failure — nothing came back to classify.
     *
     * `$reason` is a class name or a fixed label, never an exception message:
     * a connection exception's message carries the full request URI, and the
     * rule that VendorLog documents binds here for the same reason.
     */
    public static function unreachable(string $reason): self
    {
        return new self(
            status: 0,
            reason: $reason,
            retryable: true,
            configuration: false,
        );
    }
}
