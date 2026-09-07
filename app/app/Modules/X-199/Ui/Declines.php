<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X199\Models\OverflowCharge;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Payment Declines & Exceptions'])]
class Declines extends Component
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
            return view('x-199::declines', [
                'totalDeclined' => 0,
                'charges' => collect(),
            ]);
        }

        $charges = OverflowCharge::where('business_id', $this->businessId)
            ->where('charge_type', 'overflow_reversed')
            ->get();

        $totalDeclined = $charges->sum('amount_cents');

        return view('x-199::declines', [
            'totalDeclined' => $totalDeclined,
            'charges' => $charges,
        ]);
    }
}
