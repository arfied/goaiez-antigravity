<?php

declare(strict_types=1);

namespace App\Modules\X122\Ui;

use App\Modules\X122\Models\ActionInvocation;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ActionLog extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $invocations = ($this->businessId > 0)
            ? ActionInvocation::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
            : collect();

        return view('x-122::action-log', [
            'invocations' => $invocations,
        ]);
    }
}
