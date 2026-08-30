<?php

declare(strict_types=1);

namespace App\Modules\X108\Events;

final class AppointmentBooked
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $appointmentId,
        public readonly string $serviceName,
        public readonly string $startTime,
        public readonly bool $isMember
    ) {}
}
