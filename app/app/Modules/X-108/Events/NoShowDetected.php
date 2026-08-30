<?php

declare(strict_types=1);

namespace App\Modules\X108\Events;

final class NoShowDetected
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $appointmentId
    ) {}
}
