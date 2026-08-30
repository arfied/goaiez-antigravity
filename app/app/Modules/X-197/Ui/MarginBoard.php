<?php

declare(strict_types=1);

namespace App\Modules\X197\Ui;

use App\Modules\X197\Models\VoiceCostSample;
use Livewire\Component;

class MarginBoard extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $samples = ($this->businessId > 0)
            ? VoiceCostSample::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-197::margin-board', [
            'samples' => $samples,
        ]);
    }
}
