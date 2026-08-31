<?php

declare(strict_types=1);

namespace App\Modules\X01\Ui;

use App\Modules\X121\Models\Person;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CustomersList extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $persons = ($this->businessId > 0)
            ? Person::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-01::customers-list', [
            'persons' => $persons,
        ]);
    }
}
