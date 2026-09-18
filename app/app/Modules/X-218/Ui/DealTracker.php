<?php

declare(strict_types=1);

namespace App\Modules\X218\Ui;

use App\Modules\X218\Models\InfluencerDeal;
use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Support\Tenancy;

#[Layout('components.account.layout', ['heading' => 'Deal tracker'])]
class DealTracker extends Component
{
    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $deals = InfluencerDeal::where('business_id', $businessId)->with('deliverables')->get();

        return view('x-218::deal-tracker', [
            'deals' => $deals,
        ]);
    }
}
