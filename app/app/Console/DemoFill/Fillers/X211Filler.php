<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Models\ArDunningAction;
use App\Modules\X211\Models\ArCollectionsPackage;
use App\Modules\X211\Models\PaymentPlan;
use App\Modules\X211\Models\ReceivableState;

class X211Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-211';
    }

    public function fill(Business $business): int
    {
        if (Invoice::where('business_id', $business->id)->where('invoice_number', 'like', self::MARKER.'AR-%')->exists()) {
            return 0;
        }

        $inv1 = Invoice::create([
            'business_id' => $business->id,
            'customer_id' => null,
            'invoice_number' => self::MARKER.'AR-001',
            'total_cents' => 48000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(21)->startOfDay(),
        ]);

        $inv2 = Invoice::create([
            'business_id' => $business->id,
            'customer_id' => null,
            'invoice_number' => self::MARKER.'AR-002',
            'total_cents' => 90000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->addDays(12)->startOfDay(),
        ]);

        ArDunningAction::create([
            'business_id' => $business->id,
            'invoice_id' => $inv1->id,
            'action' => 'reason_recorded',
            'reason' => 'Customer promised to pay',
        ]);

        ReceivableState::create([
            'business_id' => $business->id,
            'invoice_id' => $inv1->id,
            'age_days' => 21,
            'late_fee_cents' => 2400,
            'status' => 'overdue',
        ]);

        PaymentPlan::create([
            'business_id' => $business->id,
            'invoice_id' => $inv2->id,
            'installments_count' => 3,
            'installment_amount_cents' => 30000,
            'frequency' => 'monthly',
            'status' => 'offered',
        ]);

        $inv3 = Invoice::create([
            'business_id' => $business->id,
            'customer_id' => null,
            'invoice_number' => self::MARKER.'AR-003',
            'total_cents' => 65000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(45)->startOfDay(),
        ]);

        ArDunningAction::create([
            'business_id' => $business->id,
            'invoice_id' => $inv3->id,
            'action' => 'reason_recorded',
            'reason' => 'No reply yet',
        ]);

        ArCollectionsPackage::create([
            'business_id' => $business->id,
            'invoice_id' => $inv3->id,
            'packaged_by_user_id' => null,
            'partner' => null,
            'transmitted_at' => null,
            'contents' => [
                'invoice_number' => self::MARKER.'AR-003',
                'total_cents' => 65000,
                'paid_cents' => 0,
                'balance_cents' => 65000,
                'due_date' => now()->subDays(45)->startOfDay()->toDateString(),
                'lines' => [],
                'payments' => [],
                'actions' => [['action' => 'reason_recorded', 'reason' => 'No reply yet']],
                'messages_count' => 0,
            ],
        ]);

        return 8;
    }

    public function purge(Business $business): int
    {
        $count = 0;
        $invoices = Invoice::where('business_id', $business->id)->where('invoice_number', 'like', self::MARKER.'AR-%')->get();
        $ids = $invoices->pluck('id')->all();

        if (empty($ids)) {
            return 0;
        }

        $count += ArCollectionsPackage::whereIn('invoice_id', $ids)->delete();
        $count += PaymentPlan::whereIn('invoice_id', $ids)->delete();
        $count += ReceivableState::whereIn('invoice_id', $ids)->delete();
        $count += ArDunningAction::whereIn('invoice_id', $ids)->delete();

        foreach ($invoices as $inv) {
            $inv->delete();
            $count++;
        }

        return $count;
    }
}
