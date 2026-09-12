<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Ui;

use App\Modules\CAgent\Models\AgentRefusal;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RefusalcodeDistributionPer extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $refusals = AgentRefusal::get();

        return view('c-agent::refusalcode-distribution-per', [
            'refusals' => $refusals,
        ]);
    }
}
