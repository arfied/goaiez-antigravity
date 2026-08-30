<?php

declare(strict_types=1);

namespace App\Modules\X191\Ui;

use App\Modules\X191\Models\LinkPlacement;
use Livewire\Component;

class LinksEarned extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $placements = ($this->businessId > 0)
            ? LinkPlacement::where('business_id', $this->businessId)->where('is_active', true)->get()
            : collect();

        return view('x-191::links-earned', [
            'placements' => $placements,
        ]);
    }
}
