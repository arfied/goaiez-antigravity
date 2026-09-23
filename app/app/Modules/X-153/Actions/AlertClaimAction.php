<?php

declare(strict_types=1);

namespace App\Modules\X153\Actions;

use App\Modules\X153\Events\AlertClaimed;
use App\Modules\X153\Models\Alert;
use App\Modules\X153\Models\AlertClaim;
use App\Modules\X153\Models\ReplyCode;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class AlertClaimAction
{
    public function __construct(
        private readonly DefaultsRegistry $registry
    ) {}

    private function claimExpiryMinutes(): int
    {
        return $this->registry->int('alerts.claim.expiry_minutes');
    }

    /**
     * Claim an alert via reply code. Atomic race arbiter ensures exactly one claim (TEST ANCHOR).
     */
    public function handle(int $businessId, string $code, int $userId): array
    {
        return DB::transaction(function () use ($businessId, $code, $userId) {
            $replyCode = ReplyCode::where('business_id', $businessId)
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if ($replyCode === null) {
                return [
                    'status' => 'invalid_code',
                    'message' => "Reply code {$code} does not exist",
                ];
            }

            if (! $replyCode->is_live) {
                return [
                    'status' => 'already_claimed',
                    'message' => 'Another staff member already claimed this alert',
                ];
            }

            // Atomic status check on Alert
            $updated = Alert::where('id', $replyCode->alert_id)
                ->where('status', 'pending')
                ->update(['status' => 'claimed']);

            if ($updated === 0) {
                return [
                    'status' => 'already_claimed',
                    'message' => 'Another staff member already claimed this alert',
                ];
            }

            $replyCode->update(['is_live' => false]);

            $claim = AlertClaim::create([
                'business_id' => $businessId,
                'alert_id' => $replyCode->alert_id,
                'claimed_by_user_id' => $userId,
                'claimed_at' => now(),
                'status' => 'active',
                'expires_at' => now()->addMinutes($this->claimExpiryMinutes()),
            ]);

            Event::dispatch(new AlertClaimed(
                businessId: $businessId,
                alertId: $replyCode->alert_id,
                userId: $userId,
                claimId: $claim->id
            ));

            return [
                'status' => 'claimed',
                'alert_id' => $replyCode->alert_id,
                'claim_id' => $claim->id,
                'claimed_by' => $userId,
            ];
        });
    }
}
