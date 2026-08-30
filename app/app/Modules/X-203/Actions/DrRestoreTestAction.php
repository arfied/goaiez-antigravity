<?php

declare(strict_types=1);

namespace App\Modules\X203\Actions;

use App\Modules\X203\Events\DrTestFailed;
use App\Modules\X203\Events\DrTestPassed;
use App\Modules\X203\Models\RestoreTest;
use Illuminate\Support\Facades\Event;

final class DrRestoreTestAction
{
    /**
     * Test automated restore into isolated environment with checksum & row count verification (TEST ANCHOR).
     */
    public function handle(
        int $businessId,
        string $backupId,
        string $expectedChecksum,
        string $actualChecksum,
        int $expectedRowCount,
        int $restoredRowCount
    ): array {
        // 1. Verify Checksum
        if ($expectedChecksum !== $actualChecksum) {
            $test = RestoreTest::create([
                'business_id' => $businessId,
                'backup_id' => $backupId,
                'expected_checksum' => $expectedChecksum,
                'actual_checksum' => $actualChecksum,
                'expected_row_count' => $expectedRowCount,
                'restored_row_count' => $restoredRowCount,
                'status' => 'failed_checksum_mismatch',
                'failure_reason' => "LOUD ERROR: Backup corruption detected! Expected checksum [{$expectedChecksum}] != Actual [{$actualChecksum}]",
            ]);

            Event::dispatch(new DrTestFailed($businessId, $test->id, $backupId, $test->failure_reason));

            return [
                'status' => 'failed',
                'test_id' => $test->id,
                'reason' => 'checksum_mismatch',
                'error' => $test->failure_reason,
            ];
        }

        // 2. Verify Row count
        if ($expectedRowCount !== $restoredRowCount) {
            $test = RestoreTest::create([
                'business_id' => $businessId,
                'backup_id' => $backupId,
                'expected_checksum' => $expectedChecksum,
                'actual_checksum' => $actualChecksum,
                'expected_row_count' => $expectedRowCount,
                'restored_row_count' => $restoredRowCount,
                'status' => 'failed_row_count_mismatch',
                'failure_reason' => "LOUD ERROR: Row count mismatch! Expected [{$expectedRowCount}] != Restored [{$restoredRowCount}]",
            ]);

            Event::dispatch(new DrTestFailed($businessId, $test->id, $backupId, $test->failure_reason));

            return [
                'status' => 'failed',
                'test_id' => $test->id,
                'reason' => 'row_count_mismatch',
                'error' => $test->failure_reason,
            ];
        }

        // 3. Passed
        $test = RestoreTest::create([
            'business_id' => $businessId,
            'backup_id' => $backupId,
            'expected_checksum' => $expectedChecksum,
            'actual_checksum' => $actualChecksum,
            'expected_row_count' => $expectedRowCount,
            'restored_row_count' => $restoredRowCount,
            'status' => 'passed',
        ]);

        Event::dispatch(new DrTestPassed($businessId, $test->id, $backupId, $actualChecksum));

        return [
            'status' => 'passed',
            'test_id' => $test->id,
            'checksum' => $actualChecksum,
            'row_count' => $restoredRowCount,
        ];
    }
}
