<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\ActuationTier;
use App\Models\Location;
use App\Services\Actuation\WordPress\WordPressCredentials;
use App\Services\Tenant\LocationWebsite;
use App\Services\Warehouse\PixelSightings;
use App\Support\Tenancy;
use InvalidArgumentException;

/**
 * How much of this location's website we can actually reach, worked out from
 * live facts every time it is asked.
 *
 * ⛔ **DERIVED, NEVER STORED** (`BUILD-PLAN` §2.11.2, and {@see ActuationTier}'s
 * own docblock). There is no `locations.tier` column and adding one is the
 * defect: a stored tier drifts from the facts that justify it, always in the
 * direction of claiming write access this platform no longer has. `messaging_lane`
 * is the precedent — derived and unsettable — and the consequence here is a
 * publish attempted against a plugin that was uninstalled last month.
 *
 * ## Three answers, and the third is not a tier
 *
 *   null  **we do not know yet** — no confirmed website, or nothing has looked at
 *         it. ⛔ **NOT T4.** T4 is a real tier with a real deliverable (`41` Part
 *         1: a prioritised fix list the owner applies, which is `handoff()`), and
 *         it is a claim that we *have looked* and there is no write access. Saying
 *         T4 when nobody has looked would hand an owner an advisory about a
 *         website we have never seen. This is 229's rule wearing a tier's clothes,
 *         and the one the scanner's test pins.
 *   T3    the pixel has been seen running on that host recently
 *   T4    we looked, and there is no write path
 *
 * ⛔ **"T1 IS NOT DERIVABLE TODAY" WAS TRUE AND IS NOT — CORRECTED 2026-08-20 BY
 * SLICE G (5747), AND BOTH READINGS ARE KEPT.** This paragraph read *"an arm
 * reading a table that does not exist would be 272's shape with a tier attached:
 * unreachable, untested, and indistinguishable from a working one until
 * somebody's site is published to"*, on 5541's reasoning that no plugin, adapter
 * or credential store existed. **Slice F1 built the store**, 5599 recorded that
 * the arm *"is owed and belongs to whoever merges second"*, and this slice is
 * that merge. ⚠️ **What T1 means moved with it** (5580): §2.11.2 says *"T1 from a
 * live plugin connection"* and conflict 7 found a T1 write needs no plugin of
 * ours at all — so the predicate is a **verified Application Password**, not an
 * installed plugin.
 *
 * ⛔ **AND THE HALF OF THE OLD PARAGRAPH THAT SURVIVES IS THE LOAD-BEARING
 * HALF**: *detecting WordPress is not a connection to it.* `wordpress_detected_at`
 * still buys {@see self::upgrade()} and nothing else, and reading it as T1 would
 * have a publishing job write to a site nobody granted us access to.
 *
 * ⚠️ **T0 AND T2 ARE NEVER RETURNED.** Both are declared on the enum and neither
 * is implemented (row 16e owns T0; T2 is Stage 7 and `41` Part 5 makes it
 * auto-detect-only in v1), so returning one would hand a caller a tier that raises
 * the moment it is acted on — 219's silent-downgrade failure with its sign
 * flipped. `cloudflare_detected_at` is recorded and read by nothing here.
 */
final class ActuationTiers
{
    public function __construct(
        private readonly PixelSightings $sightings,
        private readonly WordPressCredentials $credentials,
    ) {}

    /**
     * The tier we can act through today, or null when we cannot yet say.
     */
    public function for(Location $location): ?ActuationTier
    {
        $this->assertBelongsToTenant($location);

        $url = $location->website_url;

        if ($url === null || $location->website_confirmed_at === null) {
            return null;
        }

        // ⛔ **ABOVE THE SCAN GUARD, AND THAT ORDER IS ARGUED** (5747). The scan
        // guard exists because a tier asserted about a website nobody has looked
        // at is a claim we have not earned — and a stored credential is a
        // stronger observation than a scan: it exists only because
        // `WordPressCredentials::connect()` talked to that site's REST API and
        // §19.7's gate passed. A location whose owner has connected WordPress
        // and whose background probe has not finished is not "we do not know
        // yet".
        //
        // ⚠️ **THE STORE'S ANSWER, NOT THE ADAPTER'S** (5599). `isConnected()`
        // is a fact about the row and costs no request; `WordPressAdapter::health()`
        // is the live answer and costs two calls to a customer's website, so a
        // tier derived from it would put a network round trip inside every
        // screen render. **The live check has not gone away** — it is
        // `Publishing::canWriteToSite()`'s third condition, asked once, in a
        // queued job, immediately before anything is written.
        if ($this->credentials->isConnected($location)) {
            return ActuationTier::T1;
        }

        // ⚠️ THE SCAN IS PART OF THE ANSWER AND NOT AN OPTIMISATION. Without it,
        // a location whose website was confirmed a minute ago and never looked at
        // reads as T4 — an advisory tier asserted about a site nobody has seen.
        if ($location->website_scanned_at === null) {
            return null;
        }

        if ($this->sightings->seenOnHost(LocationWebsite::hostOf($url))) {
            return ActuationTier::T3;
        }

        return ActuationTier::T4;
    }

