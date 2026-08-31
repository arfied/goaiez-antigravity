<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the customer said to "did we get that sorted?" — T546 §37.3(1), wave 38
 * lane C.
 *
 * ⚠️ **A CLICK, NEVER FREE TEXT.** TRIAGE-01's AI SMS conversation loop is
 * unbuilt — nothing appends to the recovery conversation's own `transcript`
 * column (942) — so there is no parser here and none is owed by this slice.
 * The customer answers
 * by choosing one of two buttons on a page reached through a signed URL
 * (`GET /checkin/{business}/{conversation}`), which is the honest, low-friction
 * equivalent of the destination-click mechanism the review-invite flow already
 * uses, and it is auditable in exactly the same way: a record of which button
 * was pressed, never an inference from silence or from the passage of time.
 *
 * ⛔ **THE THIRD STATE — NO ANSWER AT ALL — HAS NO CASE HERE, DELIBERATELY.**
 * `triage_conversations.fix_then_ask_response` stays `null` for it. A silent
 * contact is not "not resolved" (that is a claim about their experience nobody
 * made) and is not "confirmed" (the one answer this platform must never
 * assume) — it is simply unanswered, and `null` already says that without a
 * case pretending to speak for somebody who said nothing.
 */
enum FixThenAskResponse: string
{
    /** The customer said the issue is now sorted. */
    case Confirmed = 'confirmed';

    /** The customer said it is not sorted yet. */
    case NotResolved = 'not_resolved';
}
