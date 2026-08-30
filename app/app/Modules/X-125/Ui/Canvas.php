<?php

declare(strict_types=1);

namespace App\Modules\X125\Ui;

use App\Modules\X125\Models\Flow;
use Livewire\Component;

class Canvas extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $flows = ($this->businessId > 0)
            ? Flow::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-125::canvas', [
            'flows' => $flows,
        ]);
    }
}
