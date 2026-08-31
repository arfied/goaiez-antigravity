<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What we can honestly say about nearby competitors (`28` §5.5 / decision 1084).
 *
 * ⚠️ **THERE ARE FIVE, AND THERE WERE FOUR UNTIL 2026-08-26** (9820–9839).
 * {@see self::NoNeighbours} was the answer to an empty `competitors` table, and
 * that table is empty for four different reasons — three of them ours. The
 * screen said *"We have not found nearby businesses to compare yet"* over a
 * spent Places budget, a sync that had never run, and a kill switch, which is
 * `docs/FAILURE-SHAPES.md`'s *a fetch failure and an ordinary empty result
 * sharing one value*.
 */
enum CompetitorComparisonState: string
{
    case Measured = 'measured';

    /**
     * No Google place id, so there is no point on the map to search around.
     *
     * ⚠️ **THE ONE STATE HERE AN OWNER CAN ACT ON** — confirm the listing —
     * which is why {@see CompetitorAbsenceReason} declares no
     * owner-actionable case and says so.
     */
    case NoPlaceId = 'no_place_id';

    /**
     * This platform has not asked Google about this location's neighbourhood.
     *
     * ⛔ **ADDED BECAUSE `NoNeighbours` WAS ANSWERING FOR IT** (9820–9839).
     * `visibility:sync-competitors` runs once a day, so a listing confirmed this
     * morning has no peers tonight for a reason that is entirely ours — and on a
     * deployment whose queue is not being consumed, for ever.
     * {@see CompetitorAbsenceReason} carries which of the three.
     */
    case NotCheckedYet = 'not_checked_yet';

    /**
     * We asked Google, and there is nobody comparable nearby.
     *
     * ⛔ **REACHABLE ONLY FROM A RECORDED SUCCESSFUL CHECK SINCE 2026-08-26.**
     * `CompetitorSignals::compare()` will not mint this off an empty table; it
     * has to find a `visibility.competitor_signals` run that actually reached
     * Google. **An empty collection is not evidence that a question was asked.**
     */
    case NoNeighbours = 'no_neighbours';

    /**
     * We tried and could not produce a comparison.
     *
     * {@see CompetitorAbsenceReason} is the reason and is never null
     * on this state.
     */
    case Unavailable = 'unavailable';
}
