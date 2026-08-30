<?php

declare(strict_types=1);

namespace App\Modules\X185\Ui;

use App\Modules\X185\Models\ContentPack;
use Livewire\Component;

class ExperimentBoard extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $packs = ($this->businessId > 0)
            ? ContentPack::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-185::experiment-board', [
            'packs' => $packs,
        ]);
    }
}
