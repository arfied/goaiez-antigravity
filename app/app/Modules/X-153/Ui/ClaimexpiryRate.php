<?php

declare(strict_types=1);

namespace App\Modules\X153\Ui;

use App\Modules\X153\Models\AlertClaim;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Claim expiry'])]
class ClaimexpiryRate extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $claims = collect();
        $active = 0;
        $expired = 0;
        $rate = 0;

        if ($this->businessId > 0) {
            $claims = AlertClaim::where('business_id', $this->businessId)
                ->orderByDesc('claimed_at')
                ->limit(50)
                ->get();

            $active = AlertClaim::where('business_id', $this->businessId)->where('status', 'active')->count();
            $expired = AlertClaim::where('business_id', $this->businessId)->where('status', 'expired')->count();
            $total = $active + $expired;
            
            if ($total > 0) {
                $rate = (int) round(($expired / $total) * 100);
            }
        }

        return view('x-153::claimexpiry-rate', [
            'claims' => $claims,
            'active' => $active,
            'expired' => $expired,
            'rate' => $rate,
        ]);
    }
}
