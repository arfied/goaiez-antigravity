<?php

declare(strict_types=1);

namespace App\Modules\X129\Ui;

use App\Modules\X129\Models\RedirectMap;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Migration status'])]
class MigrationCard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        return view('x-129::migration-card', [
            'total' => ($this->businessId > 0) ? RedirectMap::where('business_id', $this->businessId)->count() : 0,
            'verified' => ($this->businessId > 0) ? RedirectMap::where('business_id', $this->businessId)->where('is_verified', true)->count() : 0,
        ]);
    }
}
