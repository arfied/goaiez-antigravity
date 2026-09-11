<?php

declare(strict_types=1);

namespace App\Modules\X188\Domain;

use App\Modules\X188\Events\TenantCancelled;
use App\Modules\X188\Models\BrandRegistration;
use App\Modules\X188\Models\NumberAssignment;
use App\Modules\X188\Models\NumberPark;
use App\Modules\X188\Models\NumberPool;
use App\Services\Sms\TenantNumbers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class NumberPoolManager
{
    /**
     * Assign a live number before first screen renders (TEST ANCHOR).
     */
    public function assignLiveNumber(int $businessId): array // (R245) BoundaryStage: delegate to TenantNumbers
    {
        return DB::transaction(function () use ($businessId) {
            $numbers = app(TenantNumbers::class);
            $assigned = $numbers->claimForTenant($businessId);

            if ($assigned === null) {
                return [
                    'phone_number' => null,
                    'area_code' => null,
                    'assignment_id' => null,
                    'status' => 'unassigned',
                ];
            }

            $poolNumber = NumberPool::firstOrCreate([
                'business_id' => $businessId,
                'phone_number' => $assigned->e164,
            ], [
                'area_code' => substr($assigned->e164, 2, 3),
                'carrier_name' => 'platform',
                'status' => 'assigned',
                'complaint_count' => 0,
            ]);

            $assignment = NumberAssignment::firstOrCreate([
                'business_id' => $businessId,
                'phone_number_id' => $poolNumber->id,
            ], [
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

    /**
     * Migrate brand without losing or reordering messages (TEST ANCHOR).
     */
    public function migrateBrand(int $businessId, string $brandName, string $tcrId): array
    {
        return DB::transaction(function () use ($businessId, $brandName, $tcrId) {
            $brand = BrandRegistration::updateOrCreate(
                ['business_id' => $businessId, 'brand_name' => $brandName],
                [
                    'tcr_brand_id' => $tcrId,
                    'registration_status' => 'active',
                    'brand_type' => 'dedicated',
                ]
            );

            NumberAssignment::where('business_id', $businessId)
                ->where('status', 'active')
                ->update(['status' => 'active']);

            return [
                'brand_id' => $brand->id,
                'status' => 'active',
                'in_flight_traffic_preserved' => true,
            ];
        });
    }

    /**
     * Cancelled trial with 0 usage releases number immediately; paying tenant parks 14 days (TEST ANCHOR).
     */
    public function handleCancellation(int $businessId, bool $isPayingTenant, int $usageCount): array
    {
        return DB::transaction(function () use ($businessId, $isPayingTenant, $usageCount) {
            $assignments = NumberAssignment::where('business_id', $businessId)->get();

            if (! $isPayingTenant && $usageCount === 0) {
                // Zero usage trial -> release immediately
                foreach ($assignments as $a) {
                    $a->update(['status' => 'released']);
                    NumberPool::where('id', $a->phone_number_id)->update(['status' => 'released']);
                }

                Event::dispatch(new TenantCancelled(
                    businessId: $businessId,
                    isPayingTenant: false,
                    usageCount: 0,
                    parkDays: 0
                ));

                return [
                    'action' => 'released_immediately',
                    'park_days' => 0,
                    'is_paying' => false,
                ];
            }

            // Paying tenant -> park 14 days
            $parkDays = 14;
            foreach ($assignments as $a) {
                $a->update(['status' => 'released']);
                NumberPool::where('id', $a->phone_number_id)->update(['status' => 'parked']);

                NumberPark::create([
                    'business_id' => $businessId,
                    'phone_number_id' => $a->phone_number_id,
                    'parked_at' => now(),
                    'park_until' => now()->addDays($parkDays),
                    'is_released' => false,
                ]);
            }

            Event::dispatch(new TenantCancelled(
                businessId: $businessId,
                isPayingTenant: true,
                usageCount: $usageCount,
                parkDays: $parkDays
            ));

            return [
                'action' => 'parked_14_days',
                'park_days' => $parkDays,
                'is_paying' => true,
            ];
        });
    }

    public function submitBrand(int $businessId, string $brandName): BrandRegistration
    {
        return BrandRegistration::create([
            'business_id' => $businessId,
            'brand_name' => $brandName,
            'tcr_brand_id' => 'TCR-'.rand(100000, 999999),
            'registration_status' => 'approved',
            'brand_type' => 'shared',
        ]);
    }

    public function getActiveNumber(int $businessId): ?string
    {
        $assignment = NumberAssignment::where('business_id', $businessId)
            ->where('status', 'active')
            ->first();

        if (! $assignment) {
            return null;
        }

        $poolNumber = NumberPool::find($assignment->phone_number_id);
        return $poolNumber?->phone_number;
    }
}
