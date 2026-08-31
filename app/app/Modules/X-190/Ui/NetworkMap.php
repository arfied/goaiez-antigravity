<?php

declare(strict_types=1);

namespace App\Modules\X190\Ui;

use App\Modules\X190\Models\PartnerPool;
use Livewire\Attributes\Locked;
use Livewire\Component;

class NetworkMap extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $partners = ($this->businessId > 0)
            ? PartnerPool::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-190::network-map', [
            'partners' => $partners,
        ]);
    }
}
