<?php

declare(strict_types=1);

namespace App\Modules\X141\Ui;

use App\Modules\X141\Models\Counterfactual;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CounterfactualView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $counterfactuals = ($this->businessId > 0)
            ? Counterfactual::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-141::counterfactual-view', [
            'counterfactuals' => $counterfactuals,
        ]);
    }
}
