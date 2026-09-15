<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X168\Models\Timesheet;
use App\Modules\X168\Models\TimesheetEntry;

class X168Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-168';
    }

    public function fill(Business $business): int
    {
        if (Timesheet::where('business_id', $business->id)->where('status', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }
        $ts1 = Timesheet::create(['business_id' => $business->id, 'person_id' => $business->owner_user_id, 'period_start' => now()->startOfWeek(), 'period_end' => now()->endOfWeek(), 'status' => self::MARKER.'open']);
        $ts2 = Timesheet::create(['business_id' => $business->id, 'person_id' => $business->owner_user_id, 'period_start' => now()->subWeek()->startOfWeek(), 'period_end' => now()->subWeek()->endOfWeek(), 'status' => self::MARKER.'open']);

        TimesheetEntry::create(['business_id' => $business->id, 'timesheet_id' => $ts1->id, 'state_window' => 'on_site', 'started_at' => now()->subHours(4), 'ended_at' => now(), 'duration_minutes' => 240]);
        TimesheetEntry::create(['business_id' => $business->id, 'timesheet_id' => $ts1->id, 'state_window' => 'en_route', 'started_at' => now()->subHours(8), 'ended_at' => now()->subHours(4), 'duration_minutes' => 240]);

        TimesheetEntry::create(['business_id' => $business->id, 'timesheet_id' => $ts2->id, 'state_window' => 'on_site', 'started_at' => now()->subDays(7), 'ended_at' => now()->subDays(7)->addHours(4), 'duration_minutes' => 240]);
        TimesheetEntry::create(['business_id' => $business->id, 'timesheet_id' => $ts2->id, 'state_window' => 'en_route', 'started_at' => now()->subDays(7)->subHours(4), 'ended_at' => now()->subDays(7), 'duration_minutes' => 240]);

        return 6;
    }

    public function purge(Business $business): int
    {
        $tsIds = Timesheet::where('business_id', $business->id)->where('status', 'like', self::MARKER.'%')->pluck('id');
        $count = TimesheetEntry::whereIn('timesheet_id', $tsIds)->delete();
        $count += Timesheet::whereIn('id', $tsIds)->delete();

        return $count;
    }
}
