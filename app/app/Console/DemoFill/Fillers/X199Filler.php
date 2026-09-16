<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X121\Models\Person;
use App\Modules\X198\Models\Payment;
use App\Modules\X199\Models\CreditTerm;
use App\Modules\X199\Models\DeclineDeferral;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\InvoiceLine;
use App\Modules\X199\Models\OverflowCharge;

class X199Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-199';
    }

    public function fill(Business $business): int
    {
        if (Invoice::where('business_id', $business->id)->where('invoice_number', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $person = Person::firstOrCreate([
            'business_id' => $business->id,
            'first_name' => self::MARKER.'John',
        ], [
            'last_name' => 'Doe',
            'phone' => '+15125550000',
        ]);

        $count = 0;

        $invoicePaid = Invoice::create([
            'business_id' => $business->id,
            'customer_id' => $person->id,
            'invoice_number' => self::MARKER.'INV-001',
            'total_cents' => 10000,
            'paid_cents' => 10000,
            'status' => 'paid',
            'due_date' => now()->subDays(5)->startOfDay(),
            'paid_at' => now(),
        ]);
        $count++;

        InvoiceLine::create([
            'business_id' => $business->id,
            'invoice_id' => $invoicePaid->id,
            'description' => self::MARKER.'Service 1',
            'quantity' => 1,
            'unit_price_cents' => 10000,
            'subtotal_cents' => 10000,
        ]);
        $count++;

        $invoiceDue = Invoice::create([
            'business_id' => $business->id,
            'customer_id' => $person->id,
            'invoice_number' => self::MARKER.'INV-002',
            'total_cents' => 20000,
            'paid_cents' => 0,
            'status' => 'due',
            'due_date' => now()->subDays(10)->startOfDay(),
        ]);
        $count++;

        InvoiceLine::create([
            'business_id' => $business->id,
            'invoice_id' => $invoiceDue->id,
            'description' => self::MARKER.'Service 2',
            'quantity' => 2,
            'unit_price_cents' => 10000,
            'subtotal_cents' => 20000,
        ]);
        $count++;

        OverflowCharge::create([
            'business_id' => $business->id,
            'customer_id' => $person->id,
            'invoice_id' => $invoiceDue->id,
            'charge_type' => 'overflow_charged',
            'amount_cents' => 5000,
            'card_token' => self::MARKER.'tok_visa',
            'reference_id' => self::MARKER.'ref123',
            'status' => 'charged',
        ]);
        $count++;

        CreditTerm::create([
            'business_id' => $business->id,
            'customer_id' => $person->id,
            'terms_type' => 'net_30',
            'credit_limit_cents' => 100000,
            'current_outstanding_cents' => 20000,
        ]);
        $count++;

        $payment = Payment::create([
            'business_id' => $business->id,
            'amount_cents' => 15000,
            'payment_token' => self::MARKER.'tok_fail_1',
            'idempotency_key' => self::MARKER.'idem_fail_1',
            'gateway_charge_id' => self::MARKER.'fail_deferred',
            'status' => 'failed',
        ]);
        $count++;

        DeclineDeferral::create([
            'business_id' => $business->id,
            'payment_id' => $payment->id,
        ]);
        $count++;
        
        $payment2 = Payment::create([
            'business_id' => $business->id,
            'amount_cents' => 20000,
            'payment_token' => self::MARKER.'tok_fail_2',
            'idempotency_key' => self::MARKER.'idem_fail_2',
            'gateway_charge_id' => self::MARKER.'fail_active',
            'status' => 'failed',
        ]);
        $count++;

        return $count;
    }

    public function purge(Business $business): int
    {
        $count = 0;

        $invoices = Invoice::where('business_id', $business->id)->where('invoice_number', 'like', self::MARKER.'%')->get();
        foreach ($invoices as $inv) {
            $count += InvoiceLine::where('invoice_id', $inv->id)->delete();
            $inv->delete();
            $count++;
        }

        $count += OverflowCharge::where('business_id', $business->id)->where('reference_id', 'like', self::MARKER.'%')->delete();

        $person = Person::where('business_id', $business->id)->where('first_name', 'like', self::MARKER.'%')->first();
        if ($person) {
            $count += CreditTerm::where('business_id', $business->id)->where('customer_id', $person->id)->delete();
        }

        $payments = Payment::where('business_id', $business->id)->where('idempotency_key', 'like', self::MARKER.'%')->get();
        foreach ($payments as $pay) {
            $count += DeclineDeferral::where('payment_id', $pay->id)->delete();
            $pay->delete();
            $count++;
        }

        if ($person) {
            $person->delete();
        }

        return $count;
    }
}
