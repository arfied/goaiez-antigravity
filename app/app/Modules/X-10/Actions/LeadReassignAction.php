<?php

declare(strict_types=1);

namespace App\Modules\X10\Actions;

use App\Modules\X10\Events\LeadReassigned;
use App\Modules\X10\Models\Assignment;
use Illuminate\Support\Facades\Event;

final class LeadReassignAction
{
    /**
     * Reassigns lead on SLA timeout or tech departure without ranking people (G2-60, G17-25).
     */
    public function handle(int $businessId, int $assignmentId, int $newStaffId, string $reason = 'sla_timeout'): Assignment
    {
        $assignment = Assignment::where('business_id', $businessId)->findOrFail($assignmentId);
        $assignment->update([
            'assigned_staff_id' => $newStaffId,
            'assignment_reason' => $reason,
            'status' => 'reassigned',
        ]);

        Event::dispatch(new LeadReassigned($businessId, $assignment->lead_id, $newStaffId, $reason));

        return $assignment;
    }
}
