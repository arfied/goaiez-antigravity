<?php

declare(strict_types=1);

namespace App\Modules\X170\Ui;

use App\Modules\X170\Models\Commission;
use Livewire\Component;

class Commissions extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $commissions = ($this->businessId > 0)
            ? Commission::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-170::commissions', [
            'commissions' => $commissions,
        ]);
    }
}
