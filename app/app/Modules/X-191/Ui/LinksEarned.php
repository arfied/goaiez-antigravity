<?php

declare(strict_types=1);

namespace App\Modules\X191\Ui;

use App\Modules\X191\Models\LinkPlacement;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Earned links'])]
class LinksEarned extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $placements = ($this->businessId > 0)
            ? LinkPlacement::where('business_id', $this->businessId)->where('is_active', true)->orderByDesc('id')->get()
            : collect();

        return view('x-191::links-earned', [
            'placements' => $placements,
        ]);
    }
}
