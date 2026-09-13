<?php

declare(strict_types=1);

namespace App\Modules\X184\Ui;

use App\Modules\X184\Actions\PlanApproveCadenceAction;
use App\Modules\X184\Actions\PlanScheduleAction;
use App\Modules\X184\Models\ContentPlan;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your content week'])]
class ContentWeek extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public bool $failed = false;

    public function approveCadence(int $planId, PlanApproveCadenceAction $action): void
    {
        try {
            $action->approveCadence($this->businessId, $planId);
        } catch (\Throwable $e) {
            $this->failed = true;
        }
    }

    public function scheduleItem(int $itemId, PlanScheduleAction $action): void
    {
        try {
            $action->scheduleItem($this->businessId, $itemId);
        } catch (\Throwable $e) {
            $this->failed = true;
        }
    }

    public function render()
    {
        $plans = ($this->businessId > 0 && ! $this->failed)
            ? ContentPlan::where('business_id', $this->businessId)
                ->with(['items' => fn ($q) => $q->orderBy('scheduled_date')])
                ->get()
            : collect();

        return view('x-184::content-week', [
            'plans' => $plans,
        ]);
    }
}
