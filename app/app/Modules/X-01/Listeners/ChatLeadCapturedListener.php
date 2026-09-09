<?php

declare(strict_types=1);

namespace App\Modules\X01\Listeners;

use App\Modules\X01\Domain\UnifiedInboxManager;

final class ChatLeadCapturedListener
{
    public function __construct(
        private readonly UnifiedInboxManager $inboxManager
    ) {}

    public function handle(object $event): void
    {
        if (! property_exists($event, 'message') || $event->message === null || $event->message === '') {
            return;
        }

        $this->inboxManager->ingestMessage(
            $event->businessId,
            'chat',
            $event->phone,
            $event->name,
            $event->message
        );
    }
}
