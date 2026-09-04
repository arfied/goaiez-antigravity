<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X121\Models\Person;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\InvoiceLine;
use App\Support\Tenancy;
use Livewire\Component;

class Invoices extends Component
{
    public array $expanded = [];

    public function toggleExpanded(int $invoiceId): void
    {
        if (in_array($invoiceId, $this->expanded)) {
            $this->expanded = array_diff($this->expanded, [$invoiceId]);
        } else {
            $this->expanded[] = $invoiceId;
        }
    }

    public function recordPayment(int $invoiceId): void
    {
        app(InvoiceEngine::class)->recordPayment(Tenancy::idOrFail(), $invoiceId);
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);

        $invoices = Invoice::where('business_id', Tenancy::idOrFail())
            ->orderByDesc('created_at')
            ->get();

        $invoiceIds = $invoices->pluck('id')->toArray();
        $lines = InvoiceLine::where('business_id', Tenancy::idOrFail())
            ->whereIn('invoice_id', $invoiceIds)
            ->get()
            ->groupBy('invoice_id');

        foreach ($invoices as $invoice) {
            $person = Person::where('business_id', Tenancy::idOrFail())
                ->find($invoice->customer_id);
            $invoice->customer_name = $person ? trim($person->first_name.' '.$person->last_name) : 'Unknown';
            $invoice->lines = $lines->get($invoice->id, collect());
        }

        return view('x-199::invoices', [
            'invoices' => $invoices,
        ]);
    }
}
