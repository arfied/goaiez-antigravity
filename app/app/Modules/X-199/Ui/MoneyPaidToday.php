<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\InvoiceLine;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Component;

class MoneyPaidToday extends Component
{
    public ?int $explainedInvoiceId = null;

    public ?string $error = null;

    public $invoiceLines = [];

    public function explain(int $invoiceId): void
    {
        $this->error = null;
        try {
            $invoice = Invoice::where('business_id', Tenancy::idOrFail())
                ->where('status', 'paid')
                ->where('updated_at', '>=', now()->startOfDay())
                ->findOrFail($invoiceId);

            $this->explainedInvoiceId = $invoice->id;
            $this->invoiceLines = InvoiceLine::where('business_id', Tenancy::idOrFail())
                ->where('invoice_id', $invoice->id)
                ->get();
        } catch (ModelNotFoundException) {
            $this->error = "That invoice isn't in this account any more.";
        } catch (\Throwable $e) {
            $this->error = 'Error explaining invoice: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);

        $invoices = Invoice::where('business_id', Tenancy::id())
            ->where('status', 'paid')
            ->where('updated_at', '>=', now()->startOfDay())
            ->orderByDesc('updated_at')
            ->get();

        $totalCents = $invoices->sum('total_cents');

        return view('x-199::money-paid-today', [
            'invoices' => $invoices,
            'totalCents' => $totalCents,
        ]);
    }
}
