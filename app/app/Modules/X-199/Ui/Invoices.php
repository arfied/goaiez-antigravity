<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X199\Models\Invoice;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Invoices extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    public bool $isSample = false;

    #[Locked]
    public ?string $loadError = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        if ($this->businessId === 0) {
            return view('x-199::invoices', [
                'totalCents' => 0,
                'invoices' => collect(),
            ]);
        }

        $invoices = Invoice::where('business_id', $this->businessId)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalCents = $invoices->sum('total_cents');

        return view('x-199::invoices', [
            'totalCents' => $totalCents,
            'invoices' => $invoices,
        ]);
    }
}
