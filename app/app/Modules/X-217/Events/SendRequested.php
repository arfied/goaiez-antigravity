<?php

declare(strict_types=1);

namespace App\Modules\X217\Events;

final class SendRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $recipientEmail,
        public readonly string $channel = 'email',
        public readonly string $messageClass = 'recruitment'
    ) {}
}
