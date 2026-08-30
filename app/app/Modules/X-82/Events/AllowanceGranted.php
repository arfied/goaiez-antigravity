<?php

declare(strict_types=1);

namespace App\Modules\X82\Events;

final class AllowanceGranted
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $allowanceCode,
        public readonly int $unitsGranted
    ) {}
}
