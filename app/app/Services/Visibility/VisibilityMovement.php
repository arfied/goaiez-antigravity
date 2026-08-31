<?php

declare(strict_types=1);

namespace App\Services\Visibility;

use App\Enums\VisibilityState;

/**
 * The difference between two windows — the only thing decision 1085 permits the
 * normal surface to state.
 *
 * `28` §5.3.3's locked language rule is *"state movement and direction, never a
 * position, and never anything that implies a ranking guarantee"*, and its own
 * example sentence is a movement: *"More people found you on Google Maps this
 * month — 340 up from 280."* So movement is a first-class value here rather than
 * arithmetic left to whichever screen renders it, because the arithmetic has one
 * trap in it and it is not obvious.
 *
 * ⚠️ **THE TRAP: SUBTRACTING AN UNAVAILABLE WINDOW FROM A MEASURED ONE.** The
 * natural implementation reads both windows' clicks and subtracts. If the earlier
 * window is `Unavailable` — a revoked grant since re-authorised, a quota wall
 * that day, a property only just chosen — the tempting `?? 0` reports a *rise
 * from nothing*, which is a fabricated success story printed under a business's
 * name. The inverse is worse: an unavailable *current* window against a measured
 * earlier one reports a collapse to zero, which is decision 1084's forbidden
 * sentence exactly.
 *
 * Neither is expressible here. {@see between()} refuses unless **both** readings
 * are `Measured`, and the refusal is a case rather than a null so the caller has
 * to render something for it. This class is built in phase 1, with no screen, so
 * that phase 2 composes two readings through a function that already knows this
 * rather than writing the subtraction by hand.
 */
enum VisibilityMovement: string
{
    case Up = 'up';
    case Down = 'down';
    case Unchanged = 'unchanged';

    /**
     * One or both windows cannot be compared.
     *
     * Not "flat", and not zero. The honest sentence is *"we cannot compare these
     * two periods"*, which is a different thing to say than *"nothing changed"*.
     */
    case Indeterminate = 'indeterminate';

    /**
     * Movement in the metric `28` §5.3.3's own example sentence uses.
     *
     * Impressions rather than clicks: §5.3.3's sentence is *"more people found
     * you"*, which is the impression count. Clicks measure what they did next.
     */
    public static function between(VisibilityReading $current, VisibilityReading $earlier): self
    {
        if ($current->state !== VisibilityState::Measured || $earlier->state !== VisibilityState::Measured) {
            return self::Indeterminate;
        }

        $now = $current->totals()->impressions;
        $before = $earlier->totals()->impressions;

        return match (true) {
            $now > $before => self::Up,
            $now < $before => self::Down,
            default => self::Unchanged,
        };
    }
}
