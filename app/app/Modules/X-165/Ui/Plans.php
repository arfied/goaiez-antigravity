<?php

declare(strict_types=1);

namespace App\Modules\X165\Ui;

use App\Enums\UserRole;
use App\Modules\X163\Actions\QuotablePriceAction;
use App\Modules\X165\Actions\MembershipStartAction;
use App\Modules\X165\Actions\PlanProposeAction;
use App\Modules\X165\Models\Membership;
use App\Modules\X165\Models\MembershipPlan;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your plans'])]
class Plans extends Component
{
    #[Locked]
    public int $businessId;

    public string $newName = '';

    public string $newItemId = '';

    public array $personInput = [];

    public function mount()
    {
        abort_unless(auth()->check() && (auth()->user()->hasRole(UserRole::Owner, UserRole::Manager)), 403);
        $this->businessId = Tenancy::id();
    }

    public function proposePlan()
    {
        if (empty($this->newName)) {
            return;
        }

        $price = app(QuotablePriceAction::class)->resolve($this->businessId, (int) $this->newItemId);

        if (! isset($price['amount'])) {
            $this->addError('newItemId', 'Choose a confirmed price from your pricebook.');

            return;
        }

        app(PlanProposeAction::class)->handle(
            $this->businessId,
            $this->newName,
            $price['amount']
        );

        $this->newName = '';
        $this->newItemId = '';
    }

    public function startMembership(int $planId)
    {
        $personId = $this->personInput[$planId] ?? '';
        if (empty($personId)) {
            $this->addError('personInput.'.$planId, 'Enter the customer first.');

            return;
        }

        app(MembershipStartAction::class)->handle(
            $this->businessId,
            $planId,
            (int) $personId
        );

        $this->personInput[$planId] = '';
    }

    public function render()
    {
        $plans = MembershipPlan::where('business_id', $this->businessId)->get()->map(function ($plan) {
            $plan->formatted_price = '$'.number_format($plan->price_cents / 100, 2);
            $plan->member_count = Membership::where('plan_id', $plan->id)->count();

            return $plan;
        });

        return view('x-165::plans', [
            'plans' => $plans,
            'priceOptions' => app(QuotablePriceAction::class)->options($this->businessId),
        ]);
    }
}
