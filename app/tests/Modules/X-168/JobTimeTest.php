<?php

namespace Tests\Modules\X168;

use App\Modules\X168\Actions\TimesheetComputeAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JobTimeTest extends TestCase
{
    /**
     * @group N-063
     * @group N-064
     * @group N-067
     * @group N-070
     * @group N-072
     * @group N-073
     * @group N-074
     * @group N-076
     * @group N-079
     * @group N-082
     * @group N-085
     */
    public function test_timesheet_entry_captures_job_details_and_refuses_overtime_columns()
    {
        $biz = TestCase::provisionTenant(['name' => 'JobTime Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $personId = DB::table('people')->insertGetId([
            'business_id' => $biz->id,
            'first_name' => 'Tech',
            'last_name' => 'Guy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Test Job',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $startedAt = now()->subMinutes(60);
        $endedAt = now();

        app(TimesheetComputeAction::class)->recordJobWindow(
            businessId: $biz->id,
            personId: $personId,
            jobId: $jobId,
            stateWindow: 'on_site',
            startedAt: $startedAt,
            endedAt: $endedAt,
            locationLat: 40.7128,
            locationLng: -74.0060
        );

        $this->assertDatabaseHas('timesheet_entries', [
            'business_id' => $biz->id,
            'job_id' => $jobId,
            'state_window' => 'on_site',
            'duration_minutes' => 60,
            'location_lat' => 40.7128,
            'location_lng' => -74.0060,
        ]);

        $this->assertFalse(Schema::hasColumn('timesheets', 'overtime'));
        $this->assertFalse(Schema::hasColumn('timesheets', 'out_of_hours'));
        $this->assertFalse(Schema::hasColumn('timesheets', 'attendance'));

        $this->assertFalse(Schema::hasColumn('timesheet_entries', 'overtime'));
        $this->assertFalse(Schema::hasColumn('timesheet_entries', 'out_of_hours'));
        $this->assertFalse(Schema::hasColumn('timesheet_entries', 'attendance'));
    }
}
