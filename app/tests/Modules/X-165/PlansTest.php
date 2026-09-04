<?php

declare(strict_types=1);

namespace Tests\Modules\X165;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X165\Models\MembershipPlan;
use App\Modules\X165\Ui\Plans;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PlansTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(Plans::class)->assertForbidden();
    }

    public function test_fresh_tenant_sees_empty_sentence(): void
    {
        $owner = User::factory()->create();
        $owner->role = UserRole::Owner;
        $owner->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(Plans::class)
            ->assertOk()
            ->assertSee('No plans yet. Propose one from the jobs you already do.');
    }

    public function test_seeded_rows_displays_plan_and_sample_pill(): void
    {
        $owner = User::factory()->create();
        $owner->role = UserRole::Owner;
        $owner->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        MembershipPlan::create([
            'business_id' => $biz->id,
            'name' => 'Gold Plan',
            'price_cents' => 19900,
            'billing_interval_months' => 12,
            'renewal_reminder_days' => 7,
            'is_sample' => true,
        ]);

        Livewire::actingAs($owner)
            ->test(Plans::class)
            ->assertOk()
            ->assertSee('Gold Plan')
            ->assertSee('$199.00')
            ->assertSeeHtml('<span aria-hidden="true">▲</span>
    <span>Sample</span>', false);
    }

    public function test_propose_plan_action(): void
    {
        $owner = User::factory()->create();
        $owner->role = UserRole::Owner;
        $owner->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(Plans::class)
            ->set('newName', 'Platinum Plan')
            ->set('newPrice', '299.00')
            ->call('proposePlan');

        $this->assertDatabaseHas('membership_plans', [
            'business_id' => $biz->id,
            'name' => 'Platinum Plan',
            'price_cents' => 29900,
        ]);
    }

    public function test_start_membership_action(): void
    {
        $owner = User::factory()->create();
        $owner->role = UserRole::Owner;
        $owner->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $plan = MembershipPlan::create([
            'business_id' => $biz->id,
            'name' => 'Gold Plan',
            'price_cents' => 19900,
            'billing_interval_months' => 12,
            'renewal_reminder_days' => 7,
        ]);

        $personId = DB::table('people')->insertGetId([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($owner)
            ->test(Plans::class)
            ->set('personInput.'.$plan->id, (string) $personId)
            ->call('startMembership', $plan->id);

        $this->assertDatabaseHas('memberships', [
            'business_id' => $biz->id,
            'plan_id' => $plan->id,
            'person_id' => $personId,
        ]);
    }

    public function test_start_membership_empty_input_shows_error(): void
    {
        $owner = User::factory()->create();
        $owner->role = UserRole::Owner;
        $owner->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $plan = MembershipPlan::create([
            'business_id' => $biz->id,
            'name' => 'Gold Plan',
            'price_cents' => 19900,
            'billing_interval_months' => 12,
            'renewal_reminder_days' => 7,
        ]);

        Livewire::actingAs($owner)
            ->test(Plans::class)
            ->call('startMembership', $plan->id)
            ->assertHasErrors(['personInput.'.$plan->id => 'Enter the customer first.']);
    }
}
