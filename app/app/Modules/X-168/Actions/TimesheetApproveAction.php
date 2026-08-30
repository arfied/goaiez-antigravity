<?php

declare(strict_types=1);

namespace App\Modules\X168\Actions;

use App\Modules\X168\Models\Timesheet;

final class TimesheetApproveAction
{
    public function approve(int $businessId, int $timesheetId): Timesheet
    {
        $timesheet = Timesheet::where('business_id', $businessId)->findOrFail($timesheetId);
        $timesheet->update(['status' => 'approved']);

        return $timesheet;
    }
}
