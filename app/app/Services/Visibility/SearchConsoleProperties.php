<?php

declare(strict_types=1);

namespace App\Services\Visibility;

use App\Contracts\SearchConsoleClient;
use App\Enums\ConnectionStatus;
use App\Enums\OauthProvider;
use App\Exceptions\ProviderNotConnected;
use App\Exceptions\SearchConsoleRequestFailed;
use App\Models\Business;
use App\Models\GscSiteProperty;
use App\Models\Location;
use App\Models\OauthConnection;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Gsc\SiteProperty;
use App\Services\Oauth\TokenService;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * The one place this application decides which Search Console property a
 * location is.
 *
 * ⚠️ **THE WHOLE POINT IS {@see choose()}, AND IT IS THE ONE METHOD SOMEBODY WILL
 * BE TEMPTED TO SIMPLIFY.** It re-fetches the tenant's property list from Google
 * and refuses anything not on it. The simplification — trusting the `site_url`
 * that arrived in the request, since the picker only rendered real ones — is what
 * turns decision 1083's opaque-reference hazard from a design note into a live
 * cross-tenant read: a `siteUrl` names no tenant, so a posted one that this
 * account cannot see would be stored, synced, and reported to the wrong business
 * as their own traffic. Decision 531 recorded the same shape for Zernio's
 * `accountId`, and decision 398 recorded what happens when a guard's test can be
 * satisfied by an outer check that refuses first.
 *
 * The re-fetch costs one `sites.list` call, which is on the 200 QPM per-user
 * bucket rather than the Search Analytics one, and happens once per connect.
 *
 * ⚠️ **AND NOTHING HERE INFERS A PROPERTY FROM `locations.website_url`.** Decision
 * 1083 is explicit: a business whose site is a page on a franchisor's domain
 * would be silently mapped to the franchisor's whole property.
 */
final class SearchConsoleProperties
{
    public function __construct(
        private readonly SearchConsoleClient $client,
        private readonly TokenService $tokens,
        private readonly AuditService $audit,
    ) {}

    /**
     * Every property the tenant's connected Google account can see, including
     * the ones it cannot read.
     *
     * ⚠️ The unreadable ones are returned rather than filtered, so a picker can
     * *show* them greyed out with a reason. Filtering silently would leave an
     * owner staring at a list missing the property they came to select, with
     * nothing telling them their verification never completed — the support
     * ticket writes itself and the answer is not in our product.
     *
     * @return list<SiteProperty>
     *
     * @throws SearchConsoleRequestFailed
     * @throws ProviderNotConnected
     */
    public function available(Location $location): array
    {
        Tenancy::idOrFail();

        return $this->client->properties(self::businessFor($location));
    }

    /**
     * Point a location at one of its own account's properties.
     *
     * ⚠️ **AUDITED, ADDED WAVE 38 LANE B.** This is the one method that decides
     * which third-party account's data this platform reads and reports as a
     * tenant's own visibility, and — until this wave — no caller in `app/` had
     * ever reached it at all. `CLAUDE.md`'s *"Every sensitive action →
     * append-only audit log"* applies to it on the same footing as
     * `GbpConnections::begin()`/`disconnect()`, which record `audit_log` rows
     * for the equivalent Google Business Profile decision.
     *
     * @throws InvalidArgumentException when the property is not one this account
     *                                  holds, or holds at a level that cannot
     *                                  read Performance data. A caller can act on
     *                                  both — the first means "pick another", the
     *                                  second means "finish verifying it in
     *                                  Search Console" — which is why this is an
     *                                  exception with a code rather than a
     *                                  SQLSTATE from the matching CHECK.
     */
    public function choose(Location $location, string $siteUrl, ?User $by = null): GscSiteProperty
    {
        $tenantId = Tenancy::idOrFail();

        $match = null;

        foreach ($this->available($location) as $property) {
            // Exact string comparison, deliberately. A property key is opaque:
            // `https://example.com/` and `https://www.example.com/` are two
            // different properties, and any normalisation here would let one be
            // chosen while the other is stored.
            if ($property->siteUrl === $siteUrl) {
                $match = $property;

                break;
            }
        }

        if ($match === null) {
            throw new InvalidArgumentException('property_not_available');
        }

        if (! $match->permissionLevel->canReadPerformance()) {
            throw new InvalidArgumentException(
                $match->permissionLevel->refusalReason() ?? 'property_not_readable'
            );
        }

        // Read before the write, and only for the audit trail's "before" —
        // nothing here branches on it.
        $previousSiteUrl = $this->forLocation($location)?->site_url;

        $property = GscSiteProperty::updateOrCreate(
            // The tenant is in the match clause explicitly, so this can never
            // find and mutate another business's row even if the global scope
            // were removed — TokenService::store()'s reasoning.
            ['business_id' => $tenantId, 'location_id' => $location->getKey()],
            [
                'site_url' => $match->siteUrl,
                'permission_level' => $match->permissionLevel,
                'chosen_by_user_id' => $by?->getKey(),
                'chosen_at' => CarbonImmutable::now(),
            ],
        );

        $this->audit->recordChange(
            'gsc.property_chosen',
            $by === null ? 'system' : 'user:'.$by->getKey(),
            ['site_url' => $previousSiteUrl],
            ['site_url' => $property->site_url],
            $property,
        );

        return $property;
    }

