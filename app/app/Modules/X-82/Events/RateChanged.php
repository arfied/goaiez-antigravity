<?php

declare(strict_types=1);

namespace App\Modules\X82\Events;

final class RateChanged
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $rateCode,
        public readonly int $newAmountCents,
        public readonly int $newVersion
    ) {}
}
