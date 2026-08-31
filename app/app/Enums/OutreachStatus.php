<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Delivery lifecycle of an outreach message (DATA-MODEL §5.1
 * `outreach_status`).
 *
 * OptedOut is a terminal state recorded on the message, not the opt-out
 * itself — that lives in suppression_list and opt_outs, which the send path
 * checks before a message is ever queued.
 */
enum OutreachStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case OptedOut = 'opted_out';
    case Replied = 'replied';

    /**
     * Whether this state is finished and may never be overwritten.
     *
     * ⚠️ **`Delivered` AND `Failed` ARE TERMINAL BECAUSE THE CARRIER SAYS SO,
     * NOT BECAUSE WE PREFER TIDINESS.** A delivery receipt is the handset's
     * answer, and there is no later fact that unsays it.
     *
     * `OptedOut` is terminal for a different and stronger reason: it records
     * that we declined to send because somebody had told us to stop. Letting a
     * later receipt overwrite it would erase the one status on this enum with a
     * compliance meaning, and it would erase it *quietly* — the row would read
     * `Failed` and nobody would know a refusal had ever been recorded.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Delivered, self::Failed, self::OptedOut => true,
            self::Queued, self::Sent, self::Replied => false,
        };
    }

    /**
     * Whether a delivery receipt may move this message into `$next`.
     *
     * ⚠️ **THE ONE-WAY RULE, AND `BUILD-PLAN` §2.10.4 NAMES IT AS A TEST:**
     * *"status transitions are one-way (`Delivered` never returns to
     * `Queued`)."* Carriers redeliver receipts and they do **not** guarantee
     * order — an intermediate `PENDING` report can arrive after the final
     * `DELIVERED` one, on a retry or simply out of sequence. Applied naively,
     * that walks a delivered message backwards to queued, and `MessageLog`
     * then tells an owner we are still trying to send something the customer
     * read yesterday.
     *
     * ⚠️ **THIS IS ALSO THE WHOLE REPLAY DEFENCE, AND IT IS DELIBERATELY NOT A
     * DEDUPE TABLE.** A redelivered receipt asks for the state the row is
     * already in, which this refuses as a no-op — so idempotency falls out of
     * the rule rather than being bolted beside it. `inbound_messages` needed a
     * unique index because a replayed STOP would have written a second audit
     * trail and a second reply; a replayed receipt writes nothing by
     * construction.
     */
    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return false;
        }

        return ! $this->isTerminal();
    }
}
