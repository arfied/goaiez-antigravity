<?php

declare(strict_types=1);

namespace App\Modules\X112\Ui;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.agency')]
class Roles extends Component
{
    public function render()
    {
        return view('x-112::roles');
    }
}
