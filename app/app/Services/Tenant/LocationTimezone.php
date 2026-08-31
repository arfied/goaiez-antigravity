<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\SupportWriteSubject;
use App\Http\Middleware\Impersonating;
use App\Models\Location;
use App\Services\AuditService;
use App\Services\Impersonation\Impersonation;
use App\Support\Tenancy;
use DateTimeZone;
use InvalidArgumentException;

/**
 * The only writer of `locations.timezone` (1597).
 *
 * ⚠️ **WITHOUT THIS, SLICE 5 UNBLOCKS MARKETING ONLY WHERE THERE IS NO RULE TO
 * APPLY.** `ConsentService::stateRefusal()` refuses with `QuietHours` when the
 * customer's location has no timezone — correctly, because a window evaluated in
 * the *server's* timezone passes at the wrong hours and reports as working. But
 * `TenantProvisioner` creates a location with a name and nothing writes the
 * column, so every contact in exactly the states counsel will write rules for
 * would have stayed refused around the clock while every other state started
 * flowing. That is decision 272's shape hiding behind the fix for decision 272's
 * shape, and it would have looked like the slice worked.
 *
 * ⚠️ **OWNER-SET, NEVER DERIVED FROM `region_code`** (1598). Fifteen states span
 * more than one clock, and `UsState::timezones()` is the list: Florida, Indiana,
 * Kentucky, Michigan and Tennessee straddle Eastern and Central; Kansas,
 * Nebraska, the Dakotas and Texas straddle Central and Mountain; Idaho, Oregon
 * and Nevada straddle Pacific and Mountain; Arizona is split by whether DST is
 * observed at all; and Alaska carries the Aleutians. ⚠️ **This paragraph said
 * "six" and then listed eleven** — Arizona and Nevada were added to the enum and
 * not to the prose, which is how a reader concludes the enum is the stale copy.
 * So a state-to-zone table is wrong for millions of people, and **it is wrong in the
 * direction that texts them earlier**: a Panhandle business filed under
 * `America/New_York` has its quiet hours end an hour before the statute does.
 * The whole point of the state gate is that a plausible guess is worse than an
 * honest refusal; deriving the hour would put the guess back one field over.
 *
 * ⚠️ **`UTC` AND `GMT` ARE REFUSED BY NAME.** Both are valid IANA identifiers
 * and both are the value that arrives when somebody reaches for a default rather
 * than an answer — which is precisely the "evaluated in the server's timezone"
 * failure the nullable column was refusing. No US business keeps its hours in
 * UTC, so accepting it would only ever record a non-answer as an answer.
 */
final class LocationTimezone
{
    /**
     * Identifiers that are a stand-in for "not answered" rather than a place.
     *
     * @var list<string>
     */
    private const array REFUSED = ['UTC', 'GMT', 'Etc/UTC', 'Etc/GMT', 'Z'];

    public function __construct(
        private readonly AuditService $audit,
        private readonly Impersonation $impersonation,
    ) {}

    /**
     * Record which timezone this location keeps its hours in.
     *
     * @throws InvalidArgumentException when the identifier is not a usable IANA zone
     */
    public function set(Location $location, string $timezone, string $actor): Location
    {
        $this->assertBelongsToTenant($location);

        $timezone = trim($timezone);

        if (! $this->isUsable($timezone)) {
            throw new InvalidArgumentException(
                'Choose the timezone this location actually keeps its hours in. It decides '
                .'the hours we are allowed to text your customers, so a placeholder would '
                .'shift that window rather than leave it unset.',
            );
        }

        $before = $location->timezone;

        $location->timezone = $timezone;
        $location->save();

        // ⚠️ BOTH SIDES, PER `AuditService::recordChange()`'s own rule: an entry
        // recording only the new zone cannot answer why a send that happened
        // last week was inside the window.
        $this->audit->recordChange(
            'location.timezone_set',
            $actor,
            ['timezone' => $before],
            ['timezone' => $timezone],
            $location,
        );

        $this->recordSupportWrite();

        return $location;
    }

