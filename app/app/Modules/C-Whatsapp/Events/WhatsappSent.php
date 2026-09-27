<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Events;

final class WhatsappSent
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $recipientPhone,
        public readonly string $mode,
        public readonly ?string $providerMessageRef = null
    ) {}
}
