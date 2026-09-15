<?php

declare(strict_types=1);

namespace App\Modules\X132\Ui;

use App\Enums\UserRole;
use App\Modules\X132\Models\PersonLink;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Resolution rate & confidence'])]
class ResolutionRateConfidenceView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount()
    {
        abort_unless(auth()->check() && (auth()->user()->hasRole(UserRole::Owner, UserRole::Manager)), 403);
        $this->businessId = Tenancy::id();
    }

    public function render()
    {
        $links = ($this->businessId > 0)
            ? PersonLink::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-132::resolution-rate-confidence', [
            'links' => $links,
        ]);
    }
}
