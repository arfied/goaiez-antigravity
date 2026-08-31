<?php

declare(strict_types=1);

namespace App\Contracts\Billing;

use App\Exceptions\AuthorizeNetRequestFailed;
use App\Exceptions\StripeRequestFailed;
use App\Http\Controllers\Account\CancelSubscriptionController;
use App\Livewire\Account\Credit;
use Throwable;

/**
 * What a screen catching either card gateway may ask before it writes a sentence.
 *
 * ⛔ **THE SECOND CONTRACT IN DECISION 2056's "ONE GATEWAY ABSTRACTION", AND
 * {@see GatewayWebhookReceiver}'s DOCBLOCK SAYS IT WAS THE ONLY ONE — SO THIS
 * OWES THAT SENTENCE AN ANSWER.** That refusal is about **clients**: checkout
 * does not unify, because Stripe redirects to its own domain and Accept.js keeps
 * the form on ours, so a `checkout()` on an interface *"would have one method per
 * gateway wearing a shared name, which is worse than no interface — it reads as
 * a seam and is a coincidence."* ⚠️ **This is the other half of that test and it
 * passes it**: the question *"did the vendor read our request and object to its
 * shape"* is one question, both vendors answer it, and **two screens already ask
 * it of both in one `catch`.**
 *
 * ⛔ **THE COINCIDENCE WAS ALREADY LOAD-BEARING AND NOTHING SAID SO.**
 * {@see Credit} and {@see CancelSubscriptionController} catch
 * `AuthorizeNetRequestFailed|StripeRequestFailed` as a **union** and read
 * `$e->configuration`, `$e->reason` and `$e->retryable` off it. That compiles
 * only because the two classes happen to declare four identically named
 * properties, which no artefact required and no test held: **the day one of them
 * renamed a flag, both screens would break at runtime on a path a customer is
 * standing on.** ⛔ **And it had already cost something**: `malformedRequest`
 * landed on {@see AuthorizeNetRequestFailed} alone, so the two surfaces that
 * union with Stripe **could not take the arm at all** and went on telling a
 * buyer to check their card for a fault in our own request (11965, 12118).
 *
 * ⛔ **NOT `property_exists()` AND NOT A NULL-COALESCE ON A TYPED PROPERTY.**
 * Either would answer *"this gateway has no opinion"* in the same shape as
 * *"this gateway says no"* — which is this wave's axis, on the sentence that
 * decides whether somebody is told their card is at fault.
 *
 * ## What is deliberately NOT here
 *
 * ⚠️ **`clientRefused` IS ON BOTH CLASSES AND IS LEFT OFF**, and
 * `outcomeUnknown` is on {@see AuthorizeNetRequestFailed} alone. Decision 272's
 * shape is hardest to see as an interface member with no caller
 * ({@see GatewayWebhookReceiver}'s own words), so this declares **exactly the
 * four a union `catch` in `app/` reads today** and no more. A fifth arrives with
 * the screen that asks it.
 */
interface GatewayRequestFailure extends Throwable
{
    /**
     * The vendor's machine-readable code, never its message.
     *
     * ⛔ **BOTH CLASSES' RULE, FOR THE SAME REASON**: a vendor's human text
     * quotes the value it rejected — a card's last four, a customer's email —
     * and every reader of this property is a log line.
     */
    public string $reason { get; }

    /**
     * Whether waiting could plausibly change the answer.
     */
    public bool $retryable { get; }

    /**
     * An operator has to paste a credential; no developer and no buyer can act.
     */
    public bool $configuration { get; }

    /**
     * ⛔ **THE VENDOR READ OUR REQUEST AND OBJECTED TO ITS SHAPE — SO IT IS NOT
     * THIS PERSON'S CARD.** The flag exists because the sentence a buyer reads
     * on a fault of ours must not send them to their card, and the two screens
     * that catch a union could not ask this question at all before it was here.
     *
     * ⚠️ **THE CUSTOMER COPY CONVERGES WITH `configuration` AND THE CATEGORIES
     * ARE STILL TWO** — {@see AuthorizeNetRequestFailed}'s
     * `MALFORMED_REQUEST_CODES` carries the argument. What differs is the
     * operator's next move, and that lives in the log line.
     */
    public bool $malformedRequest { get; }
}
