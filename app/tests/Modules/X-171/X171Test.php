<?php

declare(strict_types=1);

namespace Tests\Modules\X171;

use App\Modules\X171\Actions\JobStateAction;
use App\Modules\X171\Actions\ReplayOfflineSyncAction;
use App\Modules\X171\Events\JobCompleted;
use App\Modules\X171\Events\SyncConflict;
use App\Modules\X171\Events\TechOnSite;
use App\Modules\X171\Models\DeviceSyncConflict;
use App\Modules\X171\Models\DeviceSyncQueue;
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

        $biz = TestCase::provisionTenant(['name' => 'Mobile Field Tech Tenant', 'currency' => 'USD']);
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
        $path = base_path('app/Modules/X-171');
        $this->assertTrue(is_dir($path) && count(scandir($path)) > 2, 'X-171 module directory must exist and be non-empty');

        $grepCommand = sprintf('grep -rniE "(Http::|curl_|Guzzle|file_get_contents\(\'http)" %s', escapeshellarg($path));
        $output = shell_exec($grepCommand);

        $lines = array_filter(explode("\n", $output ?? ''), function ($line) {
            return ! empty($line) && ! str_contains($line, 'capabilities.php');
        });

        $this->assertEmpty($lines, 'No path under app/Modules/X-171/ performs an outbound network call.');
    }

    /** [G4-27] */
    public function test_g4_27_offline_replay_has_no_credit_cap_to_hard_stop_at(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Mobile Field Tech Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $result = $this->syncAction->replayMutation(
            $biz->id, 'mut-g427-001', 'device-A', 'note.added', ['body' => 'on site'], 3, 3
        );
        $this->assertSame('processed', $result['status']);
        $this->assertSame(1, $result['emitted_new_events']);
        $this->assertSame(1, DeviceSyncQueue::where('business_id', $biz->id)
            ->where('client_mutation_id', 'mut-g427-001')->where('status', 'processed')->count());

        $params = array_map(
            fn ($p) => $p->getName(),
            (new \ReflectionMethod(ReplayOfflineSyncAction::class, 'replayMutation'))->getParameters()
        );
        $this->assertContains('businessId', $params);
        $this->assertContains('clientMutationId', $params);
        $this->assertGreaterThanOrEqual(6, count($params));
        foreach ($params as $p) {
            $this->assertDoesNotMatchRegularExpression('/(credit|balance|quota|tier|plan|entitlement|topup|top_up|cap)/i', $p);
        }

        $dir = app_path('Modules/X-171');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $phpFiles = [];
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $basename = $file->getBasename();
                if ($basename === 'capabilities.php' || $basename === 'manifest.php') {
                    continue;
                }
                $phpFiles[] = $file->getPathname();
            }
        }

        $this->assertGreaterThanOrEqual(22, count($phpFiles));

        $controlMatches = 0;
        foreach ($phpFiles as $path) {
            $content = file_get_contents($path);
            if (preg_match('/DeviceSyncQueue|SyncEngine|DeviceSyncConflict/', $content)) {
                $controlMatches++;
            }
            $this->assertDoesNotMatchRegularExpression(
                '/\b(credit|credits|balance|quota|hard_stop|downgrade|suspend|throttle|paywall|top_up|topup)\b/i',
                $content
            );
        }
        $this->assertGreaterThanOrEqual(3, $controlMatches);
    }
}
