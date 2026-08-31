<?php

declare(strict_types=1);

namespace App\Modules\X165\Ui;

use App\Modules\X165\Models\Membership;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Members extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $members = ($this->businessId > 0)
            ? Membership::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-165::members', [
            'members' => $members,
        ]);
    }
}
