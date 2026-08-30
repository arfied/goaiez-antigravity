<?php

declare(strict_types=1);

namespace App\Modules\X186\Ui;

use App\Modules\X186\Models\CampaignRun;
use Livewire\Component;

class LiveRun extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $runs = ($this->businessId > 0)
            ? CampaignRun::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-186::live-run', [
            'runs' => $runs,
        ]);
    }
}
