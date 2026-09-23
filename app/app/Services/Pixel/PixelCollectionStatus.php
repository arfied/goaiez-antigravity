<?php

declare(strict_types=1);

namespace App\Services\Pixel;

use App\Enums\PixelCollectionState;
use Carbon\CarbonImmutable;

/**
 * What the install screen is allowed to say about a tenant's own collection
 * (7802).
 *
 * A value rather than a bare enum, because the screen needs the state **and**
 * the two lists behind it: the websites this account has named — which is the
 * precondition the whole slice is about — and the addresses whose traffic was
 * refused, which is the evidence that the line is installed and working and
 * being discarded.
 *
 * ✅ **AND SINCE 2026-08-22, THE ONE FACT THAT IS NOT AN INFERENCE FROM
 * SILENCE** (7980–7983): `$lastArrivedAt`, from
 * `App\Services\Warehouse\PixelArrivals`. ⚠️ **IT IS CARRIED
 * SEPARATELY FROM `$state` ON PURPOSE.** A tenant can be collecting from one
 * website *and* having another turned away, so the state names the thing to act
 * on and this names the thing that is working; folding the second into the first
 * would make the screen choose between telling somebody their install works and
 * telling them what to fix.
 *
 * ⚠️ **THE TWO LISTS COME FROM DIFFERENT SIDES OF THE SAME GATE AND MUST NOT BE
 * COMPARED HERE.** `$listedSites` is what `WidgetPlugins::businessAllowsOrigin()`
 * matches against, stored normalised; `$refusals` carry raw `Origin` headers a
 * caller wrote. Diffing them on a screen would be a fourth parser of a string
 * this codebase already parses in one place on purpose (3093, 2967).
 *
 * ⚠️ **`$otherAddresses` IS A COUNT OF ADDRESSES NOT SHOWN, NOT OF VISITS.**
 * See {@see PixelCollections::MAX_ADDRESSES_SHOWN} for why the list is capped
 * and why the number beside it is the honest way to say so.
 */
final readonly class PixelCollectionStatus
{
    /**
     * @param  list<string>  $listedSites  Every website this account names,
     *                                     across all of its review feeds.
     * @param  list<RefusedOrigin>  $refusals  Busiest first, capped at
     *                                         {@see PixelCollections::MAX_ADDRESSES_SHOWN}.
     * @param  int  $otherAddresses  Distinct addresses refused inside the window
     *                               and not in `$refusals`.
     * @param  CarbonImmutable|null  $lastArrivedAt  When this tenant's pixel last
     *                                               had something accepted and
     *                                               kept, or null when nothing
     *                                               this reader can see has. ⚠️
     *                                               **Null is not a failure** —
     *                                               `App\Services\Warehouse\PixelArrivals`
     *                                               carries the five reasons,
     *                                               and `$state` is what says
     *                                               which of them a tenant is
     *                                               being told about (9900).
     */
    public function __construct(
        public PixelCollectionState $state,
        public array $listedSites = [],
        public array $refusals = [],
        public int $otherAddresses = 0,
        public ?CarbonImmutable $lastArrivedAt = null,
    ) {}

    /**
     * How many days of refusals `$refusals` covers.
     *
     * ⚠️ **DERIVED FROM THE READER'S OWN WINDOW RATHER THAN WRITTEN INTO THE
     * COPY.** The view says *"in the last N days"* out loud, and a number typed
     * into a sentence beside a constant somewhere else is the pair that drifts —
     * the same reason `IngestRejects::RECENT_WINDOW_HOURS` stopped being a
     * `subDay()` in two methods. Moving {@see IngestRejects::TENANT_WINDOW_HOURS}
     * moves the sentence.
     */
    public function windowDays(): int
    {
        return intdiv(app(IngestRejects::class)->tenantWindowHours(), 24);
    }
}
