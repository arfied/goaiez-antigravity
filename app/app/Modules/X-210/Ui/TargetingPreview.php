<?php

declare(strict_types=1);

namespace App\Modules\X210\Ui;

use App\Modules\X210\Models\PromotionScope;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Promotion targeting'])]
class TargetingPreview extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId ?: Tenancy::id() ?? 0;
        abort_if($this->businessId === 0, 404);
    }

    public function render()
    {
        $scopes = ($this->businessId > 0)
            ? PromotionScope::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-210::targeting-preview', [
            'scopes' => $scopes,
        ]);
    }
}
