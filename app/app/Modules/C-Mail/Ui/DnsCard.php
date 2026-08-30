<?php

declare(strict_types=1);

namespace App\Modules\CMail\Ui;

use Livewire\Component;

class DnsCard extends Component
{
    public function render()
    {
        return view('c-mail::dns-card');
    }
}
