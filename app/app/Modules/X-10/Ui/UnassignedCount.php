<?php

declare(strict_types=1);

namespace App\Modules\X10\Ui;

use Livewire\Component;

class UnassignedCount extends Component
{
    public function render()
    {
        return view('x-10::unassigned-count');
    }
}
