<?php

declare(strict_types=1);

namespace App\Modules\X119\Ui;

use Livewire\Component;

class PriceConfirmationScreen extends Component
{
    public function mount(): void
    {
        $this->redirectRoute('x-163.confirmation-screen');
    }

    public function render()
    {
        return view('x-119::price-confirmation-screen');
    }
}
