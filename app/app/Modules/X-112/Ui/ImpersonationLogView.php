<?php

declare(strict_types=1);

namespace App\Modules\X112\Ui;

use App\Modules\X112\Actions\GetImpersonationLogsAction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.agency')]
class ImpersonationLogView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render(GetImpersonationLogsAction $action)
    {
        $logs = ($this->businessId > 0)
            ? $action->handle($this->businessId)
            : collect();

        return view('x-112::impersonation-log', [
            'logs' => $logs,
        ]);
    }
}
