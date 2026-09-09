<?php

declare(strict_types=1);

namespace Tests\Modules\X165;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X165\Models\Membership;
use App\Modules\X165\Models\MembershipPlan;
use App\Modules\X165\Ui\Members;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class MembersTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(Members::class)->assertForbidden();
    }

    public function test_fresh_tenant_sees_empty_sentence(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(Members::class)
            ->assertOk()
            ->assertSee('No members yet. Start one from a plan.');
    }

    public function test_seeded_rows_displays_plan_and_sample_pill(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

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
            'is_sample' => true,
        ]);

        Livewire::actingAs($owner)
            ->test(Members::class)
            ->assertOk()
            ->assertSee('Gold Plan')
            ->assertSeeHtml('<span aria-hidden="true">▲</span>
    <span>Sample</span>', false);
    }

    public function test_renew_action(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $plan = MembershipPlan::create([
            'business_id' => $biz->id,
            'name' => 'Gold Plan',
            'price_cents' => 19900,
            'billing_interval_months' => 12,
            'renewal_reminder_days' => 7,
        ]);

        $initialRenewsAt = Carbon::now()->startOfDay()->addMonths(6);

        $membership = Membership::create([
            'business_id' => $biz->id,
            'plan_id' => $plan->id,
            'person_id' => 1,
            'status' => 'active',
            'starts_at' => now(),
            'renews_at' => $initialRenewsAt,
        ]);

        Livewire::actingAs($owner)
            ->test(Members::class)
            ->call('renew', $membership->id);

        $membership->refresh();
        $this->assertEquals('active', $membership->status);
        $this->assertEquals($initialRenewsAt->copy()->addMonths(12)->format('Y-m-d H:i:s'), $membership->renews_at->format('Y-m-d H:i:s'));
    }

    public function test_remind_action(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $plan = MembershipPlan::create([
            'business_id' => $biz->id,
            'name' => 'Gold Plan',
            'price_cents' => 19900,
            'billing_interval_months' => 12,
            'renewal_reminder_days' => 7,
        ]);

        $initialRenewsAt = Carbon::now()->addDays(3);

        $membership = Membership::create([
            'business_id' => $biz->id,
            'plan_id' => $plan->id,
            'person_id' => 1,
            'status' => 'active',
            'starts_at' => now()->subMonths(1),
            'renews_at' => $initialRenewsAt,
        ]);

        Livewire::actingAs($owner)
            ->test(Members::class)
            ->call('remind', $membership->id);

        $membership->refresh();
        $this->assertNotNull($membership->renewal_reminder_sent_at);
    }

    public function test_seeded_row_reaches_the_page(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $plan = MembershipPlan::create([
            'business_id' => $biz->id,
            'name' => 'Gold Plan 444.44',
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

        $this->actingAs($owner)->get(route('x-165.members'))->assertOk()->assertSee('444.44');
    }

    public function test_staff_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(Members::class)->assertForbidden();
    }
}
