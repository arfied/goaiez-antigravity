<?php

declare(strict_types=1);

namespace App\Modules\X108\Actions;

use App\Modules\X108\Domain\SchedulingEngine;
use App\Modules\X108\Models\Appointment;

final class AppointmentBookAction
{
    public function __construct(private readonly SchedulingEngine $engine) {}

    public function handle(
        int $businessId,
        string $serviceName,
        string $startTime,
        string $endTime,
        bool $isMember = false,
        ?int $customerId = null
    ): Appointment {
        return $this->engine->book($businessId, $serviceName, $startTime, $endTime, $isMember, $customerId);
    }
}
