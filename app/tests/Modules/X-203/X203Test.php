<?php

declare(strict_types=1);

namespace Tests\Modules\X203;

use App\Modules\X203\Actions\DrPitrAction;
use App\Modules\X203\Actions\DrRestoreTestAction;
use App\Modules\X203\Actions\DrRunbookRunAction;
use App\Modules\X203\Events\DrRestoreExecuted;
use App\Modules\X203\Events\DrTestFailed;
use App\Modules\X203\Events\DrTestPassed;
use App\Modules\X203\Models\RestoreTest;
use App\Modules\X203\Models\Runbook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X203Test extends TestCase
{
    private DrRestoreTestAction $restoreTestAction;

    private DrPitrAction $pitrAction;

    private DrRunbookRunAction $runbookAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->restoreTestAction = new DrRestoreTestAction;
        $this->pitrAction = new DrPitrAction;
        $this->runbookAction = new DrRunbookRunAction;
    }

    /**
     * TEST ANCHOR
     * a scheduled restore into an isolated environment, verified by row count and checksum — and a deliberately corrupted backup FAILS it loudly.
     * [G13-07], [G21-03]
     */
    public function test_anchor_restore_verification_and_corrupted_backup_loud_failure(): void
    {
        Event::fake([DrTestPassed::class, DrTestFailed::class, DrRestoreExecuted::class]);

        $biz = TestCase::provisionTenant(['name' => 'DR Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $goodChecksum = 'sha256_e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
        $corruptChecksum = 'sha256_0000000000000000000000000000000000000000000000000000000000000000';

        // 1. Scheduled restore into isolated env with verified checksum and row count -> PASSES
        $passRes = $this->restoreTestAction->handle(
            businessId: $biz->id,
            backupId: 'bak_2026_08_30_01',
            expectedChecksum: $goodChecksum,
            actualChecksum: $goodChecksum,
            expectedRowCount: 15420,
            restoredRowCount: 15420
        );

        $this->assertEquals('passed', $passRes['status']);
        Event::assertDispatched(DrTestPassed::class);

        // 2. Deliberately corrupted backup with checksum mismatch FAILS loudly (TEST ANCHOR, G13-07)
        $failChecksumRes = $this->restoreTestAction->handle(
            businessId: $biz->id,
            backupId: 'bak_corrupted_02',
            expectedChecksum: $goodChecksum,
            actualChecksum: $corruptChecksum,
            expectedRowCount: 15420,
            restoredRowCount: 15420
        );

        $this->assertEquals('failed', $failChecksumRes['status']);
        $this->assertEquals('checksum_mismatch', $failChecksumRes['reason']);
        $this->assertStringContainsString('LOUD ERROR: Backup corruption detected!', $failChecksumRes['error']);

        $failedTest = RestoreTest::where('business_id', $biz->id)->find($failChecksumRes['test_id']);
        $this->assertEquals('failed_checksum_mismatch', $failedTest->status);

        Event::assertDispatched(DrTestFailed::class);

        // 3. Row count mismatch FAILS loudly
        $failRowCountRes = $this->restoreTestAction->handle(
            businessId: $biz->id,
            backupId: 'bak_missing_rows_03',
            expectedChecksum: $goodChecksum,
            actualChecksum: $goodChecksum,
            expectedRowCount: 15420,
            restoredRowCount: 14000
        );

        $this->assertEquals('failed', $failRowCountRes['status']);
        $this->assertEquals('row_count_mismatch', $failRowCountRes['reason']);
        $this->assertStringContainsString('LOUD ERROR: Row count mismatch!', $failRowCountRes['error']);

        // 4. PITR restore & Runbook automation (G21-03)
        $pitrRes = $this->pitrAction->handle($biz->id, 'bak_2026_08_30_01', '2026-08-30T12:00:00Z');
        $this->assertEquals('pitr_restored', $pitrRes['status']);
        Event::assertDispatched(DrRestoreExecuted::class);

        $runbook = Runbook::create([
            'business_id' => $biz->id,
            'title' => 'Failover Database Primary',
            'trigger_event' => 'primary_db_unreachable',
            'steps' => ['promote_replica', 'update_dns_records', 'notify_engineering_oncall'],
        ]);

        $runRes = $this->runbookAction->handle($biz->id, $runbook->id);
        $this->assertEquals('completed', $runRes->status);
    }

    }
}
