<?php

declare(strict_types=1);

namespace App\Modules\X137\Ui;

use App\Modules\X137\Models\CallToken;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Call attribution'])]
class AttributionRow extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $tokens = ($this->businessId > 0)
            ? CallToken::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-137::attribution-row', [
            'tokens' => $tokens,
        ]);
    }
}
