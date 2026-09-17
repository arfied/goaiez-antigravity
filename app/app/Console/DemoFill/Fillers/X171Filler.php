<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X171\Models\DeviceSyncConflict;
use App\Modules\X171\Models\DeviceSyncQueue;

class X171Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-171';
    }

    public function fill(Business $business): int
    {
        if (DeviceSyncQueue::where('business_id', $business->id)->where('is_sample', true)->exists()) {
            return 0;
        }

        DeviceSyncQueue::create([
            'business_id' => $business->id,
            'client_mutation_id' => self::MARKER.'mut-1',
            'device_id' => self::MARKER.'van-07',
            'action_name' => 'job.complete',
            'payload' => ['job_id' => 100, 'note' => self::MARKER.'Replaced the fill valve'],
            'version' => 1,
            'status' => 'processed',
            'is_sample' => true,
        ]);

        $queue2 = DeviceSyncQueue::create([
            'business_id' => $business->id,
            'client_mutation_id' => self::MARKER.'mut-2',
            'device_id' => self::MARKER.'van-07',
            'action_name' => 'job.note',
            'payload' => ['job_id' => 101, 'note' => self::MARKER.'Left a voicemail'],
            'version' => 2,
            'status' => 'conflicted',
            'is_sample' => true,
        ]);

        DeviceSyncConflict::create([
            'business_id' => $business->id,
            'queue_id' => $queue2->id,
            'device_id' => self::MARKER.'van-07',
            'client_version' => 2,
            'server_version' => 3,
            'conflict_reason' => self::MARKER.'Server note newer than device note',
            'is_sample' => true,
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        $count = DeviceSyncConflict::where('business_id', $business->id)
            ->where('is_sample', true)
            ->delete();

        $count += DeviceSyncQueue::where('business_id', $business->id)
            ->where('is_sample', true)
            ->delete();

        return $count;
    }
}
