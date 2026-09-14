<?php

declare(strict_types=1);

namespace App\Modules\X132\Ui;

use App\Enums\UserRole;
use App\Modules\X132\Models\ResolutionEvidence;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Person timeline'])]
class PersonTimelineView extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    public int $personId = 0;

    public function mount()
    {
        abort_unless(auth()->check() && (auth()->user()->hasRole(UserRole::Owner, UserRole::Manager)), 403);
        $this->businessId = Tenancy::id();
    }

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
