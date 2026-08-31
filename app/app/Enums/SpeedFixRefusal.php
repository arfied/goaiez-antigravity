<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Actuation\SpeedFixes;

/**
 * Why one of `28` §4.1's seven fixes is not being applied to one site today.
 *
 * ⛔ **A FIX THAT CANNOT BE APPLIED IS RECORDED AS SUCH RATHER THAN DROPPED**
 * (1222). A silently skipped fix and a fix nobody thought of are the same thing
 * to every reader downstream, and on this path the difference is what an owner
 * is owed an explanation for — six of the seven cases below are reasons a person
 * would ask about.
 *
 * ⚠️ **A REASON IS NOT A SCREEN.** {@see SpeedFixes::plan()} returns these and
 * {@see SpeedFixes::sentenceFor()} turns the one an owner can act on into a
 * sentence; the rest are for an operator reading a plan.
 */
enum SpeedFixRefusal: string
{
    /**
     * We have not been able to say what we can reach on this website.
     *
     * ⚠️ **NOT T4 AND NOT "NOTHING TO DO"** — `ActuationTiers::for()` returns
     * null for a site with no confirmed address and for one nobody has looked at
     * yet, and 5543 is the argument for keeping those out of the advisory tier.
     */
    case TierUnknown = 'tier_unknown';

    /** `28` §4.1 does not permit this fix at this site's tier. */
    case TierCannotApply = 'tier_cannot_apply';

    /**
     * The bound adapter cannot write the field this fix needs.
     *
     * ⛔ **THIS IS THE ANSWER FOR ALL SEVEN FIXES ON EVERY DEPLOYMENT THAT
     * EXISTS** (5851). The core-REST adapter writes `title`, `content` and
     * `excerpt`; the log driver writes nothing at all. Both say so through
     * `CmsAdapter::fieldSupport()`, with a fixed reason per field that is stored
     * and shown.
     */
    case AdapterCannotWrite = 'adapter_cannot_write';

    /** This kind of change is resting on this site (`site_change_quarantines`). */
    case Quarantined = 'quarantined';

    /**
     * Another speed fix is on this site and has not been judged.
     *
     * ⛔ **§4.3's *"one at a time per site"*, and it is also a partial unique
     * index** — the service refuses first so the answer can be explained, and
     * the index refuses regardless so the rule is not a thing to remember.
     */
    case AnotherFixInFlight = 'another_fix_in_flight';

    /** Less than §4.3's ≥48h since the last speed fix landed on this site. */
    case TooSoon = 'too_soon';

    /** `actuation.enabled` is off, or the site is not writable right now. */
    case ActuationOff = 'actuation_off';
}
