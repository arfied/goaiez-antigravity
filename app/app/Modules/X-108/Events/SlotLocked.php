<?php

declare(strict_types=1);

namespace App\Modules\X108\Events;

final class SlotLocked
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $slotStart,
        public readonly string $slotEnd,
        public readonly string $sessionId
    ) {}
}
