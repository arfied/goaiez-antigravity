<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Content\GrowthPages;

/**
 * Where a growth page is in the only flow it has — `DATA-MODEL.md` §5.11's
 * `status DEFAULT 'draft'`, and `BUILD-PLAN` §2.11.3 slice C's *"status flow
 * `draft → held → published`"*.
 *
 * ⚠️ **A STRING COLUMN CAST TO THIS, NEVER A DATABASE ENUM** (`CLAUDE.md`). The
 * three cases below are the whole of the flow today and the vocabulary will
 * still move — slice D adds the approval hold, Stage 5 adds a generator — and a
 * Postgres enum is a second source of truth whose values cannot be dropped or
 * reordered once added.
 *
 * ## ⛔ `Published` IS ANTICIPATED VOCABULARY AND NOTHING WRITES IT
 *
 * Slice C is the gate; slice D is the pipeline that publishes. The case is
 * declared now because {@see self::isTerminal()} and the writer's transition
 * guard have to be able to *express* "already published" before anything can
 * reach it — `SiteChangeVerdict`'s four unwritten cases and
 * `AutopilotActionType`'s anticipated actions are the same pattern. **A column
 * with no writer is a defect (272); an enum case with no writer is a word.**
 *
 * ## ⚠️ A PAGE THAT PASSES THE GATE STAYS `Draft`, AND THAT IS DELIBERATE
 *
 * There is no `approved` rung. The gate's verdict lives on a
 * `content_quality_checks` row — which is the thing that carries the *reasons*
 * — and duplicating it as a status would give this application two answers to
 * "may this publish?" that a job ordering could put out of step. `status` says
 * where the page is; the check row says what was decided about it.
 */
enum GrowthPageStatus: string
{
    /**
     * A candidate. Not gated, or gated and cleared — see the class docblock for
     * why those are one state rather than two.
     */
    case Draft = 'draft';

    /**
     * Somebody has to look at this before it goes anywhere.
     *
     * ⚠️ **TWO KINDS OF HOLD SHARE THIS CASE AND `hold_until` IS WHAT TELLS THEM
     * APART.** A hold with no release time waits for a person and never lapses;
     * a hold with one is slice D's AUTO-WITH-HOLD, which proceeds on silence.
     * {@see GrowthPages::holdUntil()} is the only
     * way to set the second, and it refuses a page that did not clear the gate —
     * because a gate failure that quietly acquired a release time would publish
     * the exact page the gate refused.
     */
    case Held = 'held';

    /**
     * Live on the tenant's website. **Slice D's to write.**
     */
    case Published = 'published';

    /**
     * Whether this page has already left the pipeline.
     */
    public function isTerminal(): bool
    {
        return $this === self::Published;
    }
}
