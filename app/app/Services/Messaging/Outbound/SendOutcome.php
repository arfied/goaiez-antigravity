<?php

declare(strict_types=1);

namespace App\Services\Messaging\Outbound;

use App\Enums\SendOutcomeStatus;
use App\Enums\SendRefusalReason;
use LogicException;

/**
 * What happened when a message was handed to the sender.
 *
 * Three outcomes, and the third is the one that makes this a type rather than a
 * nullable return:
 *
 *   **accepted**   the carrier or mailer took it and named it
 *   **refused**    a rule said no, and the rule is attached
 *   **duplicate**  this exact {@see SendKey} had already been sent
 *
 * ## Why "duplicate" is not "accepted"
 *
 * T137 §3.1 requires that a retry never duplicate a message *or a debit*. The
 * cheapest way to honour that is for the second attempt to return the first
 * one's provider id and say nothing — and that is wrong, because the caller then
 * writes a second `outreach_messages` row, debits a second credit, and files a
 * second timeline entry against one delivered message. **The caller has to be
 * able to tell that it lost the race**, so the outcome says so and carries the
 * original's provider id for the caller to reconcile against.
 *
 * ## Why refusal is a value and failure is an exception
 *
 * A refusal is an *answer*: quiet hours, a suppression, an exhausted credit
 * balance, a paused tenant. It is expected, it is frequent, and it is not an
 * error — `ReviewInviteSender`'s fourth load-bearing property. A carrier that
 * could not be reached is a different thing entirely and throws, because 700's
 * failure was exactly a send that did not happen being indistinguishable from
 * one that did.
 *
 * ⚠️ **`providerMessageId` IS NULL ON A REFUSAL AND THE INVARIANT IS ENFORCED.**
 * A refused outcome carrying a provider id would be a message the vendor has and
 * this application believes it never sent.
 */
final readonly class SendOutcome
{
    private function __construct(
        public SendOutcomeStatus $status,
        public SendKey $key,
        public ?string $providerMessageId,
        public ?SendRefusalReason $reason,
        /**
         * Which `phone_numbers` row this left on, when it left on one.
         *
         * Null on email, null on a refusal, and null in the bootstrap case where
         * no inventory row exists yet and the driver used its configured sender.
         */
        public ?int $numberId = null,
        /**
         * What the vendor called it, verbatim.
         *
         * ⚠️ **Never shown to an owner.** `MessageLog`'s rule: the stored vendor
         * string is written for us, and putting it in front of an owner turns a
         * delivery problem into a support ticket about a message they cannot
         * read.
         */
        public ?string $vendorStatus = null,
    ) {
        if ($status === SendOutcomeStatus::Refused && $reason === null) {
            throw new LogicException(
                'A refusal without its reason is a dead end for whoever has to explain it. '
                .'ConsentService has answered with a reason since 391; this must not lose it.'
            );
        }

        if ($status !== SendOutcomeStatus::Refused && $reason !== null) {
            throw new LogicException(
                'A send that was not refused must not carry a refusal reason. An operator screen '
                .'would print the refusal beside a delivered message.'
            );
        }

        if ($status === SendOutcomeStatus::Refused && $providerMessageId !== null) {
            throw new LogicException(
                'A refused send must not name a provider message. That is a message the vendor '
                .'holds and this application believes it never sent.'
            );
        }

        if ($status === SendOutcomeStatus::Accepted && $providerMessageId === null) {
            throw new LogicException(
                'An accepted send that cannot be named can never have its delivery receipt applied, '
                .'and the row sits queued forever — the failure the receipt webhook exists to prevent.'
            );
        }
    }

    public static function accepted(
        SendKey $key,
        string $providerMessageId,
        ?int $numberId = null,
        ?string $vendorStatus = null,
    ): self {
        return new self(
            status: SendOutcomeStatus::Accepted,
            key: $key,
            providerMessageId: $providerMessageId,
            reason: null,
            numberId: $numberId,
            vendorStatus: $vendorStatus,
        );
    }

    public static function refused(SendKey $key, SendRefusalReason $reason): self
    {
        return new self(
            status: SendOutcomeStatus::Refused,
            key: $key,
            providerMessageId: null,
            reason: $reason,
        );
    }

    /**
     * This key had already been sent. Nothing new happened.
     *
     * @param  string|null  $providerMessageId  The *original* send's id, when it
     *                                          is known, so the caller can
     *                                          reconcile rather than guess.
     */
    public static function duplicate(SendKey $key, ?string $providerMessageId = null): self
    {
        return new self(
            status: SendOutcomeStatus::Duplicate,
            key: $key,
            providerMessageId: $providerMessageId,
            reason: null,
        );
    }

    /**
     * Whether anything left this system.
     *
     * ⚠️ **A duplicate answers false**, which is the whole reason the case
     * exists: the caller must not write a second row or debit a second credit.
     *
     * ⚠️ **THE ASSERTION RESTATES AN INVARIANT THE CONSTRUCTOR ALREADY REFUSES
     * TO BREAK**, rather than adding a second rule beside it. An `Accepted`
     * outcome with no provider id throws above — *"an accepted send that cannot
     * be named can never have its delivery receipt applied"* — so past this
     * method the id is a string, and the analyser is told so here instead of
     * each caller re-checking a case that cannot occur. A caller that re-guarded
     * it would be writing a branch no test could ever drive red.
     *
     * @phpstan-assert-if-true non-empty-string $this->providerMessageId
     */
    public function wasSent(): bool
    {
        return $this->status === SendOutcomeStatus::Accepted;
    }
}