    /**
     * Add support's own audit row and owner-facing feed entry when this write
     * happened inside an act-as session — {@see Impersonation::recordWrite()}.
     *
     * ⚠️ **INERT FOR AN ORDINARY OWNER WRITE.** `current()` returns null the
     * moment nobody is impersonating. A view-only session cannot reach here at
     * all: the read-only connection {@see Impersonating}
     * engages refuses the `locations` UPDATE above before this line runs, so a
     * non-null session here is always act-as.
     */
    private function recordSupportWrite(): void
    {
        $session = $this->impersonation->current();

        if ($session === null) {
            return;
        }

        $this->impersonation->recordWrite($session, SupportWriteSubject::LocationTimeZone);
    }

    /**
     * The countries whose zones an owner may pick from.
     *
     * ⚠️ **`'US'` ALONE EXCLUDED EVERY TERRITORY `UsState` ADMITS** (1614).
     * PHP's `PER_COUNTRY` list for `US` holds the fifty states, DC and Hawaii —
     * and **not** Puerto Rico, Guam, American Samoa, the Northern Marianas or
     * the US Virgin Islands, each of which has its own ISO country code and its
     * own list. So a Puerto Rican tenant could never save a timezone at all, and
     * `ConsentService::stateRefusal()` refuses with `QuietHours` when the column
     * is null: the day a rule row existed for `PR`, that tenant was refused
     * around the clock with no control on any screen able to fix it. That is
     * decision 272's shape reached from the other direction — a control that
     * exists and cannot be used.
     *
     * The five codes are the same five territory cases `UsState` carries, and
     * for the same reason: each has its own legislature, so each can carry a
     * `state_messaging_rules` row.
     *
     * @var list<string>
     */
    private const array COUNTRIES = ['US', 'PR', 'GU', 'AS', 'MP', 'VI'];

    /**
     * The zones offered to an owner.
     *
     * ⚠️ **THE SELECT IS NARROWER THAN THE SERVICE**, which is 398's rule
     * applied to a list rather than a guard: the screen offers the six country
     * lists above because every tenant this product serves is a US local
     * business, and the service accepts any real IANA zone so the refusal in
     * `set()` is about the *value* being unusable rather than about the list a
     * particular screen happened to render. ⚠️ **This sentence said "US-only"
     * until 1621** and had been false since 1614 added the five territories —
     * the kind of stale claim that makes the next reader "fix" `COUNTRIES` back
     * to `['US']` and lock a Puerto Rican tenant out again.
     *
     * ⚠️ **THE `REFUSED` FILTER IS DEFENSIVE AND MATCHES NOTHING TODAY** — the
     * six country lists contain no `UTC`, `GMT` or `Etc/*` entry, so this is
     * decision 256's vacuous shape and is kept deliberately rather than by
     * accident. What it buys is that `offered()` and `isUsable()` cannot
     * disagree: every option this renders must be one `set()` accepts, or the
     * select grows an entry that refuses when clicked. A test asserts that
     * agreement, so the guarantee is the test's and this line is belt.
     *
     * @return list<string>
     */
    public static function offered(): array
    {
        $zones = [];

        foreach (self::COUNTRIES as $country) {
            foreach (DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $country) as $zone) {
                if (! in_array($zone, self::REFUSED, true) && ! in_array($zone, $zones, true)) {
                    $zones[] = $zone;
                }
            }
        }

        return $zones;
    }

    /**
     * Whether this identifier names a real place we can evaluate hours in.
     */
    private function isUsable(string $timezone): bool
    {
        if ($timezone === '' || in_array($timezone, self::REFUSED, true)) {
            return false;
        }

        return in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * The wrong-tenant refusal — the case RLS cannot catch once a model is in
     * hand, refused here for the reason `CustomerEditor` states.
     */
    private function assertBelongsToTenant(Location $location): void
    {
        if ($location->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That location belongs to another tenant. The timezone decides when that '
            .'tenant may message their customers, so this would move somebody else\'s window.',
        );
    }
}
