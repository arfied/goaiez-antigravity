<?php

declare(strict_types=1);

namespace App\Modules\X188\Events;

final class TenantCancelled
{
    public function __construct(
        public readonly int $businessId,
        public readonly bool $isPayingTenant,
        public readonly int $usageCount,
        public readonly ?int $parkDays = null
    ) {}
}
