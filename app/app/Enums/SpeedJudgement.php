<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What one run of `28` §4.3's decider did.
 *
 * ⚠️ **`SiteMeasurementOutcome`'s VOCABULARY, NOT A COPY OF IT.** Slice H's enum
 * answers a different question about a different window, and folding the two
 * would make a value mean *"days 14–30 said the page lost visits"* in one caller
 * and *"the seven days after the change said the site got slower"* in another.
 */
enum SpeedJudgement: string
{
    /** No such speed change set. */
    case NotFound = 'not_found';

    /** Applied but its seven days are not up, or already judged. */
    case NotDue = 'not_due';

    /** Judged, nothing fired, still on the site. */
    case Kept = 'kept';

    /**
     * The window closed too thin to say anything.
     *
     * ⛔ **THE EXPECTED ANSWER TODAY, AND NEVER `Kept`** (5523, 4861).
     */
    case InsufficientData = 'insufficient_data';

    /** A trigger fired and the change came back off. */
    case RolledBack = 'rolled_back';

    /**
     * A trigger fired, the fix is resting, and the site would not take the
     * change back.
     */
    case RevertFailed = 'revert_failed';

    /**
     * The change set names a page that is not on this location's website any
     * more, so neither judging it nor putting it back can be attributed to it.
     *
     * ⛔ **`ChangeMeasurer`'s `PageNotIdentifiable`, ONE LAYER ALONG** (5819(a),
     * 5965). This decider reverts through `SiteMeasurements::revert()` and had no
     * address check of its own, so a speed fix in flight when an owner replaced
     * their website was judged a week later and put back at a path resolved
     * against whatever site the location is connected to **now**.
     *
     * ⚠️ **THE ROW STAYS `measuring` AND THAT IS SLICE H's SHAPE RATHER THAN AN
     * OVERSIGHT** (5974): H's row keeps `verdict = pending` in the identical
     * situation. Neither invents a conclusion about a change nobody measured,
     * and neither is offered by its sweep again.
     */
    case PageNotIdentifiable = 'page_not_identifiable';
}
