<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What kind of thing happened to a contact (`34` §1.2's typed entries).
 *
 * ⚠️ **A CASE PER STORE WITH A WRITER, NOT PER TYPE `34` NAMES.** This started
 * at four; the fifth (`Task`) arrived when the follow-ups slice gave
 * `crm_tasks` its writer — which is the rule working, not the rule bending.
 * §1.2 lists thirteen entry types — texts in and out, calls with a
 * player and a transcript, emails, chat threads, form submissions, website
 * visits from the pixel, reviews, bookings, payments, triage episodes and notes.
 * Nine of those have no source in this application at all: voice is Stage 2, the
 * pixel does not exist (`29` §12.1's replay-fidelity gate is unmet), bookings
 * and payments have no integration, and `crm_timeline` — the table that would
 * have carried a generic entry — has no writer.
 *
 * A case per *documented* type would have been the mistake. It reads as
 * coverage, renders nothing, and the next person to ask "why is the timeline
 * empty" finds an enum saying it should not be. Decision 256's vacuity, in an
 * enum's costume.
 *
 * ⚠️ **This is never persisted and must not become a column.** It labels a row
 * assembled at read time from four tables; storing it would create the second
 * source of truth `29` §9.5 forbids, and `CLAUDE.md`'s standing rule against
 * database enums would apply the moment somebody tried.
 */
enum TimelineEntryType: string
{
    /**
     * A review this contact left, and what routing decided about it.
     *
     * Carries the triage state rather than giving triage its own case:
     * `triage_conversations` is held to `ReviewRouter` by a chokepoint lint, a
     * triage episode always belongs to exactly one review, and reading the
     * review's own status answers the same question without a second service
     * gaining a reader (decision 624's rule — ask whether the caller belongs
     * behind the service before widening anything).
     */
    case Review = 'review';

    /**
     * Something we sent them. Outbound only, because `outreach_messages` is
     * outbound only and no inbound store exists — see `MessageLog`.
     */
    case Message = 'message';

    /**
     * A consent grant or a withdrawal, from `ConsentService::proofFor()`.
     *
     * Both directions in one case on that method's own reasoning: a trail
     * showing grants without withdrawals is a document in which every row is
     * true and the whole is false.
     */
    case Consent = 'consent';

    /**
     * Something the owner wrote down themselves.
     */
    case Note = 'note';

    /**
     * A follow-up on this contact — created, and completed when it was
     * (`44` §2: "created/completed, one sentence each"). Read through
     * `CrmTasks`, never `crm_timeline` — the tripwire lint of decision 1222
     * still holds, and this case is the proof it was never needed as a
     * loophole.
     */
    case Task = 'task';

    /**
     * A link we sent them, which they opened (T137 `SL-5`).
     *
     * ⚠️ **THE SIXTH CASE, AND IT ARRIVED THE SAME WAY THE FIFTH DID** — when
     * the store gained a writer, never before. 2495 recorded the gap in as many
     * words: *"the clicks do not reach `CustomerTimeline`'s union either … the
     * union takes a source when it gains a writer, and this one has none in
     * production, so adding the case now would render an empty history and read
     * as 'this contact clicked nothing'."* `ReviewInviteSender` is that writer.
     *
     * ⛔ **COUNTED CLICKS ONLY, AND THAT IS THE WHOLE POINT OF THE CASE.** 2484:
     * carriers, link checkers and messaging apps open every URL we send, so the
     * naive version of this reports near-total engagement seconds after a send —
     * wrong, and convincing. `ShortLinkClicks::countedForCustomer()` is the
     * method that filters, and it is the only one the timeline may use: a
     * scanner's fetch rendered here is the sentence *"they opened your message"*
     * about somebody who did not.
     */
    case LinkClick = 'link_click';
}
