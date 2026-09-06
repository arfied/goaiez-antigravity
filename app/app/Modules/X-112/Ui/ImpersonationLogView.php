<?php

declare(strict_types=1);

namespace App\Modules\X112\Ui;

use App\Modules\X112\Models\ImpersonationLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.agency')]
class ImpersonationLogView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $logs = ($this->businessId > 0)
            ? ImpersonationLog::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-112::impersonation-log', [
            'logs' => $logs,
        ]);
    }
}
