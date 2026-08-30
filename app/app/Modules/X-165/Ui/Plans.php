<?php

declare(strict_types=1);

namespace App\Modules\X165\Ui;

use App\Modules\X165\Models\MembershipPlan;
use Livewire\Component;

class Plans extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $plans = ($this->businessId > 0)
            ? MembershipPlan::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-165::plans', [
            'plans' => $plans,
        ]);
    }
}
