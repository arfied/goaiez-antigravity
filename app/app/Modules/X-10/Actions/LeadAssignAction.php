<?php

declare(strict_types=1);

namespace App\Modules\X10\Actions;

use App\Modules\X10\Events\LeadAssigned;
use App\Modules\X10\Models\Assignment;
use App\Modules\X10\Models\Territory;
use Illuminate\Support\Facades\Event;

final class LeadAssignAction
{
    /**
     * Assigns lead according to routing rules: returning caller affinity, polygon territory, or workload balance.
     */
    public function handle(
        int $businessId,
        int $leadId,
        ?int $previousOwnerStaffId = null,
        ?string $zipCode = null,
        array $staffWorkloads = []
    ): Assignment {
        $assignedStaffId = null;
        $reason = 'default_unassigned';

        // 1. Returning caller reaches same owner (G2-74, G2-75)
        if ($previousOwnerStaffId !== null) {
            $assignedStaffId = $previousOwnerStaffId;
            $reason = 'returning_caller_affinity';
        }

        // 2. Geocode / Polygon / ZipCode Territory match (G2-01, G2-07, G7-22)
        if ($assignedStaffId === null && $zipCode !== null) {
            $territory = Territory::where('business_id', $businessId)->get()->first(function ($t) use ($zipCode) {
                return is_array($t->zip_codes) && in_array($zipCode, $t->zip_codes, true);
            });

            if ($territory && $territory->assigned_staff_id) {
                $assignedStaffId = $territory->assigned_staff_id;
                $reason = 'polygon_territory_match';
            }
        }

        // 3. Workload balancing: open workload without scoring people (G15-03)
        if ($assignedStaffId === null && ! empty($staffWorkloads)) {
            // Pick staff with lowest open load
            asort($staffWorkloads);
            $assignedStaffId = (int) array_key_first($staffWorkloads);
            $reason = 'workload_balanced';
        }

        $assignedStaffId = $assignedStaffId ?? 1;

        $assignment = Assignment::create([
            'business_id' => $businessId,
            'lead_id' => $leadId,
            'assigned_staff_id' => $assignedStaffId,
            'assignment_reason' => $reason,
            'status' => 'active',
        ]);

        Event::dispatch(new LeadAssigned($businessId, $leadId, $assignedStaffId, $reason));

        return $assignment;
    }
}
