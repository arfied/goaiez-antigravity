<?php

declare(strict_types=1);

namespace App\Modules\X210\Ui;

use App\Modules\X210\Models\PromotionScope;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TargetingPreview extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $scopes = ($this->businessId > 0)
            ? PromotionScope::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-210::targeting-preview', [
            'scopes' => $scopes,
        ]);
    }
}
