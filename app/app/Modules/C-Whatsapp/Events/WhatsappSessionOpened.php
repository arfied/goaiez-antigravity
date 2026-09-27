<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Events;

final class WhatsappSessionOpened
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $sessionId,
        public readonly string $recipientPhone,
        public readonly string $body = '',
        public readonly string $senderName = '',
        public readonly array $attachments = []
    ) {}
}
