<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\ActuationTier;
use App\Enums\SpeedFix;
use App\Enums\SpeedFixStatus;
use Carbon\CarbonImmutable;

/**
 * One `speed_change_sets` row in the shape a caller outside {@see SpeedFixes}
 * may hold.
 *
 * The model stays behind the chokepoint for `SiteChange`'s reason: the pair
 * *(applied, unjudged)* is a **state** that §4.3's *"one at a time per site"*
 * depends on, and a caller holding the row is a caller that can write half of
 * it.
 */
final readonly class SpeedFixRecord
{
    /**
     * @param  array<string, mixed>|null  $baseline
     * @param  array<string, mixed>|null  $result
     */
    public function __construct(
        public int $id,
        public int $locationId,
        public SpeedFix $fix,
        public ActuationTier $tier,
        public int $changeSetId,
        public SpeedFixStatus $status,
        public CarbonImmutable $decidedAt,
        public ?CarbonImmutable $appliedAt,
        public ?array $baseline = null,
        public ?array $result = null,
    ) {}
}
