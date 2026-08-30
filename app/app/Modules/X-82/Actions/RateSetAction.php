<?php

declare(strict_types=1);

namespace App\Modules\X82\Actions;

use App\Modules\X82\Events\RateChanged;
use App\Modules\X82\Models\Rate;
use App\Modules\X82\Models\RateVersion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class RateSetAction
{
    public function setRate(
        int $businessId,
        string $rateCode,
        int $amountCents,
        string $currency = 'USD'
    ): Rate {
        $rate = Rate::where('business_id', $businessId)->where('rate_code', $rateCode)->first();

        if ($rate) {
            $newVersion = $rate->current_version + 1;
            $rate->update([
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'current_version' => $newVersion,
            ]);
        } else {
            $newVersion = 1;
            $rate = Rate::create([
                'business_id' => $businessId,
                'rate_code' => $rateCode,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'current_version' => $newVersion,
                'is_active' => true,
            ]);
        }

        RateVersion::create([
            'business_id' => $businessId,
            'rate_id' => $rate->id,
            'version_number' => $newVersion,
            'amount_cents' => $amountCents,
            'effective_from' => Carbon::now(),
        ]);

        Event::dispatch(new RateChanged($businessId, $rateCode, $amountCents, $newVersion));

        return $rate;
    }

    /**
     * Locks a tenant into a grandfathered rate version (G17-10).
     */
    public function lockGrandfathered(
        int $businessId,
        int $rateId,
        int $tenantId,
        int $amountCents,
        int $versionNumber
    ): RateVersion {
        return RateVersion::create([
            'business_id' => $businessId,
            'rate_id' => $rateId,
            'version_number' => $versionNumber,
            'amount_cents' => $amountCents,
            'tenant_locked_business_id' => $tenantId,
            'effective_from' => Carbon::now(),
        ]);
    }
}
