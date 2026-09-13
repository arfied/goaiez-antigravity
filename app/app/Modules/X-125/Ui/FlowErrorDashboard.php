<?php

declare(strict_types=1);

namespace App\Modules\X125\Ui;

use App\Modules\X125\Models\Flow;
use App\Modules\X125\Models\FlowRun;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Automation errors'])]
class FlowErrorDashboard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
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
            ->where('status', 'paused_error')
            ->get();

        return view('x-125::flow-error-dashboard', [
            'errors' => $errors,
            'paused' => $paused,
        ]);
    }
}
