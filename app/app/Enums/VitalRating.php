<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where one measurement sits against the published boundary for its metric —
 * the three bands Google names, and nothing else.
 *
 * ⛔ **THESE ARE NOT OUR BANDS AND NOTHING HERE MAY BE INVENTED** (decision
 * 5724). The boundaries that separate these three cases are Google's published
 * figures, fetched, quoted and dated in [[\App\Support\CoreWebVitals]]. A
 * fourth band, a "nearly good", or a boundary nudged to make more tenants land
 * in [[self::Good]] would be `28` §4.3's *"never a fabricated win"* broken by a
 * constant instead of by a sentence.
 *
 * ⚠️ **A METRIC WITH NO PUBLISHED BOUNDARY HAS NO RATING AT ALL, AND THAT IS
 * WHY THIS ENUM HAS NO `Unknown` CASE.** [[\App\Support\CoreWebVitals::for()]]
 * returns `null` rather than a case, and [[\App\Services\Warehouse\VitalReading::rating()]]
 * passes the `null` on. An `Unknown` case is the shape somebody eventually
 * writes `!== VitalRating::Poor` against, which reads "not bad" and means "we
 * never checked".
 *
 * ⚠️ **A RATING IS A STATEMENT ABOUT AN ABSOLUTE SPEED AND A
 * [[SpeedVerdict]] IS A STATEMENT ABOUT A CHANGE.** They answer different
 * questions and a slow site can improve while a fast one stands still, so
 * neither is derivable from the other.
 */
enum VitalRating: string
{
    /**
     * At or below the published "good" boundary.
     *
     * ⚠️ **INCLUSIVE, BECAUSE THE SOURCE IS**: *"sites should strive to have
     * Largest Contentful Paint of 2.5 seconds **or less**"*. The two Google
     * properties word this differently and
     * [[\App\Support\CoreWebVitals]] records which one is followed and why.
     */
    case Good = 'good';

    /**
     * Above the "good" boundary and at or below the "poor" one.
     */
    case NeedsImprovement = 'needs_improvement';

    /**
     * Above the published "poor" boundary.
     */
    case Poor = 'poor';
}
