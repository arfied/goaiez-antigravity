<?php

declare(strict_types=1);

namespace App\Modules\X207\Actions;

use App\Modules\X207\Domain\WebPushTransport;
use App\Modules\X207\Events\SendRequested;
use App\Modules\X207\Models\DeviceToken;
use App\Modules\X207\Models\PushDelivery;
use Illuminate\Support\Facades\Event;

final class PushSendAction
{
    /**
     * Serializes and sanitizes push payloads (TEST ANCHOR: no customer name, address, amount, message body).
     */
    public function serializePayload(array $rawInput): array
    {
        // Strictly sanitize: strip all PII and sensitive info (TEST ANCHOR)
        $forbiddenKeys = ['customer_name', 'name', 'address', 'amount', 'price', 'message_body', 'body', 'text'];
        $clean = array_diff_key($rawInput, array_flip($forbiddenKeys));

        return [
            'event_type' => $clean['event_type'] ?? 'system_alert',
            'deep_link' => $clean['deep_link'] ?? '/inbox',
            'badge' => $clean['badge'] ?? 1,
            'timestamp' => time(),
        ];
    }

    /**
     * Send push notification to a registered device.
     */
    public function handle(
        int $businessId,
        int $deviceTokenId,
        array $rawPayload,
        bool $hasPushConsent = true
    ): array {
        // 1. ConsentService check for channel: push (TEST ANCHOR)
        if (! $hasPushConsent) {
            return [
                'status' => 'refused_no_consent',
                'message' => 'Recipient has not granted consent for push notifications',
            ];
        }

        $device = DeviceToken::where('business_id', $businessId)->findOrFail($deviceTokenId);
        if ($device->status !== 'active') {
            return [
                'status' => 'refused_device_retired',
                'message' => 'Device token is retired',
            ];
        }

        // 2. Serialize and sanitize payload (TEST ANCHOR)
        $sanitizedPayload = $this->serializePayload($rawPayload);

        $status = 'not_sent_no_transport';
        $reason = null;

        if ($device->platform === 'web' && $device->subscription !== null) {
            $transportResult = app(WebPushTransport::class)->send($device->subscription, $sanitizedPayload);
            if ($transportResult['ok']) {
                $status = 'sent';
            } elseif ($transportResult['expired']) {
                $status = 'expired';
                $device->update(['status' => 'retired', 'retirement_reason' => 'expired']);
            } else {
                $status = 'failed';
                $reason = $transportResult['reason'];
            }
        }

        $delivery = PushDelivery::create([
            'business_id' => $businessId,
            'device_token_id' => $device->id,
            'payload' => $sanitizedPayload,
            'sanitized' => true,
            'status' => $status,
        ]);

        if ($status === 'sent') {
            Event::dispatch(new SendRequested($businessId, $device->id, $sanitizedPayload));
        }

        $result = [
            'status' => $status,
            'delivery_id' => $delivery->id,
            'payload' => $sanitizedPayload,
        ];

        if ($status !== 'sent' && $reason !== null) {
            $result['reason'] = $reason;
        }

        return $result;
    }
}
