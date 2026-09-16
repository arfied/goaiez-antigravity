<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X203\Models\RestoreTest;
use App\Modules\X203\Models\Runbook;
use App\Modules\X203\Models\RunbookRun;

class X203Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-203';
    }

    public function fill(Business $business): int
    {
        if (RestoreTest::where('business_id', $business->id)->where('backup_id', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        RestoreTest::create([
            'business_id' => $business->id,
            'backup_id' => self::MARKER.'bkp1',
            'expected_checksum' => 'a',
            'actual_checksum' => 'a',
            'expected_row_count' => 1,
            'restored_row_count' => 1,
            'status' => 'passed',
            'failure_reason' => null,
        ]);

        RestoreTest::create([
            'business_id' => $business->id,
            'backup_id' => self::MARKER.'bkp2',
            'expected_checksum' => 'a',
            'actual_checksum' => 'b',
            'expected_row_count' => 1,
            'restored_row_count' => 1,
            'status' => 'failed_checksum_mismatch',
            'failure_reason' => self::MARKER.'demo reason',
        ]);

        $runbook = Runbook::create([
            'business_id' => $business->id,
            'title' => self::MARKER.'Demo Runbook',
            'trigger_event' => 'demo_event',
            'steps' => ['step1'],
        ]);

        RunbookRun::create([
            'business_id' => $business->id,
            'runbook_id' => $runbook->id,
            'status' => 'completed',
            'executed_steps' => ['step1'],
        ]);

        return 4;
    }

    public function purge(Business $business): int
    {
        $testCount = RestoreTest::where('business_id', $business->id)
            ->where('backup_id', 'like', self::MARKER.'%')
            ->delete();

        $runbookIdQuery = Runbook::where('business_id', $business->id)
            ->where('title', 'like', self::MARKER.'%')
            ->select('id');

        $runCount = RunbookRun::where('business_id', $business->id)
            ->whereIn('runbook_id', $runbookIdQuery)
            ->delete();

        $rbCount = Runbook::where('business_id', $business->id)
            ->where('title', 'like', self::MARKER.'%')
            ->delete();

        return $testCount + $runCount + $rbCount;
    }
}
