<?php

declare(strict_types=1);

namespace App\Modules\X190\Ui;

use App\Modules\X190\Models\PartnerPool;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PoolDepthPer extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $count = ($this->businessId > 0)
            ? PartnerPool::where('business_id', $this->businessId)->count()
            : 0;

        return view('x-190::pool-depth-per', [
            'count' => $count,
        ]);
    }
}
