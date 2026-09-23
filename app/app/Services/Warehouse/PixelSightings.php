<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Models\L1Event;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

/**
 * Whether this tenant's pixel has been seen running on one of their own hosts.
 *
 * ⛔ **IT LIVES IN `Services/Warehouse/` BECAUSE OF WHERE IT READS, NOT BECAUSE
 * OF WHAT IT IS FOR** (5543). `WarehouseTest`'s *"nothing outside the warehouse
 * writes to a derived layer"* lint fails the build on any file in `app/` naming
 * `l1_events` or `L1Event::` outside this directory, and its message says the
 * rest: *"Reads belong behind a service and writes belong nowhere"*. The caller
 * is `App\Services\Actuation\ActuationTiers`, which derives T3 from the answer.
 *
 * ⚠️ **READ-ONLY, AND THAT IS A PROPERTY OF THE CLASS RATHER THAN A HABIT.**
 * There is no method here that writes, because a derived layer is truncated and
 * rebuilt from L0 and anything written by hand is destroyed by the next replay —
 * silently, because the replay reports success.
 *
 * ⚠️ **A SIGHTING IS DERIVED FROM REAL TRAFFIC AND THEREFORE MOVES.** L1 is a
 * function of L0, so this answer survives a replay; what it does not survive is
 * a site that stopped serving the pixel, which is the point. `41` Part 1's T3 is
 * a claim about a page we can reach *today*.
 *
 * ⛔ **AND NOTHING HAS EVER LANDED IN L1 IN PRODUCTION** (4861, 4966): the
 * collector exists and the delivery chain does not, so in production this
 * answers `false` for every tenant today. That is honest rather than broken —
 * a tenant with no pixel on their site is exactly a tenant this returns false
 * for — but a reader deciding whether T3 "works" should know that the negative
 * is currently overdetermined.
 */
final class PixelSightings
{
    /**
     * How recently the pixel must have been seen for the tier to claim it.
     *
     * ⚠️ **A CONSTANT RATHER THAN A REGISTRY KEY, DELIBERATELY** (5544). An
     * Ops-editable freshness window is a dial whose only effect is to make this
     * platform claim write access to sites it can no longer reach — and
     * `CLAUDE.md`'s first tie-breaker is less support surface. Thirty days is
     * chosen to survive a quiet month on a small local business's website
     * without surviving a site rebuild.
     */
    public const int FRESH_DAYS = 30;

    /**
     * Has anything of ours run on this host recently?
     *
     * ⚠️ **`www.` IS MATCHED BOTH WAYS, AND THE ALTERNATIVE WAS WORSE.** An
     * owner confirms `https://example.com` and their server redirects every
     * visitor to `www.example.com`, so the sighting arrives on a host that is
     * character-for-character not the one they typed. Refusing to match it would
     * report T4 for a site the pixel is demonstrably on. This is not the
     * normalisation 1083 forbids: that is about *deriving* which site is theirs,
     * and both spellings here are of a host the owner confirmed.
     *
     * ⚠️ **BOTS ARE NOT FILTERED OUT.** The question is whether our code executes
     * on that host, and a crawler executing it answers that as well as a person
     * does — while `is_bot` is a score, so filtering would turn a
     * misclassification into a downgraded tier.
     */
    public function seenOnHost(string $host): bool
    {
        Tenancy::idOrFail();

        $host = strtolower(trim($host));

        if ($host === '') {
            return false;
        }

        $bare = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        return L1Event::query()
            ->whereIn('page_host', array_unique([$host, $bare, 'www.'.$bare]))
            ->where('received_at', '>=', Carbon::now()->subDays(app(DefaultsRegistry::class)->int('warehouse.sightings_fresh_days')))
            ->exists();
    }
}
