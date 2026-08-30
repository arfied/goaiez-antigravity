<?php

declare(strict_types=1);

namespace App\Modules\X183\Ui;

use App\Modules\X183\Models\GateResult;
use Livewire\Component;

class GateRejectionReasons extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $rejections = ($this->businessId > 0)
            ? GateResult::where('business_id', $this->businessId)->where('passed', false)->get()
            : collect();

        return view('x-183::gate-rejection-reasons', [
            'rejections' => $rejections,
        ]);
    }
}
