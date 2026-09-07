<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X199\Models\Invoice;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Unpaid Invoices'])]
class Unpaid extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    public ?string $loadError = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        if ($this->businessId === 0) {
            return view('x-199::unpaid', [
                'totalOutstanding' => 0,
                'invoices' => collect(),
            ]);
        }

        $invoices = Invoice::where('business_id', $this->businessId)
            ->whereIn('status', ['issued', 'due'])
            ->whereColumn('paid_cents', '<', 'total_cents')
            ->get();

        $totalOutstanding = $invoices->sum(function ($invoice) {
            return $invoice->total_cents - $invoice->paid_cents;
        });

        return view('x-199::unpaid', [
            'totalOutstanding' => $totalOutstanding,
            'invoices' => $invoices,
        ]);
    }
}
