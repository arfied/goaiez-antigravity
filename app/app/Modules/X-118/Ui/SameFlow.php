<?php

declare(strict_types=1);

namespace App\Modules\X118\Ui;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.agency')]
class SameFlow extends Component
{
    public function render()
    {
        return view('x-118::same-flow');
    }
}
