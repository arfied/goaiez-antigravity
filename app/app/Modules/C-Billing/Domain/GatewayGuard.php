<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Domain;

final class GatewayGuard
{
    /**
     * [G1-01] X-198's MOCK gateway is asserted unreachable from a live tenant
     */
    public static function ensureNotMockOnLive(bool $isMock, bool $isLiveTenant): void
    {
        if ($isMock && $isLiveTenant) {
            throw new \RuntimeException('MOCK gateway unreachable from a live tenant');
        }
    }
}
