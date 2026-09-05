<?php

declare(strict_types=1);

namespace App\Modules\CSms\Listeners;

use App\Modules\CSms\Actions\SmsSendAction;
use App\Modules\CSms\Events\SendRequested;
use App\Modules\X204\Actions\ConsentDecideAction;

final class SendRequestedListener
{
    public function __construct(
        private readonly ConsentDecideAction $decideAction,
        private readonly SmsSendAction $sendAction
    ) {}

    public function handle(SendRequested $event): void
    {
        $consentState = match ($event->messageClass) {
            'marketing' => 'opted_in',
            default => 'transactional',
        };

        $decision = $this->decideAction->handle(
            $event->businessId,
            $event->recipientPhone,
            'sms',
            $consentState
        );

        if (! $decision['granted']) {
            return;
        }

        $this->sendAction->handle(
            $event->businessId,
            $event->recipientPhone,
            $event->body,
            $event->messageClass,
            '12:00',
            $decision['permit_id'] ?? 0
        );
    }
}
