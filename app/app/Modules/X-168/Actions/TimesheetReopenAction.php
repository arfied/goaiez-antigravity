<?php

declare(strict_types=1);

namespace App\Modules\X168\Actions;

use App\Modules\X168\Models\Timesheet;

class TimesheetReopenAction
{
    public function reopen(int $businessId, int $timesheetId): Timesheet
    {
        $timesheet = Timesheet::where('business_id', $businessId)->findOrFail($timesheetId);
        $timesheet->update(['status' => 'open']);

        return $timesheet;
    }
}
