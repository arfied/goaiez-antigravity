<?php

declare(strict_types=1);

namespace App\Modules\X110\Ui;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Tag Versions'])]
class TagVersionPer extends Component
{
    public function render()
    {
        return view('x-110::tag-version-per');
    }
}
