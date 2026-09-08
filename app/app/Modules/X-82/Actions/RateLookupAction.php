<?php

declare(strict_types=1);

namespace App\Modules\X82\Actions;

use App\Modules\X82\Models\Rate;
use App\Modules\X82\Models\RateVersion;

final class RateLookupAction
{
    /**
     * Looks up rate for a tenant.
     * Respects grandfathered rate locks across global registry updates (TEST ANCHOR & G17-10).
     */
    public function lookup(int $businessId, string $rateCode, ?int $tenantId = null): array
    {
        // 1. Check for tenant-locked grandfathered version (G17-10)
        if ($tenantId !== null) {
            $grandfathered = RateVersion::where('business_id', $businessId)
                ->where('tenant_locked_business_id', $tenantId)
                ->whereHas('rate', function ($q) use ($rateCode) {
                    $q->where('rate_code', $rateCode);
                })
                ->latest('version_number')
                ->first();

            if ($grandfathered) {
                if ($grandfathered->rate->is_sample) {
                    return [
                        'refusal_code' => 'SAMPLE_STATE_REFUSED',
                    ];
                }

                return [
                    'rate_code' => $rateCode,
                    'amount_cents' => $grandfathered->amount_cents,
                    'amount_formatted' => '$'.number_format($grandfathered->amount_cents / 100, 2),
                    'version' => $grandfathered->version_number,
                    'is_grandfathered' => true,
                ];
            }
        }

        // 2. Global current active rate
        $rate = Rate::where('business_id', $businessId)->where('rate_code', $rateCode)->firstOrFail();

        if ($rate->is_sample) {
            return [
                'refusal_code' => 'SAMPLE_STATE_REFUSED',
            ];
        }

        if (! $rate->is_active) {
            return [
                'refusal_code' => 'INACTIVE_RATE_REFUSED',
            ];
        }

        return [
            'rate_code' => $rate->rate_code,
            'amount_cents' => $rate->amount_cents,
            'amount_formatted' => '$'.number_format($rate->amount_cents / 100, 2),
            'version' => $rate->current_version,
            'is_grandfathered' => false,
        ];
    }
}
