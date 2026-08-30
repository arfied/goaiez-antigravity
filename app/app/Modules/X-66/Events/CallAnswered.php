<?php

declare(strict_types=1);

namespace App\Modules\X66\Events;

final class CallAnswered
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $sessionId,
        public readonly string $fromPhone
    ) {}
}
