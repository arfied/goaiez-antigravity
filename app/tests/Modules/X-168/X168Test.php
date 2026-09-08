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

    public function test_defect_arm_close_job_window_updates_open_entry(): void
    {
        Event::fake([TimesheetSubmitted::class, PeriodReady::class]);
        $biz = TestCase::provisionTenant(['name' => 'Defect Arm Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $techPersonId = 402;
        $now = Carbon::parse('2026-08-25 08:00:00');

        $entry = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $techPersonId,
            jobId: 7702,
            stateWindow: 'on_site',
            startedAt: $now,
            endedAt: null,
            locationLat: null,
            locationLng: null
        );

        $this->assertNotNull($entry);
        $this->assertNull($entry->ended_at);
        $this->assertEquals(0, $entry->duration_minutes);

        $closedAt = $now->copy()->addMinutes(45);
        $closedEntry = $this->computeAction->closeJobWindow($biz->id, 7702, $closedAt);

        $this->assertNotNull($closedEntry);
        $this->assertEquals($closedAt->toDateTimeString(), $closedEntry->ended_at->toDateTimeString());
        $this->assertEquals(45, $closedEntry->duration_minutes);

        $timesheet = Timesheet::find($closedEntry->timesheet_id);
        $this->assertEquals(0.75, (float) $timesheet->total_hours);
    }

    public function test_regression_arm_close_job_window_without_open_entry_writes_nothing(): void
    {
        Event::fake([TimesheetSubmitted::class, PeriodReady::class]);
        $biz = TestCase::provisionTenant(['name' => 'Regression Arm Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $techPersonId = 403;
        $now = Carbon::parse('2026-08-25 08:00:00');

        $initialCount = TimesheetEntry::where('business_id', $biz->id)->count();

        // 1. Close when no open entry exists
        $result = $this->computeAction->closeJobWindow($biz->id, 7703, $now);
        $this->assertNull($result);
        $this->assertEquals($initialCount, TimesheetEntry::where('business_id', $biz->id)->count());

        // Now create a closed entry so we can test closing an already closed window
        $entry = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $techPersonId,
            jobId: 7703,
            stateWindow: 'on_site',
            startedAt: $now,
            endedAt: $now->copy()->addMinutes(60),
            locationLat: null,
            locationLng: null
        );

        $this->assertNotNull($entry);
        $this->assertNotNull($entry->ended_at);
        $this->assertEquals(60, $entry->duration_minutes);

        $timesheet = Timesheet::find($entry->timesheet_id);
        $this->assertEquals(1.0, (float) $timesheet->total_hours);

        $countAfterCreate = TimesheetEntry::where('business_id', $biz->id)->count();

        // 2. Close a second time on the already-closed window
        $secondCloseAt = $now->copy()->addMinutes(90);
        $result2 = $this->computeAction->closeJobWindow($biz->id, 7703, $secondCloseAt);

        $this->assertNull($result2);
        $this->assertEquals($countAfterCreate, TimesheetEntry::where('business_id', $biz->id)->count());

        $entry->refresh();
        $this->assertEquals(60, $entry->duration_minutes);

        $timesheet->refresh();
        $this->assertEquals(1.0, (float) $timesheet->total_hours);
    }

    public function test_close_job_window_pins_job_id_and_does_not_close_others(): void
    {
        Event::fake([TimesheetSubmitted::class, PeriodReady::class]);
        $biz = TestCase::provisionTenant(['name' => 'Pin Job ID Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $techPersonId = 404;
        $now = Carbon::parse('2026-08-25 08:00:00');

        $entry8801 = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $techPersonId,
            jobId: 8801,
            stateWindow: 'on_site',
            startedAt: $now,
            endedAt: null,
            locationLat: null,
            locationLng: null
        );

        $entry8802 = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $techPersonId,
            jobId: 8802,
            stateWindow: 'en_route',
            startedAt: $now,
            endedAt: null,
            locationLat: null,
            locationLng: null
        );

        $this->assertNull($entry8801->ended_at);
        $this->assertNull($entry8802->ended_at);

        $closedAt = $now->copy()->addMinutes(45);
        $closedEntry = $this->computeAction->closeJobWindow($biz->id, 8801, $closedAt);

        $this->assertNotNull($closedEntry);
        $this->assertEquals(8801, $closedEntry->job_id);
        $this->assertEquals($closedAt->toDateTimeString(), $closedEntry->ended_at->toDateTimeString());
        $this->assertNotEquals(0, $closedEntry->duration_minutes);

        $entry8802->refresh();
        $this->assertNull($entry8802->ended_at);
        $this->assertEquals(0, $entry8802->duration_minutes);
    }

    public function test_record_job_window_dedup_defect_arm_returns_existing_entry(): void
    {
        Event::fake([TimesheetSubmitted::class, PeriodReady::class]);
        $biz = TestCase::provisionTenant(['name' => 'Dedup Defect Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $techPersonId = 405;
        $now = Carbon::parse('2026-08-25 08:00:00');

        $entry1 = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $techPersonId,
            jobId: 9901,
            stateWindow: 'on_site',
            startedAt: $now,
            endedAt: null,
            locationLat: null,
            locationLng: null
        );

        $initialCount = TimesheetEntry::where('business_id', $biz->id)->count();
        $timesheet = Timesheet::find($entry1->timesheet_id);
        $initialTotalHours = $timesheet->total_hours;

        Event::assertDispatched(TimesheetSubmitted::class);
        Event::assertDispatched(PeriodReady::class);
        Event::fake([TimesheetSubmitted::class, PeriodReady::class]); // reset fake for second call

        $entry2 = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $techPersonId,
            jobId: 9901,
            stateWindow: 'on_site',
            startedAt: $now->copy()->addMinutes(5),
            endedAt: null,
            locationLat: null,
            locationLng: null
        );

        $this->assertEquals($entry1->id, $entry2->id);
        $this->assertEquals(1, TimesheetEntry::where('business_id', $biz->id)->count());

        $entry1->refresh();
        $this->assertNull($entry1->ended_at);
        $this->assertEquals(0, $entry1->duration_minutes);

        $timesheet->refresh();
        $this->assertEquals($initialTotalHours, $timesheet->total_hours);

        Event::assertNotDispatched(TimesheetSubmitted::class);
        Event::assertNotDispatched(PeriodReady::class);
    }

    public function test_record_job_window_two_jobs_regression_arm(): void
    {
        Event::fake([TimesheetSubmitted::class, PeriodReady::class]);
        $biz = TestCase::provisionTenant(['name' => 'Two Jobs Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $techPersonId = 406;
        $now = Carbon::parse('2026-08-25 08:00:00');

        $entry1 = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $techPersonId,
            jobId: 9902,
            stateWindow: 'on_site',
            startedAt: $now,
            endedAt: null,
            locationLat: null,
            locationLng: null
        );

        $entry2 = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $techPersonId,
            jobId: 9903,
            stateWindow: 'on_site',
            startedAt: $now->copy()->addMinutes(10),
            endedAt: null,
            locationLat: null,
            locationLng: null
        );

        $this->assertNotEquals($entry1->id, $entry2->id);
        $this->assertEquals(2, TimesheetEntry::where('business_id', $biz->id)->count());

        $entry1->refresh();
        $this->assertNull($entry1->ended_at);

        $entry2->refresh();
        $this->assertNull($entry2->ended_at);
    }

    public function test_record_job_window_revisit_regression_arm(): void
    {
        Event::fake([TimesheetSubmitted::class, PeriodReady::class]);
        $biz = TestCase::provisionTenant(['name' => 'Revisit Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $techPersonId = 407;
        $now = Carbon::parse('2026-08-25 08:00:00');

        $entry1 = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $techPersonId,
            jobId: 9904,
            stateWindow: 'on_site',
            startedAt: $now,
            endedAt: null,
            locationLat: null,
            locationLng: null
        );

        $closedAt = $now->copy()->addMinutes(30);
        $this->computeAction->closeJobWindow($biz->id, 9904, $closedAt);

        $entry2 = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $techPersonId,
            jobId: 9904,
            stateWindow: 'on_site',
            startedAt: $now->copy()->addMinutes(60),
            endedAt: null,
            locationLat: null,
            locationLng: null
        );

        $this->assertEquals(2, TimesheetEntry::where('business_id', $biz->id)->count());
        $this->assertNotEquals($entry1->id, $entry2->id);

        $entry1->refresh();
        $this->assertNotNull($entry1->ended_at);
        $this->assertEquals(30, $entry1->duration_minutes);
    }

    public function test_record_job_window_two_technicians_one_job(): void
    {
        Event::fake([TimesheetSubmitted::class, PeriodReady::class]);
        $biz = TestCase::provisionTenant(['name' => 'Two Techs Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $tech1 = 501;
        $tech2 = 502;
        $jobId = 7777;
        $now = Carbon::parse('2026-08-25 08:00:00');

        $entry1 = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $tech1,
            jobId: $jobId,
            stateWindow: 'on_site',
            startedAt: $now,
            endedAt: null,
            locationLat: null,
            locationLng: null
        );

        $entry2 = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $tech2,
            jobId: $jobId,
            stateWindow: 'on_site',
            startedAt: $now,
            endedAt: null,
            locationLat: null,
            locationLng: null
        );

        $this->assertEquals(2, TimesheetEntry::where('business_id', $biz->id)->count());
        $this->assertNotEquals($entry1->id, $entry2->id);
        $this->assertNotEquals($entry1->timesheet_id, $entry2->timesheet_id);

        $timesheet1 = Timesheet::find($entry1->timesheet_id);
        $timesheet2 = Timesheet::find($entry2->timesheet_id);

        $this->assertEquals($tech1, $timesheet1->person_id);
        $this->assertEquals($tech2, $timesheet2->person_id);
    }

    public function test_close_job_window_closes_both_technicians_windows(): void
    {
        Event::fake([TimesheetSubmitted::class, PeriodReady::class]);
        $biz = TestCase::provisionTenant(['name' => 'Two Techs Close Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $tech1 = 501;
        $tech2 = 502;
        $jobId = 7778;
        $now = Carbon::parse('2026-08-25 08:00:00');

        $entry1 = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $tech1,
            jobId: $jobId,
            stateWindow: 'on_site',
            startedAt: $now,
            endedAt: null,
            locationLat: null,
            locationLng: null
        );

        $entry2 = $this->computeAction->recordJobWindow(
            businessId: $biz->id,
            personId: $tech2,
            jobId: $jobId,
            stateWindow: 'on_site',
            startedAt: $now,
            endedAt: null,
            locationLat: null,
            locationLng: null
        );

        $closedAt = $now->copy()->addMinutes(45);
        $this->computeAction->closeJobWindow($biz->id, $jobId, $closedAt);

        $entry1->refresh();
        $entry2->refresh();

        $this->assertEquals($closedAt->toDateTimeString(), $entry1->ended_at->toDateTimeString());
        $this->assertEquals($closedAt->toDateTimeString(), $entry2->ended_at->toDateTimeString());

        $this->assertEquals(45, $entry1->duration_minutes);
        $this->assertEquals(45, $entry2->duration_minutes);

        $timesheet1 = Timesheet::find($entry1->timesheet_id);
        $timesheet2 = Timesheet::find($entry2->timesheet_id);

        $this->assertEquals(0.75, (float) $timesheet1->total_hours);
        $this->assertEquals(0.75, (float) $timesheet2->total_hours);
    }
}
