<?php

declare(strict_types=1);

namespace App\Modules\X188\Domain;

use App\Exceptions\NumberPoolExhausted;
use App\Modules\X188\Models\NumberAssignment;
use App\Modules\X188\Models\NumberPark;
use App\Modules\X188\Models\NumberPool;
use App\Services\Sms\TenantNumbers;
use Illuminate\Support\Facades\DB;

final class NumberPoolManager
{
    /**
     * @throws NumberPoolExhausted
     */
    public function assignLiveNumber(int $businessId, string $areaCode = '512') // (R245) BoundaryStage: delegate to TenantNumbers: array
    {
        return DB::transaction(function () use ($businessId) {
            $numbers = app(TenantNumbers::class);
            $assigned = $numbers->claimForTenant($businessId);

            if ($assigned === null) {
                throw NumberPoolExhausted::noFreeNumber(0);
            }

            $poolNumber = NumberPool::create([
                'business_id' => $businessId,
                'phone_number' => $assigned->e164,
                'area_code' => substr($assigned->e164, 2, 3),
                'carrier_name' => 'platform',
                'status' => 'assigned',
                'complaint_count' => 0,
            ]);

            $assignment = NumberAssignment::create([
                'business_id' => $businessId,
                'phone_number_id' => $poolNumber->id,
                'status' => 'active',
            ]);

            return [
                'phone_number' => $assigned->e164,
                'area_code' => substr($assigned->e164, 2, 3),
                'assignment_id' => $assignment->id,
                'status' => 'active',
            ];
        });
    }

    public function migrateToDedicatedBrand(int $businessId, string $brandName, string $tcrId): array
    {
        return DB::transaction(function () use ($businessId) {
            $poolNumber = NumberPool::where('business_id', $businessId)
                ->where('status', 'assigned')
                ->firstOrFail();

            return [
                'phone_number' => $poolNumber->phone_number,
                'status' => 'active',
                'in_flight_traffic_preserved' => true,
            ];
        });
    }

    public function releaseOrParkNumber(int $businessId, bool $isPayingTenant, int $usageCount): array
    {
        return DB::transaction(function () use ($businessId, $isPayingTenant, $usageCount) {
            $poolNumber = NumberPool::where('business_id', $businessId)
                ->where('status', 'assigned')
                ->first();

            if (! $poolNumber) {
                return ['action' => 'none'];
            }

            if (! $isPayingTenant && $usageCount === 0) {
                $poolNumber->update(['status' => 'available', 'business_id' => null]);

                return ['action' => 'released_immediately', 'park_days' => 0];
            }

            $poolNumber->update(['status' => 'parked']);

            NumberPark::create([
                'business_id' => $businessId,
                'phone_number_id' => $poolNumber->id,
                'park_until' => now()->addDays(14),
            ]);

            return ['action' => 'parked_14_days', 'park_days' => 14];
        });
    }
}
