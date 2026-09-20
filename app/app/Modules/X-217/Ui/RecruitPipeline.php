<?php

declare(strict_types=1);

namespace App\Modules\X217\Ui;

use App\Modules\X217\Models\AffiliateProspect;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RecruitPipeline extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

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
