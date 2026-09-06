<?php

declare(strict_types=1);

namespace App\Modules\X199\Domain;

use App\Modules\X199\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;

/**
 * X-199
 * (R245) X-199 owns reads of its own table
 */
final class InvoiceReader
{
    public function forBusiness(int $businessId, int $invoiceId): Invoice
    {
        return Invoice::where('business_id', $businessId)->findOrFail($invoiceId);
    }

    public function overdueIssued(): Collection
    {
        return Invoice::where('due_date', '<', now()->toDateString())
            ->where('status', 'issued')
            ->get();
    }

    public function linesForInvoice(int $businessId, int $invoiceId): array
    {
        return \App\Modules\X199\Models\InvoiceLine::where('business_id', $businessId)
            ->where('invoice_id', $invoiceId)
            ->orderBy('id')
            ->get(['description', 'quantity', 'subtotal_cents'])
            ->toArray();
    }
}
