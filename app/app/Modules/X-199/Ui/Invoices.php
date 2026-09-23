<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X121\Actions\EntityReadAction;
use App\Modules\X121\Actions\PersonLookupAction;
use App\Modules\X199\Actions\InvoiceDraftAction;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Models\CreditTerm;
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

    public string $customerId = '';

    public string $lineDescription = '';

    public int $lineQuantity = 1;

    public int $lineUnitPriceCents = 0;

    public ?string $success = null;

    public function draftInvoice(InvoiceDraftAction $action): void
    {
        $this->error = null;
        $this->success = null;

        if (trim($this->customerId) === '' || ! ctype_digit(trim($this->customerId))) {
            $this->error = 'Choose a customer before drafting an invoice.';

            return;
        }
        if (trim($this->lineDescription) === '') {
            $this->error = 'Describe the work before drafting an invoice.';

            return;
        }
        if ($this->lineQuantity < 1) {
            $this->error = 'Quantity must be at least 1.';

            return;
        }

        $people = app(PersonLookupAction::class)->listForBusiness(Tenancy::idOrFail());
        $validCustomerIds = array_column($people, 'id');
        if (! in_array((int) $this->customerId, $validCustomerIds, true)) {
            $this->error = 'Choose a customer before drafting an invoice.';

            return;
        }

        $invoice = $action->handle(Tenancy::idOrFail(), (int) $this->customerId, [
            [
                'description' => $this->lineDescription,
                'quantity' => $this->lineQuantity,
                'unit_price_cents' => $this->lineUnitPriceCents,
            ],
        ]);

        $terms = CreditTerm::where('business_id', Tenancy::idOrFail())
            ->where('customer_id', (int) $this->customerId)
            ->first();

        $termsNote = $terms !== null
            ? 'their agreed '.$terms->terms_type.' terms'
            : 'the 30-day default, because no credit terms are set for them';

        $this->success = 'Drafted invoice '.$invoice->invoice_number.' for $'
            .number_format($invoice->total_cents / 100, 2).', due '.$invoice->due_date
            .' ('.$termsNote.'). It stays a draft: nothing issues it, nothing sends it to the customer, '
            .'and no money has been requested.';

        $this->customerId = '';
        $this->lineDescription = '';
        $this->lineQuantity = 1;
        $this->lineUnitPriceCents = 0;
    }

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

        $people = app(PersonLookupAction::class)->listForBusiness(Tenancy::idOrFail());

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
            'people' => $people,
        ]);
    }
}
