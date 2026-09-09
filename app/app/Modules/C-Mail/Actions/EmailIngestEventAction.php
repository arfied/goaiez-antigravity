<?php

declare(strict_types=1);

namespace App\Modules\CMail\Actions;

use App\Enums\MailEventType;
use App\Modules\CMail\Events\EmailBounced;
use App\Modules\CMail\Events\EmailComplained;
use App\Modules\CMail\Events\EmailReplied;
use App\Modules\CMail\Models\MailDomain;
use App\Modules\CMail\Models\MailEvent;
use Illuminate\Support\Facades\Event;

/**
 * Ingest inbound mail events (R245).
 * The ingest is C-Mail's. The transport is external and deliberately absent.
 * (R245) C-Mail — recomputing complaint_rate on complained and bounced events because those are the types EmailHaltSeedAction counts to derive the rate and enforce the R17 seeds.
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

        $type = MailEventType::tryFrom($eventType);
        if ($type === null) {
            return;
        }

        if (in_array($type, [MailEventType::Complained, MailEventType::Bounced], true)) {
            $domain = (new EmailHaltSeedAction)->handle($businessId, $mailDomainId);
        }

        match ($type) {
            MailEventType::Bounced, MailEventType::SpamTrap => Event::dispatch(new EmailBounced($businessId, $mailDomainId, $recipientEmail, $eventType)),
            MailEventType::Complained => Event::dispatch(new EmailComplained($businessId, $mailDomainId, $domain->complaint_rate)),
            MailEventType::Replied => Event::dispatch(new EmailReplied($businessId, $mailDomainId, $recipientEmail, $subject, $payload['sender_name'] ?? '', $payload['body'] ?? '')),
            MailEventType::Sent, MailEventType::Queued, MailEventType::Unsubscribed => null,
        };
    }
}
