<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\Billing\GatewayRequestFailure;
use App\Livewire\Account\Credit;
use App\Services\Billing\AuthorizeNetApi;
use RuntimeException;

/**
 * An outbound Authorize.Net call did not succeed.
 *
 * ⚠️ **AUTHORIZE.NET ANSWERS 200 FOR ALMOST EVERYTHING, INCLUDING FAILURES, AND
 * THAT IS THE SINGLE MOST IMPORTANT FACT ON THIS PATH (decision 2136).**
 * Verified against the vendor's live API reference (read 2026-08-11): every
 * request posts to one URL, and the outcome lives in the body's
 * `messages.resultCode` (`Ok` or `Error`) with detail in `messages.message[]`.
 * A `$response->failed()` check — the one this codebase's Stripe client uses,
 * correctly, for a vendor with HTTP semantics — **would treat a declined card, a
 * bad API login and a duplicate subscription as successes.** The symptom is a
 * subscription id read out of a body that has none, which is a null on the
 * column that decides whether anybody is billed.
 *
 * ⚠️ **THE REASON IS THE `errorCode`, NEVER THE `text`.** The text field is
 * written for a person and routinely quotes the value it rejected — a card's
 * last four, an email address, a customer profile description. This message
 * reaches logs and error trackers, which is `StripeRequestFailed`'s rule and
 * `VendorLog`'s, and it does not weaken for a second vendor.
 */
final class AuthorizeNetRequestFailed extends RuntimeException implements GatewayRequestFailure
{
    /**
     * Error codes that mean an operator has to paste a credential, not that a
     * developer has to fix a request.
     *
     * ⚠️ **`E00007` IS THE ONE THAT MATTERS AND IT IS THE ONE THAT LOOKS LIKE A
     * CHECKOUT BUG.** "User authentication failed due to invalid authentication
     * values" is what an expired or mistyped transaction key produces, and
     * without this distinction it would surface to a person mid-signup as
     * "payment is unavailable" — indistinguishable from a vendor outage, which
     * waiting *would* fix. `E00124` is the same shape for a merchant account
     * whose permissions changed underneath us.
     *
     * @var list<string>
     */
    private const array CONFIGURATION_CODES = ['E00007', 'E00124', 'E00006'];

