<?php

declare(strict_types=1);

namespace App\Modules\X102\Events;

final class ChatEscalated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $sessionId,
        public readonly string $reason
    ) {}
}
