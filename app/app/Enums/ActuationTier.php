<?php

declare(strict_types=1);

namespace App\Enums;

use App\Exceptions\UnbuiltActuationTier;

/**
 * How much of somebody else's website this platform can actually reach — doc
 * `41` Part 1's coverage matrix.
 *
 * ⛔ **DERIVED, NEVER STORED ON A LOCATION** (`BUILD-PLAN` §2.11.2). A tier is a
 * statement about live facts — is the plugin connected, has the pixel been
 * sighted — and a stored copy is a column that drifts from the facts that
 * justify it, in the direction of claiming write access this platform no longer
 * has. `messaging_lane` is the precedent: derived and unsettable. What
 * `site_changes.tier` records is a different claim — *the tier a change was
 * actually made through*, which is a historical fact about one write and stays
 * true afterwards.
 *
 * ⚠️ **ALL FIVE RUNGS ARE DECLARED AND ONLY THREE ARE REACHABLE**, which is
 * `FetchTier`'s shape and 219's rule. T0 is the native-integration tier owned by
 * row 16e and T2 is the Cloudflare-worker tier deferred to Stage 7 (`41` Part 5
 * makes T2 auto-detect-only in v1); both are declared because the *policy* —
 * which tier a location qualifies for, and what it is honestly told — has to be
 * expressible before the actuator exists.
 *
 * ⛔ **ASKING TO ACT THROUGH AN UNBUILT TIER RAISES; IT NEVER SILENTLY
 * DOWNGRADES** (219). A downgrade to advisory would make *"this site needs a
 * stronger tier than we have"* and *"there was nothing to do here"* the same
 * outcome, and `41` Part 1 turns that difference into two different sentences
 * the owner is told — one of which is an upgrade offer.
 */
enum ActuationTier: string
{
    /**
     * A site this platform built and hosts. Row 16e's, not row 9's.
     */
    case T0 = 't0_native';

    /**
     * Full server-side writes to a WordPress site. **WordPress only in v1**
     * (`41` Part 2, D-164): Shopify, Wix and Webflow are app-store submissions
     * rather than plugins, and each needs its own doc and build row.
     *
     * ⛔ **THIS SAID "THE WORDPRESS PLUGIN" AND THE VALUE STILL SAYS `t1_plugin`
     * — CORRECTED 2026-08-19 (5580).** §2.11.5 conflict 7 found that a T1 write
     * needs no plugin of ours at all: Application Passwords are WordPress core
     * (5.6+), and `App\Services\Actuation\WordPress\WordPressAdapter` reaches a
     * tenant's own site over core REST with one. **What makes a location T1 is a
     * live, verified credential**, not an installed plugin.
     *
     * ⚠️ **THE STRING IS DELIBERATELY NOT RENAMED.** It is written into every
     * `site_changes.tier` row and read back by every screen and measurement
     * window; renaming it to match the corrected meaning would be a data
     * migration bought with nothing, and the `t1_` prefix is the part anything
     * branches on. The plugin does still arrive (F2) for the three things core
     * REST cannot reach — the IndexNow key file, the `robots.txt` sitemap line,
     * and the seven speed fixes (5581) — so the word is not even wrong, it is
     * just no longer the whole of it.
     */
    case T1 = 't1_plugin';

    /**
     * A Cloudflare worker in front of the tenant's origin. Detected and recorded
     * in v1, actuated in Stage 7.
     */
    case T2 = 't2_edge';

    /**
     * Client-side, through the pixel already on the page. Second-wave indexed,
     * and `41` Part 1 requires that be said out loud rather than implied.
     */
    case T3 = 't3_pixel';

    /**
     * No write access at all: a prioritised fix list the owner applies. **This
     * is a real tier and not a failure** — it is `handoff()`, the half of every
     * automation `CLAUDE.md` requires be built in the same ticket as `execute()`.
     */
    case T4 = 't4_advisory';

    /**
     * Whether this project can actually act through the tier today.
     */
    public function isImplemented(): bool
    {
        return match ($this) {
            self::T1, self::T3, self::T4 => true,
            self::T0, self::T2 => false,
        };
    }

    /**
     * Whether acting through this tier writes to a website we do not own.
     *
     * ⚠️ **T4 IS FALSE AND THAT IS THE POINT OF IT.** Advisory transmits
     * nothing, so the whole containment argument — snapshots, rollback,
     * quarantine — is about the other rungs.
     */
    public function writesToTheSite(): bool
    {
        return match ($this) {
            self::T0, self::T1, self::T2, self::T3 => true,
            self::T4 => false,
        };
    }

    /**
     * Whether taking a change of this tier back off needs a request to a server
     * somebody else runs.
     *
     * ⛔ **NOT THE SAME QUESTION AS {@see self::writesToTheSite()}, AND THE
     * DIFFERENCE IS WHY THE OWNER'S UNDO SCREEN CAN BE INSTANT ON ONE TIER AND
     * NOT THE OTHER** (5834). Both tiers change what a visitor sees, so both
     * *write to the site* in that method's sense. Only T1 does it by asking the
     * tenant's own CMS: a T3 change is a payload **this platform serves**, so
     * undoing it is this application ceasing to serve it and reaches nobody.
     *
     * ⚠️ **T0 AND T2 ANSWER `true` THOUGH NEITHER IS REACHABLE**, on the
     * conservative direction rather than on a tidy default: both are edge or
     * native integrations against infrastructure we do not own, and an unbuilt
     * rung guessed as *instant* would put a call to somebody else's server
     * inside a web request the day it is built. {@see self::assertImplemented()}
     * refuses them long before this is asked.
     *
     * ⚠️ **T4 IS `false` AND MEANS NOTHING**: advisory transmits nothing, so it
     * never has a change set to undo. The arm exists because a `match` with no
     * `default` is what makes widening this enum redden the build rather than
     * quietly pick an answer (5748).
     */
    public function undoReachesTheirServer(): bool
    {
        return match ($this) {
            self::T0, self::T1, self::T2 => true,
            self::T3, self::T4 => false,
        };
    }

    /**
     * Refuse loudly if nothing can act through this tier.
     *
     * @throws UnbuiltActuationTier
     */
    public function assertImplemented(): void
    {
        if (! $this->isImplemented()) {
            throw UnbuiltActuationTier::for($this);
        }
    }
}
