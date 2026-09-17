<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X173\Models\AccountingConnection;
use App\Modules\X173\Models\AccountingSyncConflict;
use App\Modules\X173\Models\AccountMapping;
use App\Modules\X173\Models\SyncRun;

class X173Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-173';
    }

    public function fill(Business $business): int
    {
        if (AccountingConnection::where('business_id', $business->id)->where('realm_id', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $conn = AccountingConnection::create([
            'business_id' => $business->id,
            'provider' => 'quickbooks',
            'realm_id' => self::MARKER.'realm-4625',
            'is_active' => true,
        ]);

        AccountMapping::create([
            'business_id' => $business->id,
            'connection_id' => $conn->id,
            'internal_category' => self::MARKER.'Fuel',
            'remote_gl_account_id' => self::MARKER.'6100',
            'remote_gl_account_name' => self::MARKER.'Vehicle fuel',
        ]);

        AccountMapping::create([
            'business_id' => $business->id,
            'connection_id' => $conn->id,
            'internal_category' => self::MARKER.'Parts',
            'remote_gl_account_id' => self::MARKER.'5200',
            'remote_gl_account_name' => self::MARKER.'Parts and materials',
        ]);

        SyncRun::create([
            'business_id' => $business->id,
            'connection_id' => $conn->id,
            'records_synced' => 248,
            'conflicts_count' => 0,
            'status' => 'completed',
        ]);

        $runB = SyncRun::create([
            'business_id' => $business->id,
            'connection_id' => $conn->id,
            'records_synced' => 298,
            'conflicts_count' => 2,
            'status' => 'completed',
        ]);

        AccountingSyncConflict::create([
            'business_id' => $business->id,
            'sync_run_id' => $runB->id,
            'transaction_ref' => self::MARKER.'TXN-4625',
            'confidence_rate' => 0.42,
            'assigned_category' => 'uncategorised',
            'flagged_for_review' => true,
            'status' => 'open',
        ]);

        AccountingSyncConflict::create([
            'business_id' => $business->id,
            'sync_run_id' => $runB->id,
            'transaction_ref' => self::MARKER.'TXN-4626',
            'confidence_rate' => 0.61,
            'assigned_category' => self::MARKER.'Vehicle fuel',
            'flagged_for_review' => false,
            'status' => 'resolved',
        ]);

        return 7;
    }

    public function purge(Business $business): int
    {
        $count = 0;

        $count += AccountingSyncConflict::where('business_id', $business->id)
            ->where('transaction_ref', 'like', self::MARKER.'%')
            ->delete();

        $connections = AccountingConnection::where('business_id', $business->id)
            ->where('realm_id', 'like', self::MARKER.'%')
            ->get();
        $connIds = $connections->pluck('id')->all();

        if (! empty($connIds)) {
            $count += SyncRun::whereIn('connection_id', $connIds)->delete();
        }

        $count += AccountMapping::where('business_id', $business->id)
            ->where('internal_category', 'like', self::MARKER.'%')
            ->delete();

        foreach ($connections as $conn) {
            $conn->delete();
            $count++;
        }

        return $count;
    }
}
