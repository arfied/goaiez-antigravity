<?php

declare(strict_types=1);

namespace App\Modules\X118\Ui;

use Livewire\Component;

class DayOneSignup extends Component
{
    public int $askedFieldsCount = 2;

    public function render()
    {
        return view('x-118::day-one-signup');
    }
}
