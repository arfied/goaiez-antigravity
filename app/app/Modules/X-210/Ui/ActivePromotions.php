<?php

declare(strict_types=1);

namespace App\Modules\X210\Ui;

use App\Modules\X210\Models\Promotion;
use Livewire\Component;

class ActivePromotions extends Component
{
    public int $businessId = 0;

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