    /**
     * Could we take a change made at this tier back off this site today?
     *
     * ⛔ **THE STATE 5759 AND 5819(d) LEFT OWED, ANSWERED WITHOUT TOUCHING THE
     * NETWORK** (5833). An owner may disconnect their website while a change of
     * ours is still on it — `WordPressCredentials::forget()` is deliberately
     * unconditional — and what that leaves is a `site_changes` row with no route
     * back to the page it describes. Slice H's sweep then retries the revert
     * nightly for ever. **The owner's screen must not offer an Undo button for
     * it**, and this is the question it asks.
     *
     * ⛔ **A DIFFERENT QUESTION FROM {@see self::for()}, AND NOT TIER EQUALITY.**
     * The tempting predicate is *is this location still on the tier the change
     * was made at* — and it is wrong on T3 in the dangerous direction: undoing a
     * T3 change is this platform ceasing to serve a payload, which needs nothing
     * from the tenant's site at all, so a location whose pixel stopped being
     * seen would be told we cannot undo something we can undo instantly.
     * {@see ActuationTier::undoReachesTheirServer()} is the distinction, and it
     * is on the enum because it is a fact about the rung rather than about a
     * location.
     *
     * ⛔ **IT IS NOT GATED ON `actuation.enabled` OR ON ENTITLEMENT, ON 5813's
     * ARGUMENT.** Publishing is something a tenant buys; **taking our own edit
     * back off is the opposite of a thing we sell them**, and refusing it
     * because a switch is off or an invoice went unpaid would leave our content
     * on somebody's website with no route out. Every deployment today has
     * `actuation.enabled` false, so a gate here would make the Undo button
     * unreachable everywhere while looking perfectly correct.
     *
     * ⚠️ **THE STORE'S ANSWER, NOT THE ADAPTER'S** (5599, 5747). `isConnected()`
     * is a row read; `WordPressAdapter::health()` is two requests to a
     * customer's website, and this is asked once per location on every render of
     * the undo screen. **The live check has not gone away** — the adapter is
     * still asked for real when the undo runs, and a refusal there is what the
     * screen reports honestly rather than what it predicts.
     */
    public function canUndoAt(Location $location, ActuationTier $tier): bool
    {
        $this->assertBelongsToTenant($location);

        if (! $tier->undoReachesTheirServer()) {
            return true;
        }

        return $this->credentials->isConnected($location);
    }

    /**
     * The stronger tier this site could reach, if any — the upgrade offer.
     *
     * ⚠️ **AN OFFER, NOT A CAPABILITY, AND THE DISTINCTION IS THE WHOLE REASON
     * THIS IS A SECOND METHOD.** `41` Part 1 turns *"this site needs a stronger
     * tier than we have"* and *"there was nothing to do here"* into two different
     * sentences an owner is told, one of which is an offer. Returning T1 from
     * {@see self::for()} on the strength of a WordPress detection would collapse
     * them, and would have a publishing job try to write through a plugin nobody
     * has installed.
     *
     * ⚠️ **NULL WHEN THE ANSWER ADDS NOTHING** — no detection, or the site is
     * already on the tier being offered.
     */
    public function upgrade(Location $location): ?ActuationTier
    {
        $this->assertBelongsToTenant($location);

        if ($location->wordpress_detected_at === null) {
            return null;
        }

        $current = $this->for($location);

        // ⛔ NOT OFFERED WHEN NOTHING IS KNOWN. An upgrade sentence on a site we
        // have never reached is a claim about it, and this class's whole argument
        // is that we do not make those.
        //
        // ⛔ **AND NOT OFFERED TO A LOCATION THAT IS ALREADY THERE** (5747). Until
        // this slice `for()` could not return T1, so the arm was unreachable and
        // its absence cost nothing; now a connected WordPress site would be sold
        // the tier it is already on — `41` Part 1's upgrade sentence turned into
        // the nag it explicitly is not.
        return $current === null || $current === ActuationTier::T1 ? null : ActuationTier::T1;
    }

    private function assertBelongsToTenant(Location $location): void
    {
        if ($location->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That location belongs to another tenant. A tier read here would answer a question '
            .'about somebody else\'s website with this tenant\'s pixel data.',
        );
    }
}
