<?php

declare(strict_types=1);

namespace App\Modules\X199\Domain;

use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\InvoiceLine;
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

    public function overdueIssued(int $businessId): Collection
    {
        return Invoice::where('business_id', $businessId)
            ->where('due_date', '<', now()->toDateString())
            ->where('status', 'issued')
            ->get();
    }

    public function linesForInvoice(int $businessId, int $invoiceId): array
    {
        return InvoiceLine::where('business_id', $businessId)
            ->where('invoice_id', $invoiceId)
            ->orderBy('id')
            ->get(['description', 'quantity', 'subtotal_cents'])
            ->toArray();
    }

    public function openForBusiness(int $businessId): Collection
    {
        return Invoice::where('business_id', $businessId)
            ->whereNotIn('status', ['paid', 'draft'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    public function openUnpaidForBusiness(int $businessId, array $excludeIds = []): Collection
    {
        return Invoice::where('business_id', $businessId)
            ->whereNotIn('status', ['paid', 'draft'])
            ->whereColumn('paid_cents', '<', 'total_cents')
            ->whereNotIn('id', $excludeIds)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    public function openOverdueForBusiness(int $businessId, array $excludeIds = []): Collection
    {
        return Invoice::where('business_id', $businessId)
            ->whereNotIn('status', ['paid', 'draft'])
            ->whereDate('due_date', '<', today())
            ->whereNotIn('id', $excludeIds)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    public function unpaidOverdueForBusiness(int $businessId): Collection
    {
        return Invoice::where('business_id', $businessId)
            ->where('status', '!=', 'paid')
            ->whereDate('due_date', '<', today())
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    public function numbersById(int $businessId, array $ids)
    {
        return Invoice::where('business_id', $businessId)
            ->whereIn('id', $ids)
            ->pluck('invoice_number', 'id');
    }

    public function linesFor(int $businessId, int $invoiceId): Collection
    {
        return InvoiceLine::where('business_id', $businessId)
            ->where('invoice_id', $invoiceId)
            ->orderBy('id')
            ->get();
    }
}
