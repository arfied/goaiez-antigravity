<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X201\Models\Dispute;
use App\Modules\X201\Models\DisputeEvidence;
use App\Modules\X201\Models\DisputeOutcome;

class X201Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-201';
    }

    public function fill(Business $business): int
    {
        if (Dispute::where('business_id', $business->id)->where('reason', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $d1 = Dispute::create([
            'business_id' => $business->id,
            'invoice_id' => 9001,
            'chargeback_amount_cents' => 18500,
            'reason' => self::MARKER.'Product not received',
            'status' => 'opened',
            'deadline_at' => now()->addDays(10),
        ]);

        $d2 = Dispute::create([
            'business_id' => $business->id,
            'invoice_id' => 9002,
            'chargeback_amount_cents' => 24000,
            'reason' => self::MARKER.'Duplicate charge',
            'status' => 'compiled',
        ]);

        DisputeEvidence::create([
            'business_id' => $business->id,
            'dispute_id' => $d2->id,
            'evidence_type' => 'invoice',
            'file_url_or_content' => self::MARKER.'Invoice #9002 — 240.00 disputed as duplicate charge',
        ]);

        DisputeEvidence::create([
            'business_id' => $business->id,
            'dispute_id' => $d2->id,
            'evidence_type' => 'call_log',
            'file_url_or_content' => self::MARKER.'Call on 14 Sep, 6 min, customer confirmed the visit',
        ]);

        $d3 = Dispute::create([
            'business_id' => $business->id,
            'invoice_id' => 9003,
            'chargeback_amount_cents' => 9900,
            'reason' => self::MARKER.'Fraudulent',
            'status' => 'won',
        ]);

        DisputeOutcome::create([
            'business_id' => $business->id,
            'dispute_id' => $d3->id,
            'outcome' => 'won',
            'lost_reason' => null,
            'commission_clawback_triggered' => false,
        ]);

        return 6;
    }

    public function purge(Business $business): int
    {
        $count = 0;
        $disputes = Dispute::where('business_id', $business->id)->where('reason', 'like', self::MARKER.'%')->get();
        $ids = $disputes->pluck('id')->all();

        if (empty($ids)) {
            return 0;
        }

        $count += DisputeOutcome::whereIn('dispute_id', $ids)->delete();
        $count += DisputeEvidence::whereIn('dispute_id', $ids)->delete();

        foreach ($disputes as $d) {
            $d->delete();
            $count++;
        }

        return $count;
    }
}
