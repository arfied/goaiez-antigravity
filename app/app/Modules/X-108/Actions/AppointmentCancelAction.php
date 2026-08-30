<?php

declare(strict_types=1);

namespace App\Modules\X108\Actions;

use App\Modules\X108\Domain\SchedulingEngine;

final class AppointmentCancelAction
{
    public function __construct(private readonly SchedulingEngine $engine) {}

    public function handle(int $businessId, int $appointmentId): array
    {
        return $this->engine->cancel($businessId, $appointmentId);
    }
}
