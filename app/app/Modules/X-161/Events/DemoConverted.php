<?php

declare(strict_types=1);

namespace App\Modules\X161\Events;

final class DemoConverted
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $demoTenantId,
        public readonly int $liveBusinessId
    ) {}
}
