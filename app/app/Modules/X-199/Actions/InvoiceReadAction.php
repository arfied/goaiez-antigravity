<?php

declare(strict_types=1);

namespace App\Modules\X199\Actions;

use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\InvoiceLine;

final class InvoiceReadAction
{
    public function forPortal(int $businessId, int $invoiceId): ?array
    {
        $invoice = Invoice::where('business_id', $businessId)->find($invoiceId);
        if ($invoice === null) {
            return null;
        }

        $outstanding = max(0, (int) $invoice->total_cents - (int) $invoice->paid_cents);
        $lines = InvoiceLine::where('business_id', $businessId)->where('invoice_id', $invoice->id)->orderBy('id')->get()
            ->map(fn (InvoiceLine $l) => ['label' => (string) $l->description, 'quantity' => (int) $l->quantity, 'subtotal_cents' => (int) $l->subtotal_cents])
            ->all();

        return [
            'kind' => 'Invoice',
            'number' => (string) $invoice->invoice_number,
            'state' => match ((string) $invoice->status) {
                'paid', 'offline_recorded' => 'Paid',
                'overdue' => 'Overdue — was due '.$invoice->due_date?->format('j M Y'),
                'issued', 'due', 'sent' => 'Due '.$invoice->due_date?->format('j M Y'),
                default => 'Being prepared',
            },
            'lines' => $lines,
            'total_cents' => (int) $invoice->total_cents,
            'secondary' => $outstanding > 0 && (int) $invoice->paid_cents > 0
                ? 'Paid so far: $'.number_format(((int) $invoice->paid_cents) / 100, 2).' · Outstanding: $'.number_format($outstanding / 100, 2)
                : null,
        ];
    }
}
