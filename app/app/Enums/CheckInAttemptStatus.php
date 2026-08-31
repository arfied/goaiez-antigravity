<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What became of one attempt to send one fix-then-ask check-in — wave 38 lane C
 * (10590–10609).
 *
 * ⚠️ **A DELIBERATE MIRROR OF {@see InviteAttemptStatus}, ON ITS OWN ARGUMENT
 * — MIRRORING IS THE POINT.** `InviteAttempt` exists because a refused send has
 * to survive its own rollback as a *value*, since a row written to explain the
 * refusal is inside the transaction being thrown away. This message writes
 * exactly the same shape of row (an `outreach_messages` insert, a credit
 * debit, a `SendKey` claim, all inside one transaction), so it needs exactly
 * the same answer.
 *
 * ⛔ **NOT `InviteAttemptStatus` ITSELF, EVEN THOUGH THE SHAPE IS IDENTICAL.**
 * `InviteAttempt`'s own docblock is explicit that it exists for
 * `ReviewInviteSender` and is not `SendOutcomeStatus` reused — a check-in is
 * not a review invite, its `outreach_messages.purpose` is `'triage'` and not
 * `'review_request'`/`'reminder'`, and a type named `InviteAttempt` answering
 * for a message that never mentions a review would be exactly the kind of
 * misnamed reuse `SendOutcomeStatus`'s docblock warns the review-invite path
 * itself against.
 */
enum CheckInAttemptStatus: string
{
    /** A check-in was composed, charged for and handed to the mailer or the carrier. */
    case Sent = 'sent';

    /** A rule said no. The rule travels with it. */
    case Refused = 'refused';

    /**
     * This conversation's check-in had already been claimed. Nothing new was
     * sent, no credit was debited and no row survived.
     */
    case Duplicate = 'duplicate';

    /**
     * No gate about this person was reached — the feature switch is off, or
     * the conversation is not (yet, or any longer) eligible.
     */
    case NotAttempted = 'not_attempted';
}
