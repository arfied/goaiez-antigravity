<?php

declare(strict_types=1);

namespace App\Modules\X188\Domain;

use App\Modules\X188\Events\TenantCancelled;
use App\Modules\X188\Models\BrandRegistration;
use App\Modules\X188\Models\NumberAssignment;
use App\Modules\X188\Models\NumberPark;
use App\Modules\X188\Models\NumberPool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class NumberPoolManager
{
    /**
     * Assign a live number before first screen renders (TEST ANCHOR).
     */
        public function assignLiveNumber(int $businessId, string $areaCode = '512'): array // (R245) BoundaryStage: delegate to TenantNumbers
    {
        return DB::transaction(function () use ($businessId) {
            $numbers = app(\App\Services\Sms\TenantNumbers::class);
            $assigned = $numbers->claimForTenant($businessId);

            if ($assigned === null) {
                throw \App\Exceptions\NumberPoolExhausted::noFreeNumber(0);
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

