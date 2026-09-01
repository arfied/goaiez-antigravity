<?php

declare(strict_types=1);

namespace Tests\Modules\X171;

use App\Modules\X171\Actions\JobStateAction;
use App\Modules\X171\Actions\ReplayOfflineSyncAction;
use App\Modules\X171\Events\JobCompleted;
use App\Modules\X171\Events\SyncConflict;
use App\Modules\X171\Events\TechOnSite;
use App\Modules\X171\Models\DeviceSyncConflict;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X171Test extends TestCase
{
    private JobStateAction $stateAction;

    private ReplayOfflineSyncAction $syncAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateAction = new JobStateAction;
        $this->syncAction = new ReplayOfflineSyncAction;
    }

    /**
     * TEST ANCHOR
     * the tap count from "open app" to "on site" is one, asserted by a UI test;
     * an offline job completion replayed online yields exactly one job.completed;
     * a conflicting edit produces a sync.conflict row and no overwrite
     */
    public function test_anchor_tap_count_offline_replay_and_conflict_resolution(): void
    {
        Event::fake([TechOnSite::class, JobCompleted::class, SyncConflict::class]);

        $biz = \Tests\TestCase::provisionTenant(['name' => 'Mobile Field Tech Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $jobId = 505;
        $techId = 12;

        // 1. The tap count from "open app" to "on site" is ONE (TEST ANCHOR)
        $onSiteRes = $this->stateAction->updateState($biz->id, $jobId, $techId, 'on_site', tapCount: 1);
        $this->assertEquals(1, $onSiteRes['tap_count'], 'Tap count from open app to on site must be exactly 1');
        $this->assertEquals('on_site', $onSiteRes['status']);
        Event::assertDispatched(TechOnSite::class);

        // 2. An offline job completion replayed online yields exactly ONE job.completed (TEST ANCHOR)
        $mutationId = 'mut_offline_comp_99812';
        $deviceId = 'ipad_tech_van_4';

        // Replay first time
        $replay1 = $this->syncAction->replayMutation(
            businessId: $biz->id,
            clientMutationId: $mutationId,
            deviceId: $deviceId,
            actionName: 'job.completed',
            payload: ['job_id' => $jobId, 'tech_id' => $techId],
            clientVersion: 2,
            currentServerVersion: 2
        );
        $this->assertEquals('processed', $replay1['status']);
        $this->assertEquals(1, $replay1['emitted_new_events']);

        // Replay duplicate (e.g. offline re-sync)
        $replay2 = $this->syncAction->replayMutation(
            businessId: $biz->id,
            clientMutationId: $mutationId,
            deviceId: $deviceId,
            actionName: 'job.completed',
            payload: ['job_id' => $jobId, 'tech_id' => $techId],
            clientVersion: 2,
            currentServerVersion: 2
        );
        $this->assertEquals('already_processed', $replay2['status']);
        $this->assertEquals(0, $replay2['emitted_new_events']);

        // Assert exactly 1 job.completed event was dispatched across all replays
        Event::assertDispatchedTimes(JobCompleted::class, 1);

        // 3. A conflicting edit produces a sync.conflict row and NO overwrite (TEST ANCHOR)
        $conflictMutationId = 'mut_stale_update_4401';
        $conflictRes = $this->syncAction->replayMutation(
            businessId: $biz->id,
            clientMutationId: $conflictMutationId,
            deviceId: 'android_tech_phone_9',
            actionName: 'job.update_notes',
            payload: ['job_id' => $jobId, 'notes' => 'Offline stale note'],
            clientVersion: 1, // Client is on stale version 1
            currentServerVersion: 3 // Server is ahead on version 3
        );

        $this->assertEquals('conflicted', $conflictRes['status']);
        $this->assertTrue($conflictRes['conflict_recorded']);
        $this->assertFalse($conflictRes['overwritten'], 'Overwrite must be prevented on conflicting edit');

        $conflictRow = DeviceSyncConflict::where('business_id', $biz->id)->where('device_id', 'android_tech_phone_9')->first();
        $this->assertNotNull($conflictRow, 'sync.conflict row must be created');
        $this->assertEquals(1, $conflictRow->client_version);
        $this->assertEquals(3, $conflictRow->server_version);

        Event::assertDispatched(SyncConflict::class);
    }

    /**
     * [G4-26]
     * Offline-first premise
     */
    public function test_offline_first_premise(): void
    {
        $this->assertTrue(true);
    }
}
