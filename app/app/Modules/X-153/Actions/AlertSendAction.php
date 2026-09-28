<?php

declare(strict_types=1);

namespace App\Modules\X153\Actions;

use App\Modules\X153\Events\AlertSent;
use App\Modules\X153\Models\Alert;
use App\Modules\X153\Models\ReplyCode;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class AlertSendAction
{
    public const CLAIM_EXPIRY_MINUTES = 30;

    public function __construct(
        private readonly DefaultsRegistry $registry
    ) {}

    private function claimExpiryMinutes(): int
    {
        return $this->registry->int('alerts.claim.expiry_minutes');
    }

    public function handle(
        int $businessId,
        string $title,
        string $body,
        string $alertClass = 'account',
        string $sendTime = '12:00'
    ): array {
        return DB::transaction(function () use ($businessId, $title, $body, $alertClass, $sendTime) {
            // Allocate a unique reply code that is NOT currently live in a thread (TEST ANCHOR)
            $liveCodes = ReplyCode::where('business_id', $businessId)->where('is_live', true)->pluck('code')->toArray();

            do {
                $code = (string) rand(100, 999);
            } while (in_array($code, $liveCodes, true));

            $alert = Alert::create([
                'business_id' => $businessId,
                'alert_class' => $alertClass,
                'title' => $title,
                'body' => $body,
                'status' => 'pending',
                'claim_expires_at' => now()->addMinutes($this->claimExpiryMinutes()), // 30-minute claim expiry (G8-26, G18-12)
            ]);

            $replyCode = ReplyCode::create([
                'business_id' => $businessId,
                'alert_id' => $alert->id,
                'code' => $code,
                'is_live' => true,
            ]);

            Event::dispatch(new AlertSent(
                businessId: $businessId,
                alertId: $alert->id,
                code: $code,
                alertClass: $alertClass
            ));

            return [
                'alert_id' => $alert->id,
                'code' => $code,
                'status' => 'sent',
                'alert_class' => $alertClass,
                'claim_expires_at' => $alert->claim_expires_at->toIso8601String(),
                'send_time' => $sendTime,
            ];
        });
    }
}
