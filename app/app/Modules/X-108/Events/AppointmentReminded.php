<?php

declare(strict_types=1);

namespace App\Modules\X108\Events;

final class AppointmentReminded
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $appointmentId,
        public readonly string $reminderType // 24h, 1h, 10min
    ) {}
}
