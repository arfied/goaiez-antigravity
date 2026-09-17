<?php

declare(strict_types=1);

namespace App\Modules\X205\Ui;

use App\Modules\X205\Models\Affiliate;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Affiliates'])]
class AffiliatePortal extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $affiliates = ($this->businessId > 0)
            ? Affiliate::where('business_id', $this->businessId)->orderBy('partner_name')->get()
            : collect();

        return view('x-205::portal', [
            'affiliates' => $affiliates,
        ]);
    }
}
