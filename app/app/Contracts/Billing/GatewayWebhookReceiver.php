<?php

declare(strict_types=1);

namespace App\Contracts\Billing;

use App\Enums\GatewayEventOutcome;
use App\Enums\PaymentGateway;

/**
 * The one shape both card gateways genuinely share (decision 2140).
 *
 * ⚠️ **THIS IS DELIBERATELY THE *ONLY* CONTRACT IN THE "ONE GATEWAY
 * ABSTRACTION" DECISION 2056 ASKS FOR, AND THE OMISSION IS THE ARGUMENT.**
 * Checkout does not unify: Stripe redirects the browser to its own domain and
 * answers with a session URL, while Accept.js keeps the form on our page and
 * answers with a nonce we then exchange server-side. An interface spanning those
 * two would have one method per gateway wearing a shared name, which is worse
 * than no interface — it reads as a seam and is a coincidence.
 *
 * ⚠️ **AND THE SUBSCRIPTION-LIFECYCLE HALF IS NOT HERE EITHER, ON 582's RULE.**
 * Cancelling and reconciling *are* the same idea on both vendors, but nothing in
 * `app/` cancels a Stripe subscription today — that is row 22 slice C's portal —
 * so a `cancel()` on this interface would ship with one real implementation and
 * one written to satisfy the compiler. Decision 272's shape has been counted
 * fifteen times in this codebase and an interface method with no caller is the
 * version of it that is hardest to see. **It arrives with slice C's caller.**
 *
 * What *does* unify, exactly and without strain: an inbound HTTP body arrives
 * from a vendor, it is verified against a shared secret before anything reads
 * it, and it is applied exactly once. Both controllers are the same nine lines
 * because of it.
 */
interface GatewayWebhookReceiver
{
    /**
     * Which vendor this receiver speaks for.
     *
     * Used for logging and for the outcome an operator reads; never for
     * branching inside a handler, because a handler that branches on its own
     * identity is two handlers.
     */
    public function gateway(): PaymentGateway;

    /**
     * Verify a raw request body, or throw.
     *
     * ⚠️ **THE RAW BODY, NEVER A DECODED ARRAY, AND THIS IS A CONTRACT TERM
     * RATHER THAN AN IMPLEMENTATION DETAIL.** Both vendors sign the exact bytes
     * they sent, so any re-encoding — a framework's JSON round trip, a
     * normalised float, a reordered key — invalidates a signature that was
     * perfectly good. Putting `string $payload` in the interface is what stops
     * the next implementation taking `array $event` because it was tidier.
     *
     * ⚠️ **AN IMPLEMENTATION MUST FAIL CLOSED WHEN THE SECRET IS UNSET.** An
     * endpoint that skips verification when unconfigured is one anybody can post
     * a paid subscription to, and "unconfigured" is the state every deployment
     * starts in.
     *
     * @return array<string, mixed>
     */
    public function verify(string $payload, ?string $signature): array;

    /**
     * Apply a verified event exactly once. Null means it had already been done.
     *
     * ⚠️ **THE IDEMPOTENCY CLAIM AND THE WORK COMMIT TOGETHER OR NEITHER DOES**
     * (693). A handler that claims first and throws later leaves a row saying
     * "seen", every redelivery is refused as a duplicate, and a paid
     * subscription sits unrecorded forever while the endpoint answers 200 the
     * whole time.
     *
     * @param  array<string, mixed>  $event
     */
    public function process(array $event): ?GatewayEventOutcome;
}
