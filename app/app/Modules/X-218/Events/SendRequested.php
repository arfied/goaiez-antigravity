<?php

declare(strict_types=1);

namespace App\Modules\X218\Events;

final class SendRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $recipientHandle,
        public readonly string $channel = 'influencer_dm'
    ) {}
}
