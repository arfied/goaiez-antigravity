<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use Carbon\CarbonImmutable;

/**
 * One fix resting on one site, in the shape a caller outside
 * {@see SiteChangeQuarantines} may hold.
 *
 * The model stays behind the chokepoint for `SiteChange`'s own reason: the pair
 * `(location, change_type)` plus `released_at` is a *state*, and a caller
 * holding the row is a caller that can write half of it.
 */
final readonly class QuarantinedFix
{
    public function __construct(
        public int $id,
        public int $locationId,
        public string $changeType,
        public ?int $siteChangeId,
        public CarbonImmutable $quarantinedAt,
        public string $reason,
    ) {}
}
