<?php

declare(strict_types=1);

namespace App\Services\Gbp;

/**
 * What a provider said back after we asked it to publish a reply.
 *
 * ⚠️ **THIS EXISTS BECAUSE A 200 IS NOT A PUBLICATION, AND THE GAP IS THE
 * VENDOR'S RATHER THAN OURS.** Google's own reference for
 * `accounts.locations.reviews.updateReply` (read 2026-08-11, page last updated
 * 2026-04-07) returns a `ReviewReply` carrying `reviewReplyState`, whose values
 * are `PENDING`, `REJECTED` and `APPROVED`, plus a `policyViolation` populated
 * only when it is `REJECTED`. **Zernio's relay does not carry any of them.** Its
 * 200 body is `{status, reply:{id,text,created}, platform}` — an acknowledgement
 * that it accepted the call, with no channel for Google's moderation verdict.
 *
 * So the strongest true statement after a successful call through Zernio is
 * *"the provider accepted this reply"*, not *"this reply is live on the
 * listing"*. That distinction is why the receipt is a value carried into the
 * audit entry instead of being thrown away: when an owner asks why a reply they
 * approved is not visible, the honest answer is the provider's own
 * acknowledgement and the fact that we were never told the verdict.
 *
 * It is deliberately two nullable strings and nothing else. Everything the
 * relay returns beyond them is either the text we just sent back to us or a
 * timestamp of its own arrival; neither is evidence about anything, and
 * `GbpReview`'s reasoning applies unchanged — an unused field on a vendor
 * payload is stored personal data waiting for somebody to persist it.
 */
final readonly class GbpReplyReceipt
{
    public function __construct(
        /**
         * The provider's own id for the reply it created, when it gave one.
         *
         * Opaque. Recorded so a later reconciliation — or a support question
         * about a reply that never appeared — has the one handle the provider
         * would recognise. Never parsed and never shown to an owner.
         */
        public ?string $providerReplyId,
        /**
         * The provider's own word for the outcome, verbatim, when it gave one.
         *
         * ⚠️ **NOT MAPPED ONTO OUR OWN STATUS VOCABULARY, ON PURPOSE.** Zernio
         * documents the field as a bare `string` with no enumerated values, so
         * any mapping here would be a guess dressed as a translation — and the
         * one thing this field is for is telling somebody, later, exactly what
         * the vendor said.
         */
        public ?string $providerStatus,
    ) {}
}
