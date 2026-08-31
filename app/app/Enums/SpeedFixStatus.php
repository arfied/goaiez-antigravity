<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What became of one application of one speed fix.
 *
 * ⛔ **THE SPEC'S FIVE VALUES ARE `applied|measuring|kept|rolled_back|
 * quarantined` AND THREE OF THOSE CHANGED — THE ARGUMENT IS IN THE MIGRATION**
 * (`DATA-MODEL.md`, `speed_change_sets`, corrections 3–6). In short: `decided`
 * is added because a row exists before the write lands and one inserted as
 * `applied` would begin life lying; `applied` is dropped because it and
 * `measuring` name one state; `quarantined` is dropped because the quarantine is
 * a fact about `(location, change_type)` on `site_change_quarantines` and a
 * second copy here would be a second answer to the same question; and
 * `insufficient_data` is added because 5523's rule is that it may never collapse
 * into a finding.
 */
enum SpeedFixStatus: string
{
    /**
     * Chosen, its change set opened, nothing on the website yet.
     *
     * ⚠️ **A ROW CAN REST HERE FOR EVER AND THAT IS THE HONEST END STATE FOR A
     * WRITE THAT FAILED.** `SiteChanges::apply()` stamps `applied_at` only when
     * the adapter reports writing, so a site that did not answer leaves the
     * record of what we tried, which is the whole reason that method has two
     * steps.
     */
    case Decided = 'decided';

    /** On the site, inside its seven-day window. */
    case Measuring = 'measuring';

    /** Judged, no trigger fired, still on the site. */
    case Kept = 'kept';

    /** A trigger fired and the page was put back. */
    case RolledBack = 'rolled_back';

    /**
     * A trigger fired, the fix is resting, and the site would not take the
     * change back.
     *
     * ⛔ **OUR OWN HARMFUL CHANGE IS STILL ON SOMEBODY'S WEBSITE WHEN A ROW SAYS
     * THIS.** Slice H's `dueForRevert()` is what carries the obligation — it
     * selects on `site_changes.verdict` rather than on this column, so a speed
     * fix in this state is retried nightly by the machinery that already
     * existed, and this value is what a screen and an operator read.
     */
    case RevertFailed = 'revert_failed';

    /**
     * The window closed and not enough of it was measured to say anything.
     *
     * ⛔ **NOT `kept`, EVER** (5523). *"We watched for a week and it did not
     * move"* and *"barely anybody visited, so the question has no answer"* are
     * different claims, and reporting the second as the first manufactures
     * evidence. ⚠️ **It is terminal**: every window is anchored on `applied_at`,
     * so a window that was thin when it closed is thin for ever — and 4861 makes
     * this the expected outcome for most fixes for some time to come.
     */
    case InsufficientData = 'insufficient_data';

    /**
     * Whether this row has been judged.
     */
    public function isJudged(): bool
    {
        return $this !== self::Decided && $this !== self::Measuring;
    }
}
