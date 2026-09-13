<?php

declare(strict_types=1);

namespace Tests\Modules\X165\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X165\Models\Membership;
use App\Modules\X165\Models\MembershipPlan;
use App\Modules\X165\Ui\Members;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class MembersScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);
        $this->actingAs($owner);

        $this->get(route('x-165.members'))
            ->assertOk()
            ->assertSee('No members yet. Start one from a plan.');

        $plan = MembershipPlan::create([
            'business_id' => $biz->id,
            'name' => 'Gold Plan',
            'price_cents' => 19900,
            'billing_interval_months' => 12,
            'renewal_reminder_days' => 7,
        ]);

        Membership::create([
            'business_id' => $biz->id,
            'plan_id' => $plan->id,
            'person_id' => 1,
            'status' => 'active',
            'starts_at' => now(),
            'renews_at' => now()->addMonths(12),
        ]);

        $this->get(route('x-165.members'))
            ->assertOk()
            ->assertSee('Gold Plan');

        Livewire::test(Members::class)->assertOk();
    }
}
