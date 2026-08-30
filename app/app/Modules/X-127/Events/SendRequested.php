<?php

declare(strict_types=1);

namespace App\Modules\X127\Events;

final class SendRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $channel,
        public readonly array $payload
    ) {}
}
