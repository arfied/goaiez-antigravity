<?php

declare(strict_types=1);

namespace App\Modules\X218\Ui;

use App\Modules\X218\Models\InfluencerProfile;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Discovery board'])]
class DiscoveryBoard extends Component
{
    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $influencers = InfluencerProfile::where('business_id', $businessId)->get();

        return view('x-218::discovery-board', [
            'influencers' => $influencers,
        ]);
    }
}
