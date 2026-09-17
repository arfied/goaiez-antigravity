<?php

declare(strict_types=1);

namespace App\Modules\X191\Ui;

use App\Modules\X191\Models\LinkPitch;
use App\Modules\X191\Models\LinkPlacement;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Outreach ratio'])]
class PitchacquireRatio extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $pitchesCount = ($this->businessId > 0) ? LinkPitch::where('business_id', $this->businessId)->count() : 0;
        $earnedCount = ($this->businessId > 0) ? LinkPlacement::where('business_id', $this->businessId)->count() : 0;

        return view('x-191::pitchacquire-ratio', [
            'pitches' => $pitchesCount,
            'earned' => $earnedCount,
        ]);
    }
}
