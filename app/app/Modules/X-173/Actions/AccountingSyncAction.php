<?php

declare(strict_types=1);

namespace App\Modules\X173\Actions;

use App\Modules\X173\Domain\AccountingSyncEngine;
use App\Modules\X173\Events\AccountingSynced;
use App\Modules\X173\Events\CategoryInferred;
use App\Modules\X173\Models\AccountingConnection;
use App\Modules\X173\Models\AccountingSyncConflict;
use App\Modules\X173\Models\SyncRun;
use Illuminate\Support\Facades\Event;

final class AccountingSyncAction
{
    private AccountingSyncEngine $engine;

    public function __construct()
    {
        $this->engine = new AccountingSyncEngine;
    }

    /**
     * Synchronizes transactions with external accounting system.
     * Category below confidence threshold posts to 'uncategorised' with review flag, NEVER to a guessed code (TEST ANCHOR & G1-03).
     * Conflict row is NEVER auto-closed (TEST ANCHOR).
     */
    public function syncTransactions(
        int $businessId,
        int $connectionId,
        array $transactions
    ): array {
        $connection = AccountingConnection::where('business_id', $businessId)->findOrFail($connectionId);

        $syncedCount = 0;
        $conflictsCount = 0;

        $run = SyncRun::create([
            'business_id' => $businessId,
            'connection_id' => $connection->id,
            'records_synced' => count($transactions),
            'conflicts_count' => 0,
            'status' => 'completed',
        ]);

        foreach ($transactions as $tx) {
            $ref = $tx['ref'] ?? ('tx_'.bin2hex(random_bytes(4)));
            $confidence = array_key_exists('confidence', $tx) ? (float) $tx['confidence'] : 0.0;
            $suggested = $tx['category'] ?? 'uncategorised';

            $inference = $this->engine->inferCategory($confidence, $suggested);

            if ($inference['is_low_confidence']) {
                $conflictsCount++;
                AccountingSyncConflict::create([
                    'business_id' => $businessId,
                    'sync_run_id' => $run->id,
                    'transaction_ref' => $ref,
                    'confidence_rate' => $inference['confidence_score'],
                    'assigned_category' => 'uncategorised', // TEST ANCHOR
                    'flagged_for_review' => true,
                    'status' => 'open', // Never auto-closed (TEST ANCHOR)
                ]);
            } else {
                $syncedCount++;
            }

            Event::dispatch(new CategoryInferred($businessId, $ref, $inference['assigned_category'], $inference['confidence_score']));
        }

        $run->update(['conflicts_count' => $conflictsCount, 'records_synced' => $syncedCount]);

        Event::dispatch(new AccountingSynced($businessId, $run->id, $syncedCount, $conflictsCount));

        return [
            'run_id' => $run->id,
            'records_synced' => $syncedCount,
            'conflicts_count' => $conflictsCount,
        ];
    }
}
