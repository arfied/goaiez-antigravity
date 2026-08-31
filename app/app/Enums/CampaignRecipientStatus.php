<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What happened to one contact on one campaign.
 *
 * ⚠️ **`Duplicate` IS A CASE BECAUSE `SendOutcome::wasSent()` ANSWERS FALSE FOR
 * IT.** T137 §3.1 requires a retry never duplicate a message *or a debit*, and
 * the send-driver contract makes the second attempt say so rather than quietly
 * returning the first one's provider id. A runner that folded this into `Sent`
 * would file a second row and debit a second credit against one delivered
 * message; one that folded it into `Failed` would retry it forever.
 */
enum CampaignRecipientStatus: string
{
    /** Chosen, not yet attempted. */
    case Pending = 'pending';

    /** Handed to the carrier and named by it. */
    case Sent = 'sent';

    /**
     * This exact send had already happened. Nothing new left the system, no row
     * was written twice and no credit was debited twice.
     */
    case Duplicate = 'duplicate';

    /**
     * A rule said no, and the rule is on the row.
     *
     * ⚠️ **A REFUSAL IS AN ANSWER, NOT AN ERROR** — the send-driver contract's
     * fourth load-bearing property. Suppression, an exhausted balance, quiet
     * hours, a contact the arbiter held back: all frequent, all expected.
     */
    case Refused = 'refused';

    /**
     * The run stopped before reaching this contact, or stopped mid-flight and
     * left them for the resume.
     *
     * ⚠️ **DISTINCT FROM `Pending`, AND THE DIFFERENCE IS WHAT AN OPERATOR
     * READS.** Pending is *not yet*; this is *we got here and deliberately did
     * not*, which is what a tenant pause, a suspension or a tripped global halt
     * leaves behind. Rolling them together would make a halted campaign
     * indistinguishable from one that has not started.
     */
    case Skipped = 'skipped';

    /**
     * The carrier could not be reached, and **this application can prove the
     * message never left it.** A later pass attempts the contact again.
     *
     * ⛔ **THIS CASE HAD NO WRITER ANYWHERE IN `app/` UNTIL 2026-08-21** (7181)
     * — three occurrences in the whole tree, two of them selects and one a
     * factory attribute, which is `CLAUDE.md`'s most-recorded failure shape
     * inside the enum that describes it. `RunCampaignJob::markFailed()` is the
     * writer, and it is reached only from
     * `TextNotDeliverable::$mayHaveReachedCarrier === false`.
     *
     * ⛔ **"COULD NOT BE REACHED" IS NARROWER THAN IT READS, AND THE NARROWING
     * IS THE SAFETY PROPERTY.** A transport failure whose outcome is *unknown*
     * is {@see self::Unknown} and is never retried: retrying it is a second
     * marketing text to a member of the public. This case is only for the
     * failures the transport can positively place before the socket — a
     * missing credential, no configured sender, a host that is not Infobip's,
     * a 4xx the vendor answered before the request became a message, or a
     * libcurl code that names a connection that was never made.
     *
     * ⚠️ **OUTSTANDING, AND THEREFORE SUBJECT TO THE FORTNIGHT.** 2687's
     * ceiling applies exactly as it does to a temporary refusal, so a
     * destination the carrier will never take stops being retried and the
     * campaign closes with the owner told, rather than re-marking the same row
     * every fifteen minutes for ever.
     */
    case Failed = 'failed';

    /**
     * The send was attempted and **its outcome cannot be established.**
     *
     * ⛔ **THE ONLY STATUS HERE THAT IS NOT A CLAIM ABOUT WHAT HAPPENED, AND
     * THAT IS THE WHOLE OF ITS VALUE** (7180). `TextNotDeliverable` reaches the
     * runner with one bit — whether the carrier may already hold the message —
     * and when it may, every other case on this enum would be a statement this
     * application cannot make: `Sent` claims a handle it never got, `Refused`
     * claims nothing went, `Failed` and `Pending` both invite a retry, and the
     * retry is a **second marketing text to a member of the public**.
     *
     * ⚠️ **NOT OUTSTANDING, AND THAT IS THE FIX RATHER THAN A CONSEQUENCE OF
     * IT.** `RunCampaignJob::batch()` never re-selects it, so no second send is
     * possible; `closeIfFinished()` no longer counts it, so the campaign can
     * finish; and the count reaches the owner in its own sentence at close,
     * because *"we could not confirm"* and *"we could not send"* are different
     * things to be told.
     *
     * ⚠️ **THE COST IS NAMED: A MESSAGE THAT NEVER ARRIVED IS NEVER RETRIED**
     * (7068's trade, one caller over). `AutopilotJob::claimIsSpent()` settled
     * it — *"losing a send is recoverable; sending twice is not"*.
     */
    case Unknown = 'unknown';

    /**
     * Whether the runner should attempt this contact on a later pass.
     */
    public function isOutstanding(): bool
    {
        return match ($this) {
            self::Pending, self::Skipped, self::Failed => true,
            // ⛔ **`Unknown` IS FALSE AND IT IS THE ONE ANSWER HERE THAT IS A
            // SAFETY PROPERTY RATHER THAN A SCHEDULING ONE.** Every other
            // `false` on this line means the contact has been dealt with; this
            // one means we do not know and must not find out by sending again.
            self::Sent, self::Duplicate, self::Refused, self::Unknown => false,
        };
    }
}
