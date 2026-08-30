<?php

declare(strict_types=1);

namespace App\Modules\X217\Domain;

use InvalidArgumentException;

final class RecruitmentGuard
{
    public const DEFAULT_MAX_CEILING_BPS = 2500; // 25% max

    /**
     * TEST ANCHOR: No offer exceeds the confirmed ceiling.
     */
    public function assertWithinCeiling(int $offeredBps, int $ceilingBps = self::DEFAULT_MAX_CEILING_BPS): void
    {
        if ($offeredBps > $ceilingBps) {
            throw new InvalidArgumentException("Recruitment offer rejected: offered rate ({$offeredBps} bps) exceeds ceiling ({$ceilingBps} bps) (TEST ANCHOR)");
        }
    }
}
