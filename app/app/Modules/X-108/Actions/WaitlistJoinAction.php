<?php

declare(strict_types=1);

namespace App\Modules\X108\Actions;

use App\Modules\X108\Domain\SchedulingEngine;
use App\Modules\X108\Models\Waitlist;

final class WaitlistJoinAction
{
    public function __construct(private readonly SchedulingEngine $engine) {}

    public function handle(
        int $businessId,
        string $customerName,
        string $customerPhone,
        string $serviceName,
        string $preferredDate,
        bool $isMember = false,
        ?string $deployHash = null
    ): Waitlist {
        return $this->engine->joinWaitlist($businessId, $customerName, $customerPhone, $serviceName, $preferredDate, $isMember, $deployHash);
    }
}
