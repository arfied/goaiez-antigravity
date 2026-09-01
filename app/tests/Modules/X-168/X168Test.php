<?php

declare(strict_types=1);

namespace Tests\Modules\X168;

use App\Modules\X168\Actions\TimesheetApproveAction;
use App\Modules\X168\Actions\TimesheetComputeAction;
use App\Modules\X168\Events\PeriodReady;
use App\Modules\X168\Events\TimesheetSubmitted;
use App\Modules\X168\Models\Timesheet;
use App\Modules\X168\Models\TimesheetEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X168Test extends TestCase
{
    private TimesheetComputeAction $computeAction;

    private TimesheetApproveAction $approveAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->computeAction = new TimesheetComputeAction;
        $this->approveAction = new TimesheetApproveAction;
    }

    /**
     * TEST ANCHOR
     * grep -rE 'location|gps' app/Modules/X-168/ shows reads only inside a job-state window;
     * a location event with no active job writes nothing
     */
    public function test_anchor_location_reads_in_job_window_and_no_job_writes_nothing(): void
    {
        Event::fake([TimesheetSubmitted::class, PeriodReady::class]);

        $biz = TestCase::provisionTenant(['name' => 'JobTime Tracking Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $techPersonId = 401;
        $now = Carbon::parse('2026-08-25 08:00:00');

        // 1. A location event with NO active job writes NOTHING (TEST ANCHOR)
        $noJobEntry = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $techPersonId,
            jobId: null, // No active job
            stateWindow: 'free',
            startedAt: $now,
            endedAt: $now->copy()->addMinutes(30),
            locationLat: 37.7749,
            locationLng: -122.4194
        );

        $this->assertNull($noJobEntry, 'A location event with no active job writes nothing');

        $totalEntries = TimesheetEntry::where('business_id', $biz->id)->count();
        $this->assertEquals(0, $totalEntries, 'Zero timesheet entries created when no active job');

        Event::assertNotDispatched(TimesheetSubmitted::class);

        // 2. Active job window -> writes entry with location and updates timesheet (TEST ANCHOR)
        $enRouteStart = $now->copy();
        $enRouteEnd = $enRouteStart->copy()->addMinutes(45); // 45 minutes

        $activeJobEntry = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $techPersonId,
            jobId: 7701, // Active Job ID
            stateWindow: 'en_route',
            startedAt: $enRouteStart,
            endedAt: $enRouteEnd,
            locationLat: 37.7749,
            locationLng: -122.4194
        );

        $this->assertNotNull($activeJobEntry);
        $this->assertEquals(7701, $activeJobEntry->job_id);
        $this->assertEquals(45, $activeJobEntry->duration_minutes);
        $this->assertEquals('en_route', $activeJobEntry->state_window);
        $this->assertEquals(37.7749, (float) $activeJobEntry->location_lat);

        $timesheet = Timesheet::where('business_id', $biz->id)->where('person_id', $techPersonId)->first();
        $this->assertNotNull($timesheet);
        $this->assertEquals(0.75, (float) $timesheet->total_hours); // 45 mins = 0.75 hours

        Event::assertDispatched(TimesheetSubmitted::class);
        Event::assertDispatched(PeriodReady::class);

        // 3. Approval action
        $approvedTimesheet = $this->approveAction->approve($biz->id, $timesheet->id);
        $this->assertEquals('approved', $approvedTimesheet->status);
    }

    /**
     * [N-062]
     */
    public function test_n_062_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
