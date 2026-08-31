<?php

declare(strict_types=1);

namespace App\Services\Widgets;

use App\Enums\WidgetInstallState;
use App\Models\WidgetInstall;
use Carbon\CarbonImmutable;

/**
 * What the widget screen is allowed to say about an install (3091).
 *
 * A value rather than a model or a bare enum, because the screen needs the
 * state **and** the hosts behind it: an owner with two websites is told which
 * one is working, and that is the whole difference between "your widget is
 * broken" and "your widget is fine on the shop and missing from the blog".
 *
 * ⚠️ **THE SIGHTINGS ARE HOSTS AND TIMES AND NOTHING ELSE.** There is no count
 * to expose because none is stored (3085); a view that wanted one would have to
 * go and add a column, and a lint stands in front of that.
 */
final readonly class WidgetInstallStatus
{
    /**
     * @param  list<WidgetInstall>  $sightings  Newest row first, at most one per
     *                                          allowed host and therefore bounded
     *                                          by `WidgetInstall::MAX_DOMAINS`.
     */
    public function __construct(
        public WidgetInstallState $state,
        public array $sightings = [],
    ) {}

    /**
     * The most recent moment any of this tenant's websites served the widget.
     *
     * Computed here rather than ordered in SQL: the set is bounded by the
     * allowlist, and `ConventionsTest` fails the build on `orderByDesc` over
     * anything but `id` — a rule worth honouring rather than exempting for a
     * query that can be answered in PHP over twenty rows.
     */
    public function lastSeenAt(): ?CarbonImmutable
    {
        $latest = null;

        foreach ($this->sightings as $sighting) {
            if ($latest === null || $sighting->last_seen_at->greaterThan($latest)) {
                $latest = $sighting->last_seen_at;
            }
        }

        return $latest;
    }
}
