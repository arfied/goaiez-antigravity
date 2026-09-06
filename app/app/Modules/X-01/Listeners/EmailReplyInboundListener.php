<?php

declare(strict_types=1);

namespace App\Modules\X01\Listeners;

use App\Modules\CMail\Events\EmailReplied;
use App\Modules\X01\Domain\UnifiedInboxManager;

final class EmailReplyInboundListener
{
    public function __construct(
        private readonly UnifiedInboxManager $inboxManager
    ) {}

    public function handle(EmailReplied $event): void
    {
        if ($event->body === '') {
            return;
        }

        $this->inboxManager->ingestMessage(
            $event->businessId,
            'email',
            $event->fromEmail,
            $event->senderName,
            $event->body
        );
    }
}
