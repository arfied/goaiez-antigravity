<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Actions;

use App\Modules\CWhatsapp\Domain\WhatsappEngine;

final class WhatsappSendAction
{
    public function __construct(private readonly WhatsappEngine $engine) {}

    public function handle(int $businessId, string $recipientPhone, string $messageText, ?string $templateName = null): array
    {
        return $this->engine->send($businessId, $recipientPhone, $messageText, $templateName);
    }
}
