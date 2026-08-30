<?php

declare(strict_types=1);

namespace App\Modules\X172\Ui;

use Livewire\Component;

class CustomerfacingPortal extends Component
{
    public string $token = '';

    public function render()
    {
        return view('x-172::customerfacing-portal');
    }
}
