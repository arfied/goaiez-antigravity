<?php

declare(strict_types=1);

namespace App\Modules\X186\Ui;

use App\Modules\X186\Models\CampaignRun;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'People in your campaigns'])]
class AudiencePreviewCount extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        if ($this->businessId <= 0) {
            return view('x-186::audience-preview-count', ['count' => 0, 'people' => 0]);
        }

        $query = CampaignRun::where('business_id', $this->businessId)
            ->where('is_active', true)
            ->where('is_suppressed', false);

        return view('x-186::audience-preview-count', [
            'count' => (clone $query)->count(),
            'people' => (clone $query)->distinct('person_id')->count('person_id'),
        ]);
    }
}
