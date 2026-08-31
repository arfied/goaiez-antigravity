<?php

declare(strict_types=1);

namespace App\Modules\X10\Ui;

use App\Modules\X10\Models\RoutingRule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RoutingRules extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $rules = ($this->businessId > 0)
            ? RoutingRule::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-10::routing-rules', [
            'rules' => $rules,
        ]);
    }
}