    /**
     * The property this location is mapped to, or null.
     */
    public function forLocation(Location $location): ?GscSiteProperty
    {
        Tenancy::idOrFail();

        return GscSiteProperty::query()
            ->where('location_id', $location->getKey())
            ->first();
    }

    /**
     * Unmap a location.
     *
     * Deletes rather than soft-deletes: this row is a pointer, not evidence. The
     * things that must survive — that a sync ran, what it found — are
     * `automation_runs` and `gsc_daily_snapshots`, and neither is touched here.
     * Decision 555's reasoning inverted: an import records a claim and must never
     * be deletable; this records a configuration choice and must be.
     *
     * ⚠️ **AUDITED, ADDED WAVE 38 LANE B** — {@see choose()}'s own reasoning.
     * A no-op (nothing mapped) writes nothing to the log, on `AuditService`'s
     * own convention: an audit row records that something happened, never that
     * it was asked for and did not.
     */
    public function clear(Location $location, ?User $by = null): void
    {
        Tenancy::idOrFail();

        $property = GscSiteProperty::query()
            ->where('location_id', $location->getKey())
            ->first();

        if ($property === null) {
            return;
        }

        $siteUrl = $property->site_url;

        $property->delete();

        $this->audit->record(
            'gsc.property_cleared',
            $by === null ? 'system' : 'user:'.$by->getKey(),
            null,
            ['location_id' => $location->getKey(), 'site_url' => $siteUrl],
        );
    }

    /**
     * Whether this tenant has a Search Console grant we could use right now.
     *
     * Three answers rather than two, because "no row" and "a row that stopped
     * working" send the owner to two different places — connect for the first,
     * reconnect for the second — and `VisibilityReading` has a distinct state for
     * each (decision 1084).
     */
    public function connectionState(Location $location): ConnectionState
    {
        Tenancy::idOrFail();

        $connection = $this->tokens->connection(self::businessFor($location), OauthProvider::Gsc);

        return match (true) {
            ! $connection instanceof OauthConnection => ConnectionState::Absent,
            $connection->status === ConnectionStatus::Active => ConnectionState::Usable,
            default => ConnectionState::Unusable,
        };
    }

    /**
     * The location's own business, as a value rather than a maybe.
     *
     * `locations.business_id` is NOT NULL and foreign-keyed, so this relation
     * cannot legitimately be null — but the relation's type says it can, and
     * `?->` at each call site would turn an impossible state into a silently
     * skipped vendor call. Failing loudly is the right direction: a location with
     * no business is a broken tenant boundary, not a missing feature.
     *
     * The relation is read rather than re-queried, so a caller that already
     * loaded it pays nothing — `Location::businessName()`'s reasoning.
     */
    private static function businessFor(Location $location): Business
    {
        $business = $location->business;

        if (! $business instanceof Business) {
            throw new RuntimeException('Location '.$location->getKey().' has no business.');
        }

        return $business;
    }
}
