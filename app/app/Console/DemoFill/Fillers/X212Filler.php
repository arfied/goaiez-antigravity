<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X212\Models\MigrationReject;
use App\Modules\X212\Models\MigrationRun;

class X212Filler implements DemoFiller
{
    public const MARKER = 'demo·';

    public function module(): string
    {
        return 'X-212';
    }

    public function fill(Business $business): int
    {
        if (MigrationRun::where('business_id', $business->id)->where('source_system', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        echo "creating\n";
        $run = MigrationRun::create([
            'business_id' => $business->id,
            'source_system' => self::MARKER.'jobber',
            'status' => 'dry_run_ready',
            'total_records' => 240,
            'imported_records' => 236,
            'rejected_records' => 4,
        ]);

        echo "creating\n";
        MigrationReject::create([
            'business_id' => $business->id,
            'migration_run_id' => $run->id,
            'record_index' => 17,
            'raw_data' => ['name' => 'Ridgeline HVAC', 'phone' => ''],
            'rejection_reason' => 'no phone number on the record',
        ]);

        echo "creating\n";
        MigrationReject::create([
            'business_id' => $business->id,
            'migration_run_id' => $run->id,
            'record_index' => 93,
            'raw_data' => ['name' => '', 'phone' => '+15550100002'],
            'rejection_reason' => 'no business name on the record',
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        $runIds = MigrationRun::where('business_id', $business->id)->where('source_system', 'like', self::MARKER.'%')->pluck('id');
        
        $count = 0;
        if ($runIds->isNotEmpty()) {
            $count += MigrationReject::whereIn('migration_run_id', $runIds)->delete();
            $count += MigrationRun::whereIn('id', $runIds)->delete();
        }

        return $count;
    }
}
