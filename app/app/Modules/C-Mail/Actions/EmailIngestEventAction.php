<?php

declare(strict_types=1);

namespace App\Modules\CMail\Actions;

use App\Modules\CMail\Events\EmailBounced;
use App\Modules\CMail\Events\EmailComplained;
use App\Modules\CMail\Events\EmailReplied;
use App\Modules\CMail\Models\MailDomain;
use App\Modules\CMail\Models\MailEvent;
use Illuminate\Support\Facades\Event;

/**
 * Ingest inbound mail events (R245).
 * The ingest is C-Mail's. The transport is external and deliberately absent.
 */
final class EmailIngestEventAction
{
    public function handle(
        int $businessId,
        int $mailDomainId,
        string $eventType,
        string $recipientEmail,
        string $subject,
        array $payload = []
    ): void {
        $domain = MailDomain::where('business_id', $businessId)->findOrFail($mailDomainId);

        MailEvent::create([
            'business_id' => $businessId,
            'mail_domain_id' => $mailDomainId,
            'event_type' => $eventType,
            'send_type' => 'inbound',
            'recipient_email' => $recipientEmail,
            'subject' => $subject,
            'payload' => $payload,
        ]);

        match ($eventType) {
            'bounced', 'spam-trap' => Event::dispatch(new EmailBounced($businessId, $mailDomainId, $recipientEmail, $eventType)),
            'complained' => Event::dispatch(new EmailComplained($businessId, $mailDomainId, $domain->complaint_rate)),
            'replied' => Event::dispatch(new EmailReplied($businessId, $mailDomainId, $recipientEmail, $subject)),
            default => null,
        };
    }
}
