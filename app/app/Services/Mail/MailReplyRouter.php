<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Enums\OutreachStatus;
use App\Models\MailTrackingCode;
use App\Models\OutreachMessage;
use App\Support\Tenancy;

/**
 * A reply that arrived at the shared relay, threaded back to the contact who
 * wrote it (SL-4's *"reply routing into the CRM"*, decision 2097).
 *
 * ⚠️ **THE PROBLEM IS THAT A REPLY NAMES NO TENANT**, which is
 * `DeliveryReceipts`' problem on a different channel and is solved the same way
 * with a different key. One relay mailbox serves every client, the sender's
 * address may not be the one we mailed, and the subject line is whatever their
 * mail client decided to prefix. **The address the reply was sent *to* is the
 * one thing that survives the round trip unchanged**, so the eight-character
 * code rides in `Reply-To` and comes back in the envelope recipient.
 *
 * ⚠️ **THE CODE ESTABLISHES THE TENANT AND IS NEVER PROOF OF OWNERSHIP.**
 * `DeliveryReceipts`' exact distinction: the lookup decides *which rows are
 * visible*, and the work that follows happens under RLS with that tenant set,
 * so a forged or stale code selects a tenant whose rows do not contain that
 * message and finds nothing. What a guessed code can do is mark one message
 * replied — never write a consent, a suppression, or a send.
 *
 * ## What lands in the CRM, and what deliberately does not
 *
 * The outreach message moves to `OutreachStatus::Replied`, which `MessageLog`
 * and `CustomerTimeline` already render — so this joins a stream that has
 * readers rather than writing to `crm_timeline`, which has none (1222's rule:
 * check for a writer, and check before *designing* against a table).
 *
 * ⛔ **THE TEXT OF THE REPLY IS NOT STORED, AND THAT IS A DECISION WITH A REAL
 * COST.** `inbound_messages` refuses the same thing for the same reason — free
 * text written by a member of the public, in a platform-scoped table beside the
 * ids that name them, under no tenant's retention policy. The owner therefore
 * learns *that* somebody replied and not *what they said*, until a tenant-owned
 * inbound store exists to hold it. That store is doc `45`'s conversation path
 * and is unbuilt.
 *
 * ⚠️ **A `Delivered` MESSAGE CANNOT MOVE TO `Replied`, AND NOTHING ON THE EMAIL
 * PATH SETS `Delivered` TODAY.** `OutreachStatus::isTerminal()` makes
 * `Delivered` final — correct for a carrier receipt, which has no later fact
 * that unsays it — so if SES delivery events are ever ingested, a reply to a
 * delivered message would be silently dropped here. Recorded rather than fixed:
 * changing what terminal means is a decision about the whole status enum, not
 * about this file.
 */
final class MailReplyRouter
{
    public function __construct(private readonly MailTrackingCodes $codes) {}

    /**
     * Route one reply, addressed to one or more of our own addresses.
     *
     * Returns true when a message was threaded. False covers every ordinary
     * reason it was not — no code in any recipient, a code nobody minted, a
     * message already terminal, a code minted for a message that no longer
     * exists — and the caller answers 200 to all of them, because none is
     * improved by the sender's mail server trying again.
     *
     * @param  list<mixed>  $recipients  the addresses the reply was sent to, as the
     *                                   vendor gave them — validated here rather
     *                                   than trusted, because they come from a
     *                                   webhook body
     */
    public function route(array $recipients): bool
    {
        foreach ($recipients as $recipient) {
            if (! is_string($recipient)) {
                continue;
            }

            $code = $this->codes->codeIn($recipient);

            if ($code === null) {
                continue;
            }

            $tracking = $this->codes->resolve($code);

            if (! $tracking instanceof MailTrackingCode) {
                // A code nobody minted. Ordinary rather than hostile: a reply to
                // a message sent before this table existed, a mailing-list
                // expansion, a bounce of a bounce.
                continue;
            }

            if ($this->thread($tracking)) {
                return true;
            }
        }

        return false;
    }

    private function thread(MailTrackingCode $tracking): bool
    {
        $this->codes->markReplied($tracking);

        if ($tracking->outreach_message_id === null) {
            // The code was minted for a message with no `outreach_messages` row
            // — support mail, say. The reply is recorded on the code and there
            // is nothing further to move.
            return true;
        }

        return Tenancy::actingAs($tracking->business_id, function () use ($tracking): bool {
            $message = OutreachMessage::query()->find($tracking->outreach_message_id);

            if (! $message instanceof OutreachMessage) {
                return false;
            }

            if (! $message->status->canTransitionTo(OutreachStatus::Replied)) {
                return false;
            }

            $message->status = OutreachStatus::Replied;
            $message->save();

            return true;
        });
    }
}
