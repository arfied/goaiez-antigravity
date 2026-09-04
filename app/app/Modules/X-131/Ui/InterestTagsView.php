<?php

declare(strict_types=1);

namespace App\Modules\X131\Ui;

use App\Modules\X131\Models\PersonInterest;
use Livewire\Attributes\Locked;
use Livewire\Component;

class InterestTagsView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public int $personId = 0;

    public function render()
    {
        $interests = ($this->businessId > 0 && $this->personId > 0)
            ? PersonInterest::where('business_id', $this->businessId)->where('person_id', $this->personId)->get()
            : collect();

        return view('x-131::interest-tags', [
            'interests' => $interests,
        ]);
    }
}
