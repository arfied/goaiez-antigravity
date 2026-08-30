<?php

declare(strict_types=1);

namespace App\Modules\X108\Actions;

use App\Modules\X108\Domain\SchedulingEngine;

final class AvailabilityRequestAction
{
    public function __construct(private readonly SchedulingEngine $engine) {}

    public function handle(int $businessId, string $date, bool $isMember = false): array
    {
        return $this->engine->getAvailableSlots($businessId, $date, $isMember);
    }
}
