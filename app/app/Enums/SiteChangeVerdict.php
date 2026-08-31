<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What measuring a site change concluded — `29` §2 rule 32's second half,
 * *"measure 14–30 days, auto-rollback on regression"*.
 *
 * ⛔ **`InsufficientData` IS A VERDICT OF ITS OWN AND MUST NEVER COLLAPSE INTO
 * `Neutral`** (`BUILD-PLAN` §2.11.2, correction 3, decision 5523). They are
 * different claims: `Neutral` says *we measured this page for a month and it did
 * not move*, `InsufficientData` says *not enough traffic reached it for the
 * question to have an answer*. Reporting silence as a finding is a false
 * statement (229), and the two lead to different actions — one keeps the change
 * on evidence, the other keeps it for want of any.
 *
 * ⚠️ **AND THE DIFFERENCE IS LOAD-BEARING TODAY RATHER THAN LATER**: 4861
 * records that barely any production traffic has ever reached the pixel, so
 * `InsufficientData` is the *expected* answer for most changes for some time.
 * A system that reported all of those as "neutral — kept" would be manufacturing
 * a month of evidence it does not have, on every tenant, from the first change.
 *
 * ⚠️ **FOUR OF THE SIX CASES HAVE NO WRITER IN SLICE A**, and that is
 * deliberate: they are vocabulary rather than columns, on
 * {@see AutopilotActionType}'s precedent, and slice H is what measures. The
 * column itself has a writer from the first day — `Pending` at open and
 * `RolledBack` at revert — which is what keeps 272's shape away from it.
 */
enum SiteChangeVerdict: string
{
    /** Written, not yet judged. The state every change starts in. */
    case Pending = 'pending';

    /** Measured, and the page did better. */
    case Improved = 'improved';

    /** Measured over a sufficient window, and nothing moved. */
    case Neutral = 'neutral';

    /**
     * Not enough traffic for the question to have an answer. **Never a
     * synonym for `Neutral`** — see the class docblock.
     */
    case InsufficientData = 'insufficient_data';

    /** Measured, and the page did worse. Slice H auto-reverts on this. */
    case Regressed = 'regressed';

    /**
     * The change is no longer on the site.
     *
     * ⚠️ **THIS SAYS NOTHING ABOUT WHY.** An auto-revert on a regression and an
     * owner pressing Undo both land here, and `site_changes.rolled_back_by` is
     * the column that tells them apart — which is the whole of correction 4.
     */
    case RolledBack = 'rolled_back';

    /**
     * Whether measurement has reached a conclusion about this change.
     *
     * ⚠️ **`InsufficientData` ANSWERS TRUE**, because "we looked and could not
     * tell" is a conclusion — it ends the measurement window. What it is not is
     * evidence, and no caller may read it as any.
     */
    public function isDecided(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * Whether this verdict is evidence that the change did something.
     */
    public function isEvidence(): bool
    {
        return match ($this) {
            self::Improved, self::Neutral, self::Regressed => true,
            self::Pending, self::InsufficientData, self::RolledBack => false,
        };
    }
}
