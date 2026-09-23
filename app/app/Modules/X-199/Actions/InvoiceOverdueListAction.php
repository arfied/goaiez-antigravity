<?php

declare(strict_types=1);

namespace App\Modules\X199\Actions;

use App\Modules\X199\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceOverdueListAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(int $businessId, ?int $customerId = null): array
    {
        $query = Invoice::query()
            ->select('invoices.*', 'people.first_name', 'people.last_name')
            ->join('people', 'invoices.customer_id', '=', 'people.id')
            ->where('invoices.business_id', $businessId)
            ->where('invoices.paid_cents', '<', DB::raw('invoices.total_cents'))
            ->whereNotIn('invoices.status', ['paid', 'draft'])
            ->where('invoices.due_date', '<', now()->toDateString())
            ->orderBy('invoices.due_date', 'asc');

        if ($customerId !== null) {
            $query->where('invoices.customer_id', $customerId);
        }

        $invoices = $query->get();

        return array_map(function ($invoice) {
            $customerName = trim(($invoice->first_name ?? '').' '.($invoice->last_name ?? ''));

            return [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
                'customer_name' => $customerName ?: 'Unknown',
                'due_date' => $invoice->due_date ? $invoice->due_date->toDateString() : null,
                'days_overdue' => $invoice->due_date ? (int) $invoice->due_date->startOfDay()->diffInDays(now()->startOfDay(), true) : 0,
                'outstanding_cents' => $invoice->total_cents - $invoice->paid_cents,
            ];
        }, $invoices->all());
    }
}
