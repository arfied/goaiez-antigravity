<?php

declare(strict_types=1);

namespace App\Modules\CMail\Actions;

use App\Modules\CMail\Events\EmailSent;
use App\Modules\CMail\Models\MailDomain;
use App\Modules\CMail\Models\MailEvent;
use App\Modules\CMail\Models\WarmupCalendar;
use App\Modules\X204\Domain\ConsentService;
use Illuminate\Support\Facades\Event;

final class EmailSendAction
{
    private ConsentService $consentService;

    public function __construct(ConsentService $consentService)
    {
        $this->consentService = $consentService;
    }

    /**
     * Send email with warmup calendar limits & marketing complaint pause enforcement (TEST ANCHOR).
     */
    public function handle(
        int $businessId,
        int $mailDomainId,
        string $recipientEmail,
        string $subject,
        string $sendType = 'marketing',
        int $requestedCount = 1
    ): array {
        $domain = MailDomain::where('business_id', $businessId)->findOrFail($mailDomainId);

        // 1. Complaint rate check: a complaint rate crossing 0.10% pauses every marketing send, but pauses no conversational reply (TEST ANCHOR)
        if ($domain->is_marketing_paused && $sendType === 'marketing') {
            return [
                'status' => 'refused_paused',
                'refusal_code' => 'MARKETING_SENDS_PAUSED_COMPLAINT_RATE',
                'message' => 'Marketing sends paused due to complaint rate crossing 0.10% threshold',
            ];
        }

        if ($sendType === 'marketing') {
            $decision = $this->consentService->decide($businessId, $recipientEmail, 'email');
            if (! $decision['granted']) {
                return [
                    'status' => 'refused_suppressed',
                    'refusal_code' => 'MARKETING_SEND_SUPPRESSED',
                ];
            }
        }

        // 2. Warmup calendar check (applies to marketing sends: TEST ANCHOR)
        $warmup = WarmupCalendar::where('business_id', $businessId)->where('mail_domain_id', $mailDomainId)->first();

        $sentCount = $requestedCount;
        $queuedCount = 0;

        if ($sendType === 'marketing' && $warmup !== null && ! $warmup->is_warmed) {
            $available = max(0, $warmup->daily_allowance - $warmup->sent_today);
            if ($requestedCount > $available) {
                $sentCount = $available;
                $queuedCount = $requestedCount - $available;
            }
            $warmup->increment('sent_today', $sentCount);
        }

        if ($sentCount > 0) {
            MailEvent::create([
                'business_id' => $businessId,
                'mail_domain_id' => $domain->id,
                'event_type' => 'sent',
                'send_type' => $sendType,
                'recipient_email' => $recipientEmail,
                'subject' => $subject,
                'payload' => ['sent_count' => $sentCount],
            ]);

            Event::dispatch(new EmailSent($businessId, $domain->id, $recipientEmail, $sendType));
        }

        if ($queuedCount > 0) {
            MailEvent::create([
                'business_id' => $businessId,
                'mail_domain_id' => $domain->id,
                'event_type' => 'queued',
                'send_type' => $sendType,
                'recipient_email' => $recipientEmail,
                'subject' => $subject,
                'payload' => ['queued_count' => $queuedCount],
            ]);
        }

        return [
            'status' => 'processed',
            'sent_count' => $sentCount,
            'queued_count' => $queuedCount,
            'send_type' => $sendType,
        ];
    }
}
