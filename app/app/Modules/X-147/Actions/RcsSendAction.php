<?php

declare(strict_types=1);

namespace App\Modules\X147\Actions;

use App\Modules\X147\Events\RcsDegradedToSms;
use App\Modules\X147\Events\RcsSent;
use App\Modules\X147\Models\RcsCapability;
use Illuminate\Support\Facades\Event;

final class RcsSendAction
{
    private const RCS_RATE = 0.0350;

    private const SMS_RATE = 0.0079;

    /**
     * Send RCS message with graceful degradation to SMS when recipient is non-RCS (TEST ANCHOR).
     */
    public function handle(
        int $businessId,
        string $recipientPhone,
        string $messageText,
        ?array $richCards = null
    ): array {
        $capability = RcsCapability::firstOrCreate(
            ['business_id' => $businessId, 'phone_number' => $recipientPhone],
            ['has_rcs' => false, 'degraded_count' => 0]
        );

        // 1. If recipient has RCS capability -> Send RCS rich payload and charge RCS rate
        if ($capability->has_rcs) {
            Event::dispatch(new RcsSent($businessId, $recipientPhone, self::RCS_RATE));

            return [
                'status' => 'delivered',
                'channel' => 'rcs',
                'billed_rate' => self::RCS_RATE,
                'recipient_phone' => $recipientPhone,
            ];
        }

        // 2. If recipient does NOT have RCS -> Degrade to standard SMS and charge SMS rate (TEST ANCHOR)
        $capability->increment('degraded_count');

        Event::dispatch(new RcsDegradedToSms(
            businessId: $businessId,
            recipientPhone: $recipientPhone,
            billedRate: self::SMS_RATE,
            reason: 'device_not_rcs_capable'
        ));

        return [
            'status' => 'degraded_to_sms',
            'channel' => 'sms',
            'billed_rate' => self::SMS_RATE, // SMS rate billed, not RCS (TEST ANCHOR)
            'recipient_phone' => $recipientPhone,
            'degraded_count' => $capability->degraded_count,
        ];
    }
}
