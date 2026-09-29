<?php

declare(strict_types=1);

namespace App\Modules\X199\Domain;

use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X199\Events\InvoiceIssued;
use App\Modules\X199\Events\InvoiceOverdue;
use App\Modules\X199\Events\InvoicePaid;
use App\Modules\X199\Events\LimitExceeded;
use App\Modules\X199\Events\OverflowCharged;
use App\Modules\X199\Events\OverflowReversed;
use App\Modules\X199\Models\CreditTerm;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\InvoiceLine;
use App\Modules\X199\Models\OverflowCharge;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

final class InvoiceEngine
{
    public const DEFAULT_CREDIT_LIMIT_CENTS = 500000;

    /**
     * Issue invoice with net-30 limit checking and overflow card charge (TEST ANCHOR).
     */
    public function defaultCreditLimitCents(): int
    {
        return $this->registry->int('invoices.default_credit_limit_cents');
    }

    public function __construct(private DefaultsRegistry $registry) {}

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

            $terms = CreditTerm::firstOrCreate(
                ['business_id' => $businessId, 'customer_id' => $customerId],
                [
                    'terms_type' => $termsType,
                    'credit_limit_cents' => $this->defaultCreditLimitCents(), // $5,000 credit limit
                    'current_outstanding_cents' => 0,
                    'card_on_file_token' => null,
                ]
            );

            $dueDays = CreditTerm::TERMS_DAYS[$terms->terms_type] ?? 0;

            $invoice = Invoice::create([
                'business_id' => $businessId,
                'customer_id' => $customerId,
                'invoice_number' => InvoiceNumber::next($businessId),
                'total_cents' => $totalCents,
                'paid_cents' => 0,
                'status' => 'issued',
                'due_date' => now()->addDays($dueDays)->toDateString(),
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
                Event::dispatch(new LimitExceeded(
                    businessId: $businessId,
                    customerId: $customerId,
                    limitCents: $terms->credit_limit_cents,
                    outstandingCents: $newOutstanding
                ));

                $overflowAmount = $newOutstanding - $terms->credit_limit_cents;
                $cardToken = $terms->card_on_file_token;

                $status = 'refused';
                $gatewayChargeId = null;

                try {
                    $gatewayEngine = app(GatewayEngine::class);
                    $payment = $gatewayEngine->capture(
                        businessId: $businessId,
                        amountCents: $overflowAmount,
                        paymentToken: $cardToken,
                        idempotencyKey: 'overflow_'.$invoice->id.'_'.$overflowAmount
                    );
                    if (is_array($payment)) {
                        $gatewayChargeId = null;
                        $status = 'refused';
                    } else {
                        $gatewayChargeId = $payment->gateway_charge_id;
                        $status = $payment->status === 'captured' ? 'charged' : 'refused';
                    }
                } catch (\Exception $e) {
                    Log::warning('Gateway capture failed: '.$e->getMessage(), [
                        'business_id' => $businessId,
                        'customer_id' => $customerId,
                        'invoice_id' => $invoice->id,
                        'amount_cents' => $overflowAmount,
                        'exception' => $e,
                    ]);
                    $status = 'refused';
                }

                $overflowCharge = OverflowCharge::create([
                    'business_id' => $businessId,
                    'customer_id' => $customerId,
                    'invoice_id' => $invoice->id,
                    'charge_type' => 'overflow_charged',
                    'amount_cents' => $overflowAmount,
                    'card_token' => $cardToken,
                    'reference_id' => $gatewayChargeId,
                    'status' => $status,
                ]);

                if ($status === 'charged') {
                    Event::dispatch(new OverflowCharged(
                        businessId: $businessId,
                        customerId: $customerId,
                        invoiceId: $invoice->id,
                        amountCents: $overflowAmount,
                        gatewayChargeId: $overflowCharge->reference_id
                    ));
                }
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

            // A payment is recorded against an OPEN invoice. `paid` and `draft` are exactly
            // InvoiceReader's own definition of not-open (:41, :49), never a second literal
            // list, so an `overdue` invoice stays payable. The throw is before the first write.
            if (in_array($invoice->status, ['paid', 'draft'], true)) {
                throw new InvoiceNotPayableException(sprintf(
                    'Invoice %s is %s: a payment is only recorded against an open invoice. Nothing was recorded.',
                    $invoice->invoice_number,
                    $invoice->status
                ));
            }

            $payAmount = $amountCents ?? max(0, $invoice->total_cents - $invoice->paid_cents);

            $newPaid = $invoice->paid_cents + $payAmount;
            $status = $newPaid >= $invoice->total_cents ? 'paid' : $invoice->status;

            $invoice->update([
                'paid_cents' => $newPaid,
                'status' => $status,
                'paid_at' => $status === 'paid' ? now() : null,
            ]);

            $reversedCharges = [];

            if ($status === 'paid') {
                // If there were overflow charges for this invoice, reverse them (TEST ANCHOR)
                $charges = OverflowCharge::where('business_id', $businessId)
                    ->where('invoice_id', $invoice->id)
                    ->where('charge_type', 'overflow_charged')
                    ->where('status', 'charged')
                    ->get();

                foreach ($charges as $c) {
                    $reversed = OverflowCharge::create([
                        'business_id' => $businessId,
                        'customer_id' => $invoice->customer_id,
                        'invoice_id' => $invoice->id,
                        'charge_type' => 'overflow_reversed',
                        'amount_cents' => $c->amount_cents,
                        'card_token' => $c->card_token,
                        'reference_id' => null,
                    ]);
                    $reversedCharges[] = $reversed;

                    Event::dispatch(new OverflowReversed(
                        businessId: $businessId,
                        customerId: $invoice->customer_id,
                        invoiceId: $invoice->id,
                        amountCents: $c->amount_cents
                    ));
                }

                Event::dispatch(new InvoicePaid(
                    businessId: $businessId,
                    invoiceId: $invoice->id,
                    amountPaidCents: $payAmount
                ));
            }

            return [
                'invoice_id' => $invoice->id,
                'status' => $status,
                'paid_cents' => $invoice->paid_cents,
                'reversed_overflow_charges' => $reversedCharges,
            ];
        });
    }

    public function markOverdue(int $businessId, int $invoiceId): void
    {
        DB::transaction(function () use ($businessId, $invoiceId) {
            $invoice = Invoice::where('business_id', $businessId)->findOrFail($invoiceId);

            if ($invoice->status !== 'issued') {
                return;
            }

            $invoice->update(['status' => 'overdue']);

            // Calculate days overdue. Using due_date (cast to 'date'/midnight) as the receiver ensures diffInDays counts full days passed.
            $daysOverdue = (int) max(1, $invoice->due_date->diffInDays(now()));

            Event::dispatch(new InvoiceOverdue(
                businessId: $businessId,
                invoiceId: $invoice->id,
                daysOverdue: $daysOverdue
            ));
        });
    }
}
