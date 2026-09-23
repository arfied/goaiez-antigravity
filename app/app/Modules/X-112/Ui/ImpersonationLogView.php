<?php

declare(strict_types=1);

namespace App\Modules\X112\Ui;

use App\Modules\X112\Actions\AgencyImpersonateAction;
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

    public int $agencyId = 0;

    public int $userId = 0;

    public int $targetClientBusinessId = 0;

    public string $reason = '';

    public ?string $success = null;

    public ?string $error = null;

    public function startImpersonation(AgencyImpersonateAction $action): void
    {
        $this->success = null;
        $this->error = null;

        if ($this->agencyId === 0) {
            $this->error = 'Agency ID is required.';

            return;
        }

        if ($this->userId === 0) {
            $this->error = 'User ID is required.';

            return;
        }

        if ($this->targetClientBusinessId === 0) {
            $this->error = 'Target Client Business ID is required.';

            return;
        }

        if (trim($this->reason) === '') {
            $this->error = 'Reason is required.';

            return;
        }

        $log = $action->handle(Tenancy::idOrFail(), $this->agencyId, $this->userId, $this->targetClientBusinessId, $this->reason);

        $this->success = "Started impersonation of client {$log->target_client_business_id} by user {$log->user_id} under agency {$log->agency_id}. Nothing downstream is wired to it yet.";
        $this->agencyId = 0;
        $this->userId = 0;
        $this->targetClientBusinessId = 0;
        $this->reason = '';
    }

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
