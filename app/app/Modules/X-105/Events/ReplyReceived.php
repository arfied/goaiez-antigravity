<?php

declare(strict_types=1);

namespace App\Modules\X105\Events;

final class ReplyReceived
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $ladderId,
        public readonly int $personId,
        public readonly string $replyChannel
    ) {}
}
