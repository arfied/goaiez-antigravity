<?php

declare(strict_types=1);

namespace App\Modules\X165\Ui;

use App\Enums\UserRole;
use App\Modules\X165\Actions\MembershipRenewAction;
use App\Modules\X165\Actions\RenewalReminderAction;
use App\Modules\X165\Models\Membership;
use App\Modules\X165\Models\MembershipPlan;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your members'])]
class Members extends Component
{
    #[Locked]
    public int $businessId;

    public function mount()
    {
        abort_unless(auth()->check() && (auth()->user()->hasRole(UserRole::Owner, UserRole::Manager)), 403);
        $this->businessId = Tenancy::id();
    }

    public function renew(int $membershipId)
    {
        app(MembershipRenewAction::class)->handle($this->businessId, $membershipId);
    }

    public function remind(int $membershipId)
    {
        app(RenewalReminderAction::class)->sendReminderIfDue($this->businessId, $membershipId, now());
    }

    public function render()
    {
        $memberships = Membership::where('business_id', $this->businessId)->get();
        $planNames = MembershipPlan::whereIn('id', $memberships->pluck('plan_id')->unique())->get()->keyBy('id')->map->name;

        return view('x-165::members', [
            'memberships' => $memberships,
            'planNames' => $planNames,
        ]);
    }
}
