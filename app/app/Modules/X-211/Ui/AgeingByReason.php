<?php

declare(strict_types=1);

namespace App\Modules\X211\Ui;

use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Models\OfflinePayment;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AgeingByReason extends Component
{
    public $referenceNumber = '';

    public function logPayment(int $invoiceId): void
    {
        $this->resetErrorBag();
        if (trim($this->referenceNumber) === '') {
            $this->addError('referenceNumber', 'Reference number is required.');

            return;
        }

        $invoice = Invoice::where('business_id', Tenancy::idOrFail())->findOrFail($invoiceId);
        $amountCents = $invoice->total_cents - $invoice->paid_cents;

        OfflinePayment::create([
            'business_id' => Tenancy::idOrFail(),
            'invoice_id' => $invoice->id,
            'amount_cents' => $amountCents,
            'payment_method' => 'check',
            'reference_number' => $this->referenceNumber,
        ]);

        $invoice->update([
            'paid_cents' => $invoice->total_cents,
            'status' => 'paid',
        ]);

        $this->referenceNumber = '';
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);

        $invoices = Invoice::where('business_id', Tenancy::idOrFail())
            ->where('status', 'issued')
            ->where('due_date', '<', now())
            ->get();

        $groups = [];
        $noReason = [];

        foreach ($invoices as $inv) {
            $latestAction = DB::table('ar_dunning_actions')
                ->where('invoice_id', $inv->id)
                ->orderByDesc('created_at')
                ->first();

            if ($latestAction) {
                $groups[$latestAction->reason][] = $inv;
            } else {
                $noReason[] = $inv;
            }
        }

        ksort($groups);

        if (! empty($noReason)) {
            $groups['No reason recorded yet'] = $noReason;
        }

        return view('x-211::ageing-by-reason', [
            'groups' => $groups,
        ]);
    }
}
