<?php

declare(strict_types=1);

namespace App\Modules\X209\Ui;

use App\Modules\X209\Models\FixerLadder;
use Livewire\Component;

class LaddersOwnState extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $ladders = ($this->businessId > 0)
            ? FixerLadder::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-209::ladders-own-state', [
            'ladders' => $ladders,
        ]);
    }
}
