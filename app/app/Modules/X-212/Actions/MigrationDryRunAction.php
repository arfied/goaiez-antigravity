<?php

declare(strict_types=1);

namespace App\Modules\X212\Actions;

use App\Modules\X212\Events\MigrationDryRunReady;
use App\Modules\X212\Events\MigrationRecordRejected;
use App\Modules\X212\Events\MigrationStarted;
use App\Modules\X212\Models\MigrationReject;
use App\Modules\X212\Models\MigrationRun;
use Illuminate\Support\Facades\Event;

final class MigrationDryRunAction
{
    public function handle(int $businessId, string $sourceSystem, array $records): MigrationRun
    {
        $run = MigrationRun::create([
            'business_id' => $businessId,
            'source_system' => $sourceSystem,
            'status' => 'started',
            'total_records' => count($records),
            'imported_records' => 0,
            'rejected_records' => 0,
            'is_silent_mode' => true, // Silent import
        ]);

        Event::dispatch(new MigrationStarted($businessId, $run->id, $sourceSystem));

        $validCount = 0;
        $rejectCount = 0;

        foreach ($records as $index => $record) {
            // Validation: require phone or email
            if (empty($record['phone']) && empty($record['email'])) {
                MigrationReject::create([
                    'business_id' => $businessId,
                    'migration_run_id' => $run->id,
                    'record_index' => $index,
                    'raw_data' => $record,
                    'rejection_reason' => 'Missing contact identifier (phone or email required)',
                ]);

                Event::dispatch(new MigrationRecordRejected($businessId, $run->id, $index, 'missing_contact'));
                $rejectCount++;
            } else {
                $validCount++;
            }
        }

        $run->update([
            'status' => 'dry_run_ready',
            'imported_records' => $validCount,
            'rejected_records' => $rejectCount,
        ]);

        Event::dispatch(new MigrationDryRunReady($businessId, $run->id, $validCount, $rejectCount));

        return $run;
    }
}
