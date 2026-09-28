<?php

declare(strict_types=1);

namespace App\Modules\X190\Ui;

use App\Modules\X190\Models\PartnerPool;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Pool depth'])]
class PoolDepthPer extends Component
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
        $count = ($this->businessId > 0)
            ? PartnerPool::where('business_id', $this->businessId)->where('is_declined', false)->count()
            : 0;

        return view('x-190::pool-depth-per', [
            'count' => $count,
        ]);
    }
}
