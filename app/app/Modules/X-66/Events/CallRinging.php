<?php

declare(strict_types=1);

namespace App\Modules\X66\Events;

final class CallRinging
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $sessionId,
        public readonly string $callSid,
        public readonly string $fromPhone,
        public readonly string $toPhone
    ) {}
}