    /**
     * Error codes that mean **we sent a request the vendor could not accept**.
     *
     * ⛔ **A THIRD CATEGORY, AND IT EXISTS BECAUSE THE OTHER TWO WERE BOTH
     * WRONG FOR IT.** `E00014` — *"A required field is not present"* — is what
     * every `ARBCreateSubscriptionRequest` came back with while both profile
     * creators sent no `billTo`, which is why no tenant had ever completed a
     * checkout. It is not in {@see self::CONFIGURATION_CODES}, so
     * `AuthorizeNetCheckoutController::refusal()` fell through to *"That card
     * could not be accepted. Please check the details or try another card."* —
     * ⛔ **the exact sentence that method's own docblock says must never be
     * shown for a fault of ours**, said to somebody whose card was fine.
     *
     * ⛔ **AND ADDING IT TO THE CONFIGURATION LIST WOULD HAVE BEEN THE SMALLER
     * WRONG ANSWER.** That list's own sentence is *"an operator has to paste a
     * credential, not that a developer has to fix a request"*, and **every
     * surface that can see this class reads `configuration`** — three of them
     * to decide what to say and all four to decide what to log. ⚠️ **This
     * sentence said *"four surfaces branch on it"* and the two verbs are not
     * the same count** (12249): `Account\CancelSubscriptionController` only
     * logs, because its customer sentence promises a record rather than a
     * remedy and is right either way. **The property is that no reader of this
     * class ignores the flag**; a census of which ones branch is `grep`'s to
     * run and went stale inside one wave.
     * A malformed request in that bucket sends an operator to a credentials
     * board where nothing is wrong, and the log line saying `configuration:
     * true` is what they would act on. **The customer copy converges — neither
     * is their fault and neither is worth a retry — and the operator's next
     * move is the thing the two categories disagree about.**
     *
     * ✅ **`E00013` HAS MOVED HERE FROM THE CONFIGURATION LIST, AND THE ORDER
     * IS THE WHOLE OF WHY IT TOOK TWO WAVES** (11965, 12243). *"The field is
     * invalid"* is a request-shape fault by any reading, and 11965 left it above
     * anyway because **{@see Credit} read `configuration` and knew nothing of
     * this flag**: moving it first would have dropped an `E00013` on a credit
     * top-up from *"we cannot take payments just now"* to the card-blaming
     * sentence — this category's own defect, reintroduced one arm over, on the
     * one surface where a customer reads the difference.
     *
     * ⚠️ **THE OTHER THREE SURFACES ARE INDIFFERENT TO THE MOVE AND THAT IS THE
     * TRAP.** `AuthorizeNetCheckoutController::refusal()` and
     * `Account\Plan::vendorRefusal()` return the **same sentence** from both
     * arms, and `CancelSubscriptionController` only logs — so a change made in
     * the wrong order looks entirely safe from three of the four places you
     * would go to check it. `tests/Feature/Billing/GatewayFaultBlameTest.php`
     * drives the fourth, and asserts `configuration` is now **false** on this
     * code, so nothing can read that test as passing because the older arm
     * caught it.
     *
     * @var list<string>
     */
    private const array MALFORMED_REQUEST_CODES = ['E00003', 'E00013', 'E00014', 'E00015', 'E00016', 'E00093'];

    private function __construct(
        public readonly string $reason,
        public readonly bool $retryable,
        public readonly bool $configuration,
        public readonly bool $outcomeUnknown,
        public readonly bool $clientRefused = false,
        public readonly bool $malformedRequest = false,
    ) {
        parent::__construct("Authorize.Net request failed: {$reason}");
    }

    /**
     * Classify an `Error` result the vendor actually returned.
     *
     * @param  list<string>  $errorCodes  `messages.message[].code`, in order.
     */
    public static function fromResult(array $errorCodes): self
    {
        $reason = $errorCodes[0] ?? 'unknown_error';

        return new self(
            reason: $reason,
            // ⚠️ NOTHING IS RETRYABLE HERE, DELIBERATELY. An `Error` resultCode
            // means the vendor read the request and refused it: replaying it
            // produces the same refusal, and on a charge a blind retry is how a
            // customer is billed twice. Transport failures are the retryable
            // case and they come through unreachable() below.
            retryable: false,
            configuration: in_array($reason, self::CONFIGURATION_CODES, true),
            // ⛔ THE VENDOR READ THE REQUEST AND SAID NO, SO NOTHING HAPPENED.
            // This is the one of the three constructors where a caller may
            // truthfully tell somebody that nothing was charged.
            outcomeUnknown: false,
            // ⛔ THE ONLY CONSTRUCTOR THAT CAN SET IT, BECAUSE IT IS THE ONLY
            // ONE WHERE THE VENDOR READ OUR REQUEST AND OBJECTED TO ITS SHAPE.
            // An unreachable vendor and an unset credential say nothing about
            // whether the body was well formed.
            malformedRequest: in_array($reason, self::MALFORMED_REQUEST_CODES, true),
        );
    }

