<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Location;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Session;
use InvalidArgumentException;

/**
 * Which of a tenant's locations the account screens are acting on.
 *
 * ⛔ **THIS APPLICATION HAD NO LOCATION PICKER ANYWHERE, AND THE GAP WAS LOAD-
 * BEARING RATHER THAN COSMETIC** (2969, and 1220's rule is what created it). A
 * plugin, a gating threshold, a feedback page and a visibility figure are each
 * minted *per location*; with no way to say which one an owner meant, every
 * screen that needed one had two options and took the safer of them —
 * `Account\WidgetInstall`, `Account\ReviewRules` and the `/account` gating panel
 * all return **nothing at all** when the tenant has more than one location. The
 * commercial model sells additional locations at $99.99/mo, so that is a paying
 * customer who cannot switch their review widget on by any route.
 *
 * ⚠️ **A PICKER IS NOT A TENANT-FACING TOGGLE.** `CLAUDE.md`'s standing
 * instruction — *"never add a tenant-facing toggle; every toggle is a future
 * support ticket"* — has been overruled exactly once, by an explicit owner
 * ruling, for the invite threshold (1143), and it stands everywhere else. It
 * does not reach this. A toggle stores a **preference** that changes what the
 * product does; this stores a **cursor** that changes which row you are looking
 * at. Nothing here is remembered as policy, nothing here is read by a job, an
 * automation or a send decision, and clearing it changes no behaviour — the next
 * request simply lands on the first location again. **The test of the
 * distinction is whether the system behaves differently when nobody is
 * watching**, and by that test a selection that lives only in the viewer's own
 * session is navigation.
 *
 * ⚠️ **THE SELECTION LIVES IN THE SESSION, AND THE ALTERNATIVES WERE REFUSED FOR
 * STATED REASONS.** A column on `users` or `businesses` is a schema change whose
 * writer must then be maintained forever and whose value outlives the reason it
 * was set — decision 272's shape, in the shape of a cursor. **A location id in
 * the URL is the one that leaks**: an owner pasting `/account/widget?location=8`
 * into a support ticket, a browser history, a referer header or a shared
 * bookmark carries a selection across a boundary it was never scoped to, and a
 * link that renders differently for the person who receives it is the failure
 * mode nobody reports. The session is per-user, per-browser, expires on its own,
 * needs no migration, stores no personal data and cannot be shared by accident —
 * `CLAUDE.md`'s ambiguity rule reads for it on all three counts (less support
 * surface, less stored PII, lower marginal cost).
 *
 * ⚠️ **A STORED SELECTION IS RE-PROVEN AGAINST THE TENANT ON EVERY READ, NEVER
 * TRUSTED.** The session is user state and therefore attacker-adjacent, and it
 * outlives the thing that made it valid: an impersonation ending, an ownership
 * change, a location deleted. `current()` resolves it out of this tenant's own
 * locations or discards it. There is no path here on which a stored id becomes a
 * row without that lookup agreeing.
 *
 * ⚠️ **`select()` TAKES A MODEL AND CHECKS `business_id` ITSELF, WHICH IS 398'S
 * RULE APPLIED DELIBERATELY.** Resolving an id through the global scope would
 * mean the scope — or RLS beneath it — refuses a foreign location before any
 * guard here runs, leaving the guard unfalsifiable: delete it and the suite
 * stays green. So the tenant check compares the two ids that are already in
 * hand, exactly as `ReviewGating::assertBelongsToTenant()` and
 * `WidgetPlugins::assertBelongsToTenant()` do, and its test drives it with a
 * hydrated model rather than through a query.
 *
 * Specification: docs/DECISIONS.md 3060-3079, 1220, 1433, 2969.
 */
final class LocationContext
{
    /**
     * Where the cursor is kept.
     *
     * Not namespaced by tenant on purpose: `current()` resolves the stored id
     * out of *this* tenant's locations and discards anything it cannot find, so
     * a key carrying another tenant's id fails closed by the same lookup that
     * serves the ordinary case. A tenant-keyed name would add a second thing to
     * keep true without removing the need for that lookup.
     */
    public const string SESSION_KEY = 'account.selected_location_id';

