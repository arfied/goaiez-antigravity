<?php

declare(strict_types=1);

namespace App\Modules\X132\Ui;

use App\Modules\X132\Models\ResolutionEvidence;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PersonTimelineView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public int $personId = 0;

    public function render()
    {
        $evidence = ($this->businessId > 0 && $this->personId > 0)
            ? ResolutionEvidence::where('business_id', $this->businessId)->where('canonical_person_id', $this->personId)->get()
            : collect();

        return view('x-132::person-timeline', [
            'evidence' => $evidence,
        ]);
    }
}
