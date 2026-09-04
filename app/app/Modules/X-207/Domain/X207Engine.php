<?php
declare(strict_types=1);

namespace App\Modules\X207\Domain;

final class X207Engine
{
    public function validatePushProvider(string $provider): array {
        if (!in_array($provider, ['apns', 'fcm', 'web'])) {
            return ['status' => 'refused', 'reason' => 'no carrier between us and the device'];
        }
        return ['status' => 'ok'];
    }

    public function validateCadence(int $pushCount, int $ceiling): array {
        if ($pushCount >= $ceiling) {
            return ['status' => 'refused', 'reason' => 'passes X-204 cadence ceiling'];
        }
        return ['status' => 'ok'];
    }

    public function validateQuietHours(bool $inQuietHours): array {
        if ($inQuietHours) {
            return ['status' => 'refused', 'reason' => 'quiet hours apply'];
        }
        return ['status' => 'ok'];
    }

    public function handleTokenExpiration(bool $isExpired): array {
        if ($isExpired) {
            return ['status' => 'retired_quietly', 'is_delivery_failure' => false];
        }
        return ['status' => 'active'];
    }

    public function getPriority(string $eventType): string {
        $critical = ['review_naming_employee', 'recover_escalation', 'money_event'];
        if (in_array($eventType, $critical)) {
            return 'minutes';
        }
        return 'standard';
    }

    public function checkConsent(bool $hasConsent): array {
        if (!$hasConsent) {
            return ['status' => 'refused', 'reason' => 'no consent'];
        }
        return ['status' => 'ok'];
    }

    public function sanitizePayload(array $payload): array {
        unset($payload['secret']);
        return $payload;
    }
}
