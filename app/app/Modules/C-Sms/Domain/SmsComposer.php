<?php

declare(strict_types=1);

namespace App\Modules\CSms\Domain;

use App\Modules\CSms\Events\SendRequested;
use App\Modules\CSms\Models\SmsComposition;
use App\Modules\CSms\Models\SmsModerationResult;
use App\Modules\X204\Domain\ConsentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class SmsComposer
{
    public function __construct(
        private readonly ConsentService $consentService
    ) {}

    /**
     * Calculate segments and encoding according to carrier rules (TEST ANCHOR).
     */
    public function calculateSegments(string $body): array
    {
        $hasNonGsm = (bool) preg_match('/[^\x20-\x7E\r\n\t€£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞ]/u', $body);
        $length = mb_strlen($body, 'UTF-8');

        if ($hasNonGsm) {
            $encoding = 'ucs2';
            $segments = ($length <= 70) ? 1 : (int) ceil($length / 67);
        } else {
            $encoding = 'gsm7';
            $segments = ($length <= 160) ? 1 : (int) ceil($length / 153);
        }

        return [
            'length' => $length,
            'encoding' => $encoding,
            'segments' => $segments,
            'warning' => ($segments > 1) ? "Message length ({$length} chars) will bill as {$segments} segments in {$encoding}" : null,
        ];
    }

    /**
     * Dispatch SMS respecting quiet hours and STOP suppression (TEST ANCHOR).
     */
    public function send(
        int $businessId,
        string $recipientPhone,
        string $body,
        string $messageClass = 'transactional',
        string $recipientLocalTime = '12:00'
    ): array {
        return DB::transaction(function () use ($businessId, $recipientPhone, $body, $messageClass, $recipientLocalTime) {
            $knownClasses = ['transactional', 'opted_in', 'attested', 'customer_initiated', 'marketing'];
            if (! in_array($messageClass, $knownClasses, true)) {
                return [
                    'status' => 'refused',
                    'reason' => 'UNKNOWN_MESSAGE_CLASS',
                    'message' => "Message class '{$messageClass}' is unknown; refused before send",
                ];
            }

            // 1. Consent / STOP / Suppression check
            $consentState = match ($messageClass) {
                'transactional' => 'transactional',
                'opted_in', 'attested', 'customer_initiated' => $messageClass,
                'marketing' => 'opted_in',
            };

            $decision = $this->consentService->decide($businessId, $recipientPhone, 'sms', $consentState);
            if (! $decision['granted']) {
                $reason = ($decision['reason'] === 'SUPPRESSED') ? 'STOP_SUPPRESSED' : $decision['reason'];

                return [
                    'status' => 'halted',
                    'reason' => $reason,
                    'message' => 'Send suppressed due to consent check: '.$decision['reason'],
                ];
            }

            // 2. Segment calculation
            $calc = $this->calculateSegments($body);

            // 3. Quiet hours check: 21:00 to 08:00 (TEST ANCHOR: marketing at 21:30 waits, transactional goes)
            $isQuietHour = ($recipientLocalTime >= '21:00' || $recipientLocalTime < '08:00');
            $status = 'sent';
            $scheduledAt = null;

            if ($isQuietHour && $messageClass === 'marketing') {
                $status = 'scheduled';
                $scheduledAt = now()->addHours(11); // Morning delivery
            }

            $composition = SmsComposition::create([
                'business_id' => $businessId,
                'recipient_phone' => $recipientPhone,
                'message_class' => $messageClass,
                'body' => $body,
                'segments_count' => $calc['segments'],
                'encoding' => $calc['encoding'],
                'status' => $status,
                'scheduled_at' => $scheduledAt,
            ]);

            SmsModerationResult::create([
                'business_id' => $businessId,
                'composition_id' => $composition->id,
                'passed' => true,
                'warnings' => $calc['warning'] ? [$calc['warning']] : [],
            ]);

            if ($status === 'sent') {
                Event::dispatch(new SendRequested(
                    businessId: $businessId,
                    compositionId: $composition->id,
                    recipientPhone: $recipientPhone,
                    messageClass: $messageClass,
                    body: $body,
                    segmentsCount: $calc['segments']
                ));
            }

            return [
                'composition_id' => $composition->id,
                'status' => $status,
                'message_class' => $messageClass,
                'segments_count' => $calc['segments'],
                'encoding' => $calc['encoding'],
                'scheduled_at' => $scheduledAt?->toIso8601String(),
            ];
        });
    }

    public function halt(int $businessId, int $compositionId): bool
    {
        $comp = SmsComposition::where('business_id', $businessId)->findOrFail($compositionId);

        return (bool) $comp->update(['status' => 'halted']);
    }
}
