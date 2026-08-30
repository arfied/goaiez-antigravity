<?php

declare(strict_types=1);

namespace App\Modules\X82\Actions;

use App\Modules\X82\Events\AllowanceGranted;
use App\Modules\X82\Models\Allowance;
use Illuminate\Support\Facades\Event;

final class AllowanceLookupAction
{
    /**
     * Looks up or grants allowances based on rollover/expire policy (G17-26).
     */
    public function grant(
        int $businessId,
        string $allowanceCode,
        int $units,
        string $policy = 'rollover'
    ): Allowance {
        $allowance = Allowance::firstOrCreate(
            ['business_id' => $businessId, 'allowance_code' => $allowanceCode],
            ['units_granted' => 0, 'units_used' => 0, 'policy' => $policy]
        );

        $allowance->increment('units_granted', $units);

        Event::dispatch(new AllowanceGranted($businessId, $allowanceCode, $units));

        return $allowance;
    }
}
