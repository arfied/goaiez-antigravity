<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Events;

final class LedgerPeriodClosed
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $closingBalanceHundredthsCents,
        public readonly string $periodEnd
    ) {}
}
