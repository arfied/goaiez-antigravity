<?php

declare(strict_types=1);

namespace App\Modules\X10\Ui;

use App\Modules\X10\Enums\RoutingRuleType;
use App\Modules\X10\Models\RoutingRule;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Lead routing rules'])]
class RoutingRules extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function toggle(string $ruleType): void
    {
        $businessId = Tenancy::idOrFail();
        $rule = RoutingRule::where('business_id', $businessId)->where('rule_type', $ruleType)->first();
        if ($rule) {
            $rule->update(['is_active' => ! $rule->is_active]);
        }
    }

    public function moveUp(string $ruleType): void
    {
        $this->swapPriority($ruleType, -1);
    }

    public function moveDown(string $ruleType): void
    {
        $this->swapPriority($ruleType, 1);
    }

    private function swapPriority(string $ruleType, int $direction): void
    {
        $businessId = Tenancy::idOrFail();
        $rules = RoutingRule::where('business_id', $businessId)->orderBy('priority')->get();
        $index = $rules->search(fn ($r) => $r->rule_type->value === $ruleType);

        if ($index !== false && isset($rules[$index + $direction])) {
            $current = $rules[$index];
            $neighbor = $rules[$index + $direction];

            $currentPriority = $current->priority;
            $current->update(['priority' => $neighbor->priority]);
            $neighbor->update(['priority' => $currentPriority]);
        }
    }

    public function setDefaultStaff(int $userId): void
    {
        $businessId = Tenancy::idOrFail();
        $rule = RoutingRule::where('business_id', $businessId)
            ->where('rule_type', RoutingRuleType::DEFAULT_STAFF)
            ->first();

        if ($rule) {
            $rule->update(['settings' => ['staff_id' => $userId]]);
        }
    }

    public function render()
    {
        $rules = ($this->businessId > 0)
            ? RoutingRule::where('business_id', $this->businessId)->orderBy('priority')->get()
            : collect();

        $staffUsers = ($this->businessId > 0)
            ? \Illuminate\Support\Facades\DB::table('staff_users')->where('business_id', $this->businessId)->get()
            : collect();

        return view('x-10::routing-rules', [
            'rules' => $rules,
            'staffUsers' => $staffUsers,
        ]);
    }
}
