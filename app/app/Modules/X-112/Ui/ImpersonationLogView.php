<?php

declare(strict_types=1);

namespace App\Modules\X112\Ui;

use App\Modules\X112\Actions\GetImpersonationLogsAction;
use App\Support\Tenancy;
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
        $id = $this->businessId;
        if ($id === 0) {
            abort_unless(auth()->check() && Tenancy::check(), 403);
            $id = Tenancy::idOrFail();
        }

        $logs = ($id > 0)
            ? $action->handle($id)
            : collect();

        return view('x-112::impersonation-log', [
            'logs' => $logs,
        ]);
    }
}
