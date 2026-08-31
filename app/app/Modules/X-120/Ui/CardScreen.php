<?php

declare(strict_types=1);

namespace App\Modules\X120\Ui;

use App\Modules\X120\Models\CardToken;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CardScreen extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $cards = ($this->businessId > 0)
            ? CardToken::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-120::card-screen', [
            'cards' => $cards,
        ]);
    }
}
