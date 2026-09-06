<?php

declare(strict_types=1);

namespace App\Modules\X01\Events;

final class TakeoverReleased
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $conversationId
    ) {}
}
