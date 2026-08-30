<?php

declare(strict_types=1);

namespace App\Modules\X184\Ui;

use App\Modules\X184\Models\ContentPlan;
use Livewire\Component;

class ContentWeek extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $plans = ($this->businessId > 0)
            ? ContentPlan::where('business_id', $this->businessId)->with('items')->get()
            : collect();

        return view('x-184::content-week', [
            'plans' => $plans,
        ]);
    }
}
