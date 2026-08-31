<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Messaging\Outbound\PlatformMessageSender;
use App\Services\Messaging\Outbound\SendKey;

/**
 * What became of one attempt to invite one customer to review a business.
 *
 * ⚠️ **A DELIBERATE MIRROR OF {@see SendOutcomeStatus}, AND MIRRORING IS THE
 * POINT.** That enum is the working half of the pair `CLAUDE.md` §Engineering
 * warns about — *"reads on a diff as removing an inconsistency"* — so the
 * review-invite path is
 * levelled **up** to its shape rather than the other way round. Every argument
 * in its docblock applies here unchanged: a duplicate is not an accepted send,
 * a refusal carries its rule, and a transport that could not be reached throws
 * rather than taking a case.
 *
 * ⛔ **IT IS NOT `SendOutcomeStatus` AND MUST NOT BE MERGED INTO IT.** Two of
 * this enum's four cases cannot exist there. `NotAttempted` is the answer when
 * no gate about *this person* was ever reached, which a message sender by
 * definition never has to say — {@see PlatformMessageSender}
 * is handed a composed message and a decision already made. And an accepted
 * review invite has **no provider message id on the email channel**, because
 * the mail leaves through a queued job, so a `SendOutcome::accepted()` cannot
 * even be constructed for it. ⚠️ **Two of this path's refusals also happen
 * before a {@see SendKey} exists at all** —
 * the containment and the missing permit — and every `SendOutcome` factory
 * requires one. **Reusing that type was considered and is impossible, not
 * merely undesirable.**
 *
 * ⚠️ **NOT `OutreachStatus` EITHER**, for its own stated reason: that enum is
 * the lifecycle of a *row* and moves over hours as delivery receipts arrive.
 * This is the answer to one synchronous call and never changes afterwards —
 * and three of its four cases describe a call that wrote no row at all.
 */
enum InviteAttemptStatus: string
{
    /** A message was composed, charged for and handed to the mailer or the carrier. */
    case Sent = 'sent';

    /** A rule said no. The rule travels with it. */
    case Refused = 'refused';

    /**
     * This review's invite had already been claimed. Nothing new was sent, no
     * credit was debited and no row survived.
     *
     * ⚠️ **NOT A REFUSAL, ON {@see SendOutcomeStatus::Duplicate}'s OWN
     * ARGUMENT.** There is no rule refusing this customer; there is an earlier
     * attempt that got there first, and the caller must be able to tell that it
     * lost the race rather than that somebody may not be messaged.
     */
    case Duplicate = 'duplicate';

    /**
     * No gate about this person was reached.
     *
     * ⚠️ **THE ONE CASE WITH NO REASON, AND IT FUSES THREE STATES ON PURPOSE**
     * — the review-invite feature switches both being off, and
     * `ReviewInvites::offerFor()` returning an empty list. Neither is a fact
     * about the recipient, neither opened a transaction, neither cost anything,
     * and both are already visible to the tenant on their own screens: the
     * platform switch on `Admin\SendingControls`, and the destinations and
     * threshold that decide the offer on their own review-settings screen.
     *
     * ⛔ **THE FUSION IS A STATED LIMIT RATHER THAN AN OVERSIGHT.** Splitting it
     * means new {@see SendRefusalReason} cases, and that enum's own docblock
     * makes a new case a conversation — it would need an `ownerSentence()`, an
     * `isTemporary()` arm and an argument about a screen that renders it. **The
     * refusals this slice is about are the ones that happen after the decision
     * to send has been made and something has already been written.**
     *
     * ⚠️ **RECONSIDERED AND REAFFIRMED — 10240, WAVE 35 LANE B PHASE 3.** The
     * question this whole slice exists for — *"my customer left four stars and
     * was never asked for a review"* — is exactly gate 2's arm: a rating that
     * clears no destination's `review_destinations.invite_threshold` makes
     * `offerFor()` return `[]`, and that is a fact about **this review**, not
     * about the platform. ⛔ **STILL NOT SPLIT, FOR A REASON THIS DOCBLOCK
     * DID NOT HAVE BEFORE**: `Admin\AutomationRuns`, the one operator screen
     * that lists these runs, does not render `output` at all — its
     * `columns()` stops at `automation_key`, `status`, `location_id` and the
     * two timestamps. So splitting this case would not create a reader; it
     * would add a permanent decision point to a `match`-with-no-`default`
     * enum for a distinction nothing renders. **The authoritative, per-review
     * answer to gate 2's question already exists and needs no enum change**:
     * `reviews.routed_destinations`, written unconditionally by
     * `ReviewRouter::route()` at submission — before the invite feature's own
     * switches are even read, and whether or not this job ever runs. An empty
     * array there is gate 2's whole finding, one column away, on every review
     * regardless of automation timing. A future screen answering *"why wasn't
     * this customer asked"* should join `reviews` for that column rather than
     * ask this enum to carry a second implementation of `offerFor()`'s
     * eligibility check just to explain itself — which is what a new case
     * would require, since re-deriving eligibility beside a decision already
     * made is the exact "two implementations of one rule" trap
     * `ReviewInvites`' own docblock (its "THIS CLASS DOES NOT DECIDE, IT
     * READS A DECISION" paragraph) argues against one layer up. ⚠️ **The count
     * this docblock opened with is also an undercount, corrected here rather
     * than in the number**: `attemptReminder()` returns this case for two
     * further reasons of its own — nothing was ever sent to follow up on, and
     * the customer already clicked through since — neither of which is gate 2
     * either. Five return sites across the two methods share this one case;
     * the property that matters is still the one stated above, not the count.
     */
    case NotAttempted = 'not_attempted';
}
