<?php

declare(strict_types=1);

namespace App\Modules\X142\Ui;

use Livewire\Component;

class WebhooksView extends Component
{
    public function mount(): void {}

    public function render()
    {
        return view('x-142::webhooks');
    }
}
