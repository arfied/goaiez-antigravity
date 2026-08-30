<?php

declare(strict_types=1);

namespace App\Modules\X01\Events;

final class TakeoverStarted
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $conversationId,
        public readonly int $operatorId,
        public readonly string $operatorName
    ) {}
}
