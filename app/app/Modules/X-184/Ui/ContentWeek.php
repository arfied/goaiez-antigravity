<?php

declare(strict_types=1);

namespace App\Modules\X184\Ui;

use App\Modules\X184\Actions\PlanApproveCadenceAction;
use App\Modules\X184\Actions\PlanProposeAction;
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

    public string $weekLabel = '';

    public string $itemSourceEvent = '';

    public string $itemTopicTheme = '';

    public string $itemChannel = 'facebook';

    public string $success = '';

    public string $error = '';

    public bool $failed = false;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function proposePlan(PlanProposeAction $action): void
    {
        $this->success = '';
        $this->error = '';

        if (trim($this->weekLabel) === '') {
            $this->error = 'A week label is required to propose a plan.';

            return;
        }

        if (trim($this->itemSourceEvent) === '') {
            $this->error = 'Every plan item must name its source event so we know what generated it.';

            return;
        }

        $item = [
            'source_event' => trim($this->itemSourceEvent),
        ];

        if (trim($this->itemTopicTheme) !== '') {
            $item['topic_theme'] = trim($this->itemTopicTheme);
        }

        if (trim($this->itemChannel) !== '') {
            $item['channel'] = trim($this->itemChannel);
        }

        $plan = $action->proposePlan(
            businessId: Tenancy::idOrFail(),
            weekLabel: trim($this->weekLabel),
            postsCadence: 3,
            items: [$item]
        );

        $this->success = 'Added 1 item to the plan for '.$plan->week_label.'. For a Facebook or Instagram item, "Write this post" opens the Social queue with its topic filled in.';

        $this->weekLabel = '';
        $this->itemSourceEvent = '';
        $this->itemTopicTheme = '';
        $this->itemChannel = 'facebook';
    }

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
