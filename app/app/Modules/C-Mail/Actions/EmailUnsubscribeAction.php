<?php

declare(strict_types=1);

namespace App\Modules\CMail\Actions;

use App\Modules\CMail\Models\MailEvent;

final class EmailUnsubscribeAction
{
    public function handle(int $businessId, int $mailDomainId, string $recipientEmail): array
    {
        MailEvent::create([
            'business_id' => $businessId,
            'mail_domain_id' => $mailDomainId,
            'event_type' => 'unsubscribed',
            'send_type' => 'marketing',
            'recipient_email' => $recipientEmail,
            'subject' => 'Unsubscribed',
        ]);

        return [
            'status' => 'unsubscribed',
            'recipient_email' => $recipientEmail,
        ];
    }
}
