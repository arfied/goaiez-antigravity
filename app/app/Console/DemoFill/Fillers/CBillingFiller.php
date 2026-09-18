<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\CBilling\Models\CreditLedgerEntry;
use App\Modules\CBilling\Models\Meter;

class CBillingFiller implements DemoFiller
{
    public function module(): string
    {
        return 'C-Billing';
    }

    public function fill(Business $business): int
    {
        if (CreditLedgerEntry::where('business_id', $business->id)
            ->where('description', 'like', self::MARKER.'%')
            ->exists()) {
            return 0;
        }

        Meter::create([
            'business_id' => $business->id,
            'meter_type' => self::MARKER.'sms_segments',
            'units_used' => 1240,
            'cost_hundredths_cents' => 3720000,
        ]);

        Meter::create([
            'business_id' => $business->id,
            'meter_type' => self::MARKER.'voice_minutes',
            'units_used' => 86,
            'cost_hundredths_cents' => 4300000,
        ]);

        CreditLedgerEntry::create([
            'business_id' => $business->id,
            'entry_type' => 'topup',
            'reference_id' => self::MARKER.'ref-1',
            'description' => self::MARKER.'Plan credit',
            'amount_hundredths_cents' => 50000000,
            'balance_after_hundredths_cents' => 50000000,
            'created_at' => now(),
        ]);

        CreditLedgerEntry::create([
            'business_id' => $business->id,
            'entry_type' => 'topup',
            'reference_id' => self::MARKER.'ref-2',
            'description' => self::MARKER.'Top-up',
            'amount_hundredths_cents' => 5000000,
            'balance_after_hundredths_cents' => 55000000,
            'created_at' => now(),
        ]);

        return 4;
    }

    public function purge(Business $business): int
    {
        $meters = Meter::where('business_id', $business->id)
            ->where('meter_type', 'like', self::MARKER.'%')
            ->delete();

        $ledgers = CreditLedgerEntry::where('business_id', $business->id)
            ->where('description', 'like', self::MARKER.'%')
            ->delete();

        return $meters + $ledgers;
    }
}
