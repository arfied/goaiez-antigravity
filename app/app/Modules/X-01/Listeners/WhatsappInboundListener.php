<?php

declare(strict_types=1);

namespace App\Modules\X01\Listeners;

use App\Modules\CWhatsapp\Events\WhatsappSessionOpened;
use App\Modules\X01\Domain\UnifiedInboxManager;

final class WhatsappInboundListener
{
    public function __construct(
        private readonly UnifiedInboxManager $inboxManager
    ) {}

    public function handle(WhatsappSessionOpened $event): void
    {
        if ($event->body === '') {
            return;
        }

        $this->inboxManager->ingestMessage(
            $event->businessId,
            'whatsapp',
            $event->recipientPhone,
            $event->senderName,
            $event->body,
            $event->attachments
        );
    }
}