    /**
     * The body came back shaped in a way we cannot read at all.
     *
     * ⚠️ **A SEPARATE CASE FROM AN `Error` RESULT, BECAUSE THE REMEDIES ARE
     * OPPOSITE.** A refusal is finished; an unreadable body is either the vendor
     * changing a contract or a proxy in the way, and both need a person. It is
     * not retryable for the same reason as above: we have no evidence about
     * whether the thing we asked for happened.
     *
     * ⚠️ **AUTHORIZE.NET'S JSON RESPONSES CARRY A UTF-8 BYTE-ORDER MARK.** It is
     * a documented long-standing quirk rather than a fault, and `json_decode()`
     * returns null on it — so a client that does not strip it meets this
     * exception on *every* call while the vendor is working perfectly.
     * {@see AuthorizeNetApi} strips it; this note is here
     * because the symptom points nowhere near the cause.
     */
    public static function unreadable(): self
    {
        return new self(
            reason: 'unreadable_response',
            retryable: false,
            configuration: false,
            // ⛔ "WE HAVE NO EVIDENCE ABOUT WHETHER THE THING WE ASKED FOR
            // HAPPENED" — this constructor's own words, above, and now a flag a
            // caller can branch on rather than a sentence a caller has to read.
            // ⚠️ `retryable` IS NOT THAT FLAG AND MUST NOT BE READ AS IT: this
            // case is unknown *and* not retryable, which is precisely the pair
            // that makes a charge dangerous to repeat and dishonest to write off.
            outcomeUnknown: true,
        );
    }

    /**
     * A credential this client needs is not set, so nothing was attempted.
     *
     * ⛔ **THE FAULT THIS CLASS COULD NOT EXPRESS, AND EVERY `catch` WRITTEN
     * AGAINST IT THEREFORE MISSED — 9294.** {@see AuthorizeNetApi} built its
     * `merchantAuthentication` block **before** the `try`, from two
     * `PlatformCredentials::get()` calls that raise a bare `RuntimeException`.
     * So an unset key escaped unclassified past three catch sites written for
     * this class: `Account\CancelSubscriptionController` answered **500 on the
     * statutory cancel path**, `Account\Credit` answered 500, and the two
     * checkout controllers fell through to a `RuntimeException` arm that
     * redirects **with no message at all**.
     * {@see GbpRequestFailed::unconfigured()} is the same constructor for the
     * same fault on the Google path (9145); the two payment gateways are what
     * that wave did not reach.
     *
     * ⚠️ **NOT RETRYABLE.** A backoff ladder cannot paste a key, and every
     * caller here reads `retryable` to decide whether to say *"try again"*.
     *
     * ⛔ **`outcomeUnknown: false` IS A CLAIM ABOUT MONEY AND IT IS THE
     * STRONGEST ONE AVAILABLE**: the request never left this process, so a
     * caller may truthfully say nothing was charged. ⚠️ **`clientRefused` IS A
     * DIFFERENT CLAIM AND BOTH ARE NEEDED.** {@see self::fromResult()} also sets
     * `outcomeUnknown: false` and is the vendor's own verdict; only this
     * constructor means *nobody at the other end had an opinion*, and a caller
     * writing *"Authorize.Net refused the charge"* onto a `credit_purchases` row
     * would otherwise be putting words in a vendor's mouth about a request it
     * never saw. ⚠️ `configuration` cannot do that job either — it is also true
     * of `E00007`, which is a key that WAS sent and rejected.
     */
    public static function unconfigured(): self
    {
        return new self(
            reason: 'platform_credential_missing',
            retryable: false,
            configuration: true,
            outcomeUnknown: false,
            clientRefused: true,
        );
    }

    /**
     * A transport failure — nothing came back to classify.
     *
     * `$reason` is a class name or a fixed label, never an exception message: a
     * connection exception's message carries the full request URI.
     */
    public static function unreachable(string $reason): self
    {
        return new self(
            reason: $reason,
            retryable: true,
            configuration: false,
            // ⛔ THE REQUEST MAY HAVE ARRIVED AND THE ANSWER MAY HAVE BEEN LOST.
            // On a read that is merely a retry; on a charge it is money that may
            // already have moved, and a caller that records it as "failed" both
            // tells the cardholder something untrue and takes the row out of the
            // reconciliation sweep's reach.
            outcomeUnknown: true,
        );
    }
}
