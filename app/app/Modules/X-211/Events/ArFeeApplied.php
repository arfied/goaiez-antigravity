<?php

declare(strict_types=1);

namespace App\Modules\X211\Events;

final class ArFeeApplied
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $invoiceId,
        public readonly int $feeCents
    ) {}
}
