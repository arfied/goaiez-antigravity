<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Ui;

use App\Modules\CAgent\Models\AgentTurn;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Thread extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $turns = ($this->businessId > 0)
            ? AgentTurn::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
            : collect();

        return view('c-agent::thread', [
            'turns' => $turns,
        ]);
    }
}
