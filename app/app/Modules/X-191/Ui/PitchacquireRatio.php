<?php

declare(strict_types=1);

namespace App\Modules\X191\Ui;

use App\Modules\X191\Models\LinkPitch;
use App\Modules\X191\Models\LinkPlacement;
use Livewire\Component;

class PitchacquireRatio extends Component
{
    public int $businessId = 0;

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
