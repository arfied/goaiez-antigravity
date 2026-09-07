<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X199\Models\Invoice;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Money Paid Today
 * Derives paid today from invoices updated today with status 'paid' or 'offline_recorded', summing paid_cents.
 */
#[Layout('components.account.layout', ['heading' => 'Happened Today'])]
class MoneyPaidToday extends Component
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
            return view('x-199::money-paid-today', [
                'total' => 0,
                'invoices' => collect(),
            ]);
        }

        $invoices = Invoice::where('business_id', $this->businessId)
            ->whereIn('status', ['paid', 'offline_recorded'])
            ->whereDate('updated_at', now()->toDateString())
            ->get();

        $totalCents = $invoices->sum('paid_cents');

        return view('x-199::money-paid-today', [
            'total' => $totalCents,
            'invoices' => $invoices,
        ]);
    }
}
