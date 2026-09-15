<?php

declare(strict_types=1);

namespace App\Modules\CSms\Events;

final class MessageReceived
{
    public function __construct(
        public readonly int $businessId,
        public readonly ?int $personId,
        public readonly string $fromPhone,
        public readonly ?string $body,
        public readonly string $providerMessageId,
        public readonly string $receivedAt
    ) {}
}
