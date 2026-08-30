<?php

declare(strict_types=1);

namespace App\Modules\X220\Ui;

use App\Modules\X220\Models\GoldenSet;
use Livewire\Component;

class EvalReport extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $sets = ($this->businessId > 0)
            ? GoldenSet::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-220::eval-report', [
            'sets' => $sets,
        ]);
    }
}
