<?php

declare(strict_types=1);

namespace App\Modules\X210\Ui;

use App\Modules\X210\Models\Promotion;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your offers'])]
class ActivePromotions extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $promotions = ($this->businessId > 0)
            ? Promotion::where('business_id', $this->businessId)->where('is_active', true)->get()
            : collect();

        return view('x-210::active-promotions', [
            'promotions' => $promotions,
        ]);
    }
}
