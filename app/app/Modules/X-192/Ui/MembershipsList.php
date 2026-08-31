<?php

declare(strict_types=1);

namespace App\Modules\X192\Ui;

use App\Modules\X192\Models\DirectoryMembership;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MembershipsList extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $memberships = ($this->businessId > 0)
            ? DirectoryMembership::where('business_id', $this->businessId)->orderByDesc('rank_score')->get()
            : collect();

        return view('x-192::memberships-list', [
            'memberships' => $memberships,
        ]);
    }
}
