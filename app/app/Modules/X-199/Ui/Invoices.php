<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X121\Actions\EntityReadAction;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\InvoiceLine;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Customer Invoices'])]
class Invoices extends Component
{
    public array $expanded = [];

    public ?string $error = null;

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
        $this->error = null;
        try {
            app(InvoiceEngine::class)->recordPayment(Tenancy::idOrFail(), $invoiceId);
        } catch (ModelNotFoundException) {
            $this->error = "That invoice isn't in this account any more — reload the list.";
        } catch (\Throwable $e) {
            $this->error = 'That payment was not recorded: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);

        $invoices = Invoice::where('business_id', Tenancy::idOrFail())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $invoiceIds = $invoices->pluck('id')->toArray();
        $lines = InvoiceLine::where('business_id', Tenancy::idOrFail())
            ->whereIn('invoice_id', $invoiceIds)
            ->get()
            ->groupBy('invoice_id');

        foreach ($invoices as $invoice) {
            $person = app(EntityReadAction::class)->handle('people', (int) $invoice->customer_id, Tenancy::idOrFail());
            $invoice->customer_name = $person ? trim(($person['first_name'] ?? '').' '.($person['last_name'] ?? '')) : 'Unknown';
            $invoice->lines = $lines->get($invoice->id, collect());
        }

        return view('x-199::invoices', [
            'invoices' => $invoices,
        ]);
    }
}
