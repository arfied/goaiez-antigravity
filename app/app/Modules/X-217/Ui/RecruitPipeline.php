<?php

declare(strict_types=1);

namespace App\Modules\X217\Ui;

use App\Modules\X217\Models\AffiliateProspect;
use Livewire\Component;

class RecruitPipeline extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $prospects = ($this->businessId > 0)
            ? AffiliateProspect::where('business_id', $this->businessId)->with('offers')->get()
            : collect();

        return view('x-217::recruit-pipeline', [
            'prospects' => $prospects,
        ]);
    }
}
