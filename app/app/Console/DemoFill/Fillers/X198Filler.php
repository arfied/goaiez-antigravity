<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X198\Models\MerchantConnection;
use App\Modules\X198\Models\Payout;
use App\Modules\X198\Models\ReconciliationRun;

class X198Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-198';
    }

    public function fill(Business $business): int
    {
        if (ReconciliationRun::where('business_id', $business->id)->where('discrepancy_reason', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $connection = MerchantConnection::create([
            'business_id' => $business->id,
            'gateway_name' => 'stripe',
            'merchant_account_id' => 'demo·acct_ridgeline',
            'is_connected' => true,
            'merchant_status' => 'connected',
            'merchant_relationship' => 'direct',
        ]);

        $payout = Payout::create([
            'business_id' => $business->id,
            'gateway_payout_id' => 'demo·po_0001',
            'amount_cents' => 125000,
            'payout_date' => now()->subDays(3)->toDateString(),
            'merchant_connection_id' => $connection->id,
        ]);

        ReconciliationRun::create([
            'business_id' => $business->id,
            'payout_id' => $payout->id,
            'expected_cents' => 125000,
            'actual_cents' => 124100,
            'discrepancy_cents' => -900,
            'discrepancy_reason' => 'demo·A refund landed after the payout closed',
            'status' => 'discrepancy_logged',
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        $count = 0;

        $runs = ReconciliationRun::where('business_id', $business->id)->where('discrepancy_reason', 'like', self::MARKER.'%')->get();
        foreach ($runs as $run) {
            $run->delete();
            $count++;
        }

        $payouts = Payout::where('business_id', $business->id)->where('gateway_payout_id', 'like', self::MARKER.'%')->get();
        foreach ($payouts as $payout) {
            $payout->delete();
            $count++;
        }

        $connections = MerchantConnection::where('business_id', $business->id)->where('merchant_account_id', 'like', self::MARKER.'%')->get();
        foreach ($connections as $connection) {
            $connection->delete();
            $count++;
        }

        return $count;
    }
}
