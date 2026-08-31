<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which of the First 7-Day Results Path's wins landed for a tenant (`28` §3.2).
 *
 * ⚠️ **NARROWER THAN §3.2's TABLE, AND THE NARROWING IS DECISION 901's PATTERN
 * REPEATED.** The doc names two wins — "first Google review arrives" (Day 2-3)
 * and, failing that, "missed-call text-back proof" (Day 3, the alternate win) —
 * before the Day-5 fallback. **There is no third case here for the alternate
 * win.** Missed-call text-back is Stage 2 and **no inbound call is captured in
 * this schema at all**, so there is nothing an alt-win detector could read. ⚠️
 * **This sentence used to reach that conclusion through *"voice is web-only"*,
 * which is decision 28's support-bot widget and not this subject** — the
 * relevant fact is the absence of a capture, not the shape of the voice
 * product. Adding a case nothing can ever produce
 * would be `docs/FAILURE-SHAPES.md`'s "a table, column or control with no
 * writer" one enum value at a time —
 * so it is left out rather than shipped dormant, and this paragraph is where a
 * future Stage 2 slice should look before reopening the question.
 */
enum FirstWeekWinType: string
{
    /** Day 2-3's win: the tenant's first Google review, detected on any tick. */
    case GoogleReview = 'google_review';

    /**
     * Day 5's guaranteed-visible artifact, sent only when nothing else has won
     * by then.
     */
    case FallbackProof = 'fallback_proof';
}
