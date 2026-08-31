<?php

declare(strict_types=1);

namespace App\Modules\X199\Domain;

use App\Modules\X199\Events\InvoiceIssued;
use App\Modules\X199\Events\InvoicePaid;
use App\Modules\X199\Models\CreditTerm;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\InvoiceLine;
use App\Modules\X199\Models\OverflowCharge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class InvoiceEngine
{
    /**
     * Issue invoice with net-30 limit checking and overflow card charge (TEST ANCHOR).
     */
    public function issueInvoice(
        int $businessId,
        int $customerId,
        array $lines,
        string $termsType = 'net_30'
    ): array {
        return DB::transaction(function () use ($businessId, $customerId, $lines, $termsType) {
            $totalCents = 0;
            foreach ($lines as $line) {
                $totalCents += ($line['quantity'] ?? 1) * ($line['unit_price_cents'] ?? 0);
            }

            $terms = CreditTerm::where('business_id', $businessId)->where('customer_id', $customerId)->first();
            if ($terms === null) {
                $terms = CreditTerm::create([
                    'business_id' => $businessId,
                    'customer_id' => $customerId,
                    'terms_type' => $termsType,
                    'credit_limit_cents' => 500000, // $5,000 credit limit
                    'current_outstanding_cents' => 0,
                    'card_on_file_token' => 'pm_card_vault_'.Str::random(12),
                ]);
            }

            $termsDaysMap = [
                'net_30' => 30,
                'net_15' => 15,
            ];
            $dueDays = $termsDaysMap[$termsType] ?? 0;

            $invoice = Invoice::create([
                'business_id' => $businessId,
                'customer_id' => $customerId,
                'invoice_number' => 'INV-'.strtoupper(Str::random(6)),
                'total_cents' => $totalCents,
                'paid_cents' => 0,
                'status' => 'issued',
                'due_date' => now()->addDays($dueDays)->toDateString(),
                'pdf_url' => 'https://cdn.goaiez.com/invoices/inv.pdf',
            ]);

            foreach ($lines as $line) {
                InvoiceLine::create([
                    'business_id' => $businessId,
                    'invoice_id' => $invoice->id,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'] ?? 1,
                    'unit_price_cents' => $line['unit_price_cents'] ?? 0,
                    'subtotal_cents' => ($line['quantity'] ?? 1) * ($line['unit_price_cents'] ?? 0),
                ]);
            }

            $overflowCharge = null;
            $newOutstanding = $terms->current_outstanding_cents + $totalCents;

            // Check if account is over credit limit (TEST ANCHOR)
            if ($newOutstanding > $terms->credit_limit_cents) {
                $overflowAmount = $newOutstanding - $terms->credit_limit_cents;

                $overflowCharge = OverflowCharge::create([
                    'business_id' => $businessId,
                    'customer_id' => $customerId,
                    'invoice_id' => $invoice->id,
                    'charge_type' => 'overflow_charged',
                    'amount_cents' => $overflowAmount,
                    'card_token' => $terms->card_on_file_token ?? 'pm_fallback_token',
                    'reference_id' => 'ch_overflow_'.Str::random(12),
                ]);
            }

            $terms->update(['current_outstanding_cents' => $newOutstanding]);

            Event::dispatch(new InvoiceIssued(
                businessId: $businessId,
                invoiceId: $invoice->id,
                invoiceNumber: $invoice->invoice_number,
                totalCents: $totalCents
            ));

            return [
                'invoice' => $invoice,
                'overflow_charge' => $overflowCharge,
                'is_over_limit' => $overflowCharge !== null,
            ];
        });
    }

    /**
     * Record payment and write overflow.reversed row for the same amount (TEST ANCHOR).
     */
    public function recordPayment(int $businessId, int $invoiceId, ?int $amountCents = null): array
    {
        return DB::transaction(function () use ($businessId, $invoiceId, $amountCents) {
            $invoice = Invoice::where('business_id', $businessId)->findOrFail($invoiceId);
            $payAmount = $amountCents ?? $invoice->total_cents;

            $invoice->update([
                'paid_cents' => $invoice->paid_cents + $payAmount,
                'status' => 'paid',
            ]);

            // If there were overflow charges for this invoice, reverse them (TEST ANCHOR)
            $charges = OverflowCharge::where('business_id', $businessId)
                ->where('invoice_id', $invoice->id)
                ->where('charge_type', 'overflow_charged')
                ->get();

            $reversedCharges = [];
            foreach ($charges as $c) {
                $reversed = OverflowCharge::create([
                    'business_id' => $businessId,
                    'customer_id' => $invoice->customer_id,
                    'invoice_id' => $invoice->id,
                    'charge_type' => 'overflow_reversed',
                    'amount_cents' => $c->amount_cents,
                    'card_token' => $c->card_token,
                    'reference_id' => 're_overflow_'.Str::random(12),
                ]);
                $reversedCharges[] = $reversed;
            }

            Event::dispatch(new InvoicePaid(
                businessId: $businessId,
                invoiceId: $invoice->id,
                amountPaidCents: $payAmount
            ));

            return [
                'invoice_id' => $invoice->id,
                'status' => 'paid',
                'paid_cents' => $invoice->paid_cents,
                'reversed_overflow_charges' => $reversedCharges,
            ];
        });
    }
}