    /**
     * Every location this tenant owns, in a stable order.
     *
     * ⚠️ **ORDERED, AND THAT IS NOT TIDINESS** (1888). PostgreSQL returns an
     * unordered query in whatever order it likes and not stably between calls,
     * so "the first location" would be a different row on two consecutive
     * requests — which is the arbitrary-selection defect this class exists to
     * end, reintroduced by omission. `name` is what the picker shows and `id` is
     * the tiebreak, so two locations sharing a name still order deterministically.
     *
     * @return Collection<int, Location>
     */
    public function options(): Collection
    {
        return Location::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Whether this tenant has anything to choose between.
     *
     * ⚠️ **THE SINGLE-LOCATION TENANT NEVER SEES THE CONTROL.** That is the
     * overwhelmingly common shape, and a select with one option on every screen
     * is clutter that asks a question with one answer — `CLAUDE.md`'s *"Never
     * add a tenant-facing toggle"* reasoning, and the reason this is a
     * separate method rather than a `count() > 1` written out at each call site.
     */
    public function hasChoice(): bool
    {
        return $this->options()->count() > 1;
    }

    /**
     * The location the account screens should act on, or null when there is none.
     *
     * Three cases, and the third is the one that did not exist before:
     *
     *   no locations    null — nothing to act on, and the screens say so
     *   one location    that one, with no session involved at all
     *   two or more     the stored selection, or the first in `options()` order
     *
     * ⚠️ **FALLING BACK TO THE FIRST IS ONLY HONEST BECAUSE THE PICKER IS ON
     * SCREEN NAMING IT.** 1220's rule was *absent rather than guessing*, and the
     * reason was never that a default is wrong — it is that a silent one is: a
     * screen writing a setting the owner believes is account-wide into one
     * branch of several, with nothing on the page saying which. A disclosed
     * default is a different object. **The disclosure is not left to whoever
     * writes the next screen** — `Architecture/AccountScreensTest` fails the
     * build on any component that reads this class without rendering
     * `<x-account.location-picker>`, so the fallback and the label that makes it
     * honest cannot be separated.
     */
    public function current(): ?Location
    {
        $options = $this->options();

        if ($options->isEmpty()) {
            return null;
        }

        if ($options->count() === 1) {
            return $options->first();
        }

        $stored = Session::get(self::SESSION_KEY);

        if (is_int($stored) || (is_string($stored) && ctype_digit($stored))) {
            $selected = $options->firstWhere('id', (int) $stored);

            if ($selected instanceof Location) {
                return $selected;
            }
        }

        return $options->first();
    }

    /**
     * Point the account screens at this location.
     *
     * @throws InvalidArgumentException when the location is not this tenant's
     */
    public function select(Location $location): void
    {
        $this->assertBelongsToTenant($location);

        Session::put(self::SESSION_KEY, $location->id);
    }

    /**
     * Drop the cursor.
     *
     * Used when a session should not carry a selection into whatever comes
     * next — signing out, and the end of an impersonation.
     */
    public function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * The *wrong tenant* case neither the global scope nor RLS can catch, because
     * the id arrives on a model that is already in memory rather than through a
     * query. `location_id` comes from the passed model and the tenant comes from
     * context, and the two are checked against each other here while both are in
     * hand — the same guard, for the same reason, as
     * `ReviewGating::assertBelongsToTenant()`.
     *
     * ⚠️ **DRIVING THIS RED REQUIRES CALLING IT DIRECTLY** (398). Every route
     * into it from the web resolves the id through the global scope first, so a
     * test that goes through the controller proves the *scope* works and would
     * stay green with this method's body deleted.
     */
    private function assertBelongsToTenant(Location $location): void
    {
        if ($location->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That location belongs to another tenant. The account screens act on the '
            .'selected location, so pointing them at somebody else\'s would write one '
            .'business\'s settings against another business\'s branch.'
        );
    }
}
