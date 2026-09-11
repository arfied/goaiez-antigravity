<?php

declare(strict_types=1);

namespace App\Modules\X01\Listeners;

use App\Modules\X01\Domain\UnifiedInboxManager;
use App\Modules\X102\Events\ChatLeadCaptured;

final class ChatLeadCapturedListener
{
    public function __construct(
        private readonly UnifiedInboxManager $inboxManager
    ) {}

    public function handle(ChatLeadCaptured $event): void
    {
        if ($event->message === null || trim($event->message) === '') {
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
