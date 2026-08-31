<?php

declare(strict_types=1);

namespace App\Modules\X186\Ui;

use App\Modules\X186\Models\CampaignRun;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AudiencePreviewCount extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $count = ($this->businessId > 0)
            ? CampaignRun::where('business_id', $this->businessId)->where('is_active', true)->count()
            : 0;

        return view('x-186::audience-preview-count', [
            'count' => $count,
        ]);
    }
}
