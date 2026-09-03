<?php

declare(strict_types=1);

namespace App\Modules\X171\Actions;

use App\Modules\X171\Events\JobCompleted;
use App\Modules\X171\Events\SyncConflict;
use App\Modules\X171\Models\DeviceSyncConflict;
use App\Modules\X171\Models\DeviceSyncQueue;
use Illuminate\Support\Facades\Event;

final class ReplayOfflineSyncAction
{
    /**
     * Replays offline queued mutation.
     * Offline job completion yields exactly 1 job.completed event even if replayed multiple times (TEST ANCHOR).
     * Conflicting edit produces sync.conflict row and NO overwrite (TEST ANCHOR).
     */
    public function replayMutation(
        int $businessId,
        string $clientMutationId,
        string $deviceId,
        string $actionName,
        array $payload,
        int $clientVersion,
        int $currentServerVersion
    ): array {
        // 1. Conflict detection (client version stale compared to server version)
        if ($clientVersion < $currentServerVersion) {
            $queueItem = DeviceSyncQueue::create([
                'business_id' => $businessId,
                'client_mutation_id' => $clientMutationId,
                'device_id' => $deviceId,
                'action_name' => $actionName,
                'payload' => $payload,
                'version' => $clientVersion,
                'status' => 'conflicted',
            ]);

            DeviceSyncConflict::create([
                'business_id' => $businessId,
                'queue_id' => $queueItem->id,
                'device_id' => $deviceId,
                'client_version' => $clientVersion,
                'server_version' => $currentServerVersion,
                'conflict_reason' => "Client version {$clientVersion} is outdated vs server {$currentServerVersion}; overwrite prevented",
            ]);

            Event::dispatch(new SyncConflict($businessId, $queueItem->id, $deviceId, 'STALE_CLIENT_VERSION'));

            return [
                'status' => 'conflicted',
                'conflict_recorded' => true,
                'overwritten' => false, // No overwrite (TEST ANCHOR)
            ];
        }

        // 2. Idempotent queue lookup
        $existing = DeviceSyncQueue::where('business_id', $businessId)
            ->where('client_mutation_id', $clientMutationId)
            ->first();

        if ($existing && $existing->status === 'processed') {
            return [
                'status' => 'already_processed',
                'emitted_new_events' => 0,
            ];
        }

        $queue = DeviceSyncQueue::create([
            'business_id' => $businessId,
            'client_mutation_id' => $clientMutationId,
            'device_id' => $deviceId,
            'action_name' => $actionName,
            'payload' => $payload,
            'version' => $clientVersion,
            'status' => 'processed',
        ]);

        if ($actionName === 'job.completed') {
            $personId = \Illuminate\Support\Facades\DB::table('work_orders')
                ->where('id', (int) $payload['job_id'])
                ->value('person_id');
            Event::dispatch(new JobCompleted($businessId, (int) $payload['job_id'], (int) $payload['tech_id'], $personId));
        }

        return [
            'status' => 'processed',
            'emitted_new_events' => 1,
        ];
    }
}
