<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Actions;

use App\Modules\CWhatsapp\Domain\WhatsappEngine;
use App\Modules\CWhatsapp\Models\WhatsappSession;

final class WhatsappConnectAction
{
    public function __construct(private readonly WhatsappEngine $engine) {}

    public function handle(int $businessId, string $recipientPhone): WhatsappSession
    {
        return $this->engine->recordInbound($businessId, $recipientPhone);
    }
}
