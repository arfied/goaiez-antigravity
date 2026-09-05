<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\OverflowCharge;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Component;

class Unpaid extends Component
{
    public array $expanded = [];

    public ?string $error = null;

    public bool $showLastFivePaid = false;

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

    public function showPaid(): void
    {
        $this->showLastFivePaid = true;
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);

        $query = Invoice::where('business_id', Tenancy::idOrFail());

        if ($this->showLastFivePaid) {
            $query->where('status', 'paid')
                ->orderByDesc('created_at')
                ->limit(5);
        } else {
            $query->whereNotIn('status', ['paid', 'draft'])
                ->whereRaw('paid_cents < total_cents')
                ->orderBy('due_date', 'asc');
        }

        $invoices = $query->get();

        $count = 0;
        $valueCents = 0;

        if (! $this->showLastFivePaid) {
            // Recalculate totals from all unpaid regardless of pagination/limit
            $allUnpaid = Invoice::where('business_id', Tenancy::idOrFail())
                ->whereNotIn('status', ['paid', 'draft'])
                ->whereRaw('paid_cents < total_cents')
                ->get();
            $count = $allUnpaid->count();
            $valueCents = $allUnpaid->sum(fn ($i) => $i->total_cents - $i->paid_cents);
        }

        $invoiceIds = $invoices->pluck('id')->toArray();

        $overflows = OverflowCharge::where('business_id', Tenancy::idOrFail())
            ->whereIn('invoice_id', $invoiceIds)
            ->get();

        $charged = $overflows->where('charge_type', 'overflow_charged')->where('status', 'charged');
        $reversed = $overflows->where('charge_type', 'overflow_reversed')->pluck('invoice_id')->toArray();

        foreach ($invoices as $invoice) {
            $invoice->has_overflow = $charged->contains('invoice_id', $invoice->id) && ! in_array($invoice->id, $reversed);

            $days = 0;
            if ($invoice->due_date && $invoice->due_date->isPast() && ! $invoice->due_date->isToday()) {
                $days = $invoice->due_date->diffInDays(now());
            }
            $invoice->days_overdue = $days;
            $invoice->outstanding_cents = $invoice->total_cents - $invoice->paid_cents;
        }

        return view('x-199::unpaid', [
            'invoices' => $invoices,
            'unpaidCount' => $count,
            'unpaidValueCents' => $valueCents,
        ]);
    }
}
