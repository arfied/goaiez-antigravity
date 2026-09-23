<?php

declare(strict_types=1);

namespace App\Modules\X199\Actions;

use App\Modules\X199\Domain\InvoiceNumber;
use App\Modules\X199\Models\CreditTerm;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\InvoiceLine;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\DB;

final class InvoiceDraftAction
{
    public const DEFAULT_DUE_DAYS = 30;

    private function defaultDueDays(): int
    {
        return $this->registry->int('invoices.default_due_days');
    }

    public function __construct(private DefaultsRegistry $registry) {}

    public function handle(int $businessId, int $customerId, array $lines, ?int $dueDays = null): Invoice
    {
        return DB::transaction(function () use ($businessId, $customerId, $lines, $dueDays) {
            $dueDays ??= $this->defaultDueDays();
            $totalCents = 0;
            foreach ($lines as $line) {
                $totalCents += ($line['quantity'] ?? 1) * ($line['unit_price_cents'] ?? 0);
            }

            $terms = CreditTerm::where('business_id', $businessId)
                ->where('customer_id', $customerId)
                ->first();

            $effectiveDueDays = $terms !== null
                ? (CreditTerm::TERMS_DAYS[$terms->terms_type] ?? 0)
                : $dueDays;

            $invoice = Invoice::create([
                'business_id' => $businessId,
                'customer_id' => $customerId,
                'invoice_number' => InvoiceNumber::next($businessId),
                'total_cents' => $totalCents,
                'paid_cents' => 0,
                'status' => 'draft',
                'due_date' => now()->addDays($effectiveDueDays)->toDateString(),
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

            return $invoice;
        });
    }
}
