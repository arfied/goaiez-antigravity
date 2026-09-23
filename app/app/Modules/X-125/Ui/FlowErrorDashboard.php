<?php

declare(strict_types=1);

namespace App\Modules\X125\Ui;

use App\Modules\X125\Actions\FlowPauseAction;
use App\Modules\X125\Actions\FlowResumeAction;
use App\Modules\X125\Models\Flow;
use App\Modules\X125\Models\FlowRun;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Automation errors and pauses'])]
class FlowErrorDashboard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $success = '';

    public string $error = '';

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function pauseFlow(int $flowId, FlowPauseAction $action): void
    {
        $flow = Flow::where('business_id', Tenancy::idOrFail())->findOrFail($flowId);

        if ($flow->status !== 'active') {
            $this->error = 'Only a running flow can be paused. This one reads '.$flow->status.'.';

            return;
        }

        $action->handle(Tenancy::idOrFail(), $flowId);
        $this->success = 'Paused. '.$flow->name.' will not run until you resume it. Nothing else is notified.';
    }

    public function resumeFlow(int $flowId, FlowResumeAction $action): void
    {
        $flow = Flow::where('business_id', Tenancy::idOrFail())->findOrFail($flowId);

        if ($flow->status !== 'paused' && $flow->status !== 'paused_error') {
            $this->error = 'Only a paused flow can be resumed. This one reads '.$flow->status.'.';

            return;
        }

        $action->handle(Tenancy::idOrFail(), $flowId);
        $this->success = 'Resumed. '.$flow->name.' is active again and its error count is back to zero.';
    }

    public function render()
    {
        $errors = FlowRun::where('business_id', $this->businessId)
            ->where('status', 'error')
            ->with('flow')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $paused = Flow::where('business_id', $this->businessId)
            ->whereIn('status', ['paused_error', 'paused'])
            ->get();

        return view('x-125::flow-error-dashboard', [
            'errors' => $errors,
            'paused' => $paused,
        ]);
    }
}
