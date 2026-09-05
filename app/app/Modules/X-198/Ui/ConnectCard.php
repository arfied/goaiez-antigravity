<?php

declare(strict_types=1);

namespace App\Modules\X198\Ui;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.agency')]
class ConnectCard extends Component
{
    public function render()
    {
        return view('x-198::connect-card');
    }
}
