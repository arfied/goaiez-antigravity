<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X199\Models\Invoice;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Derives paid today from invoices updated today with status 'paid' or 'offline_recorded', summing paid_cents.
 */
class MoneyPaidToday extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[\Livewire\Attributes\Locked]
    public bool $isSample = false;

    #[\Livewire\Attributes\Locked]
    public ?string $loadError = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId;
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
