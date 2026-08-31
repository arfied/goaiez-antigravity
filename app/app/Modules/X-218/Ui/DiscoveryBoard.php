<?php

declare(strict_types=1);

namespace App\Modules\X218\Ui;

use App\Modules\X218\Models\InfluencerProfile;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DiscoveryBoard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $influencers = ($this->businessId > 0)
            ? InfluencerProfile::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-218::discovery-board', [
            'influencers' => $influencers,
        ]);
    }
}
