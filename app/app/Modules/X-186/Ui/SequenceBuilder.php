<?php

declare(strict_types=1);

namespace App\Modules\X186\Ui;

use App\Modules\X186\Models\CampaignStep;
use Livewire\Component;

class SequenceBuilder extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $steps = ($this->businessId > 0)
            ? CampaignStep::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-186::sequence-builder', [
            'steps' => $steps,
        ]);
    }
}
