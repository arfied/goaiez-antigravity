<?php

declare(strict_types=1);

namespace App\Modules\CMail\Actions;

use App\Modules\CMail\Models\MailEvent;
use App\Modules\X204\Domain\ConsentService;

final class EmailUnsubscribeAction
{
    private ConsentService $consentService;

    public function __construct(ConsentService $consentService)
    {
        $this->consentService = $consentService;
    }

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

        $suppression = $this->consentService->suppress($businessId, $recipientEmail, 'email', 'unsubscribed_marketing');

        return [
            'status' => 'unsubscribed',
            'recipient_email' => $recipientEmail,
            'suppression_id' => $suppression->id,
        ];
    }
}
