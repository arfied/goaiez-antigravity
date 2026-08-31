<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Reports extends Component
{
    public function render(): View
    {
        return view('livewire.advanced.reports', [
            'businessId' => Tenancy::id(),
        ]);
    }
}
