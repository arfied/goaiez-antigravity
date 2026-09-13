<?php

declare(strict_types=1);

namespace Tests\Modules\X165;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X163\Models\PriceBookItem;
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
        $item = $this->pricebookItem($biz->id, 'Platinum Service', 29900, true);

        Livewire::actingAs($owner)
            ->test(Plans::class)
            ->set('newName', 'Platinum Plan')
            ->set('newItemId', (string) $item->id)
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

    public function test_seeded_row_reaches_the_page(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        MembershipPlan::create([
            'business_id' => $biz->id,
            'name' => 'Platinum Plan',
            'price_cents' => 55555,
            'billing_interval_months' => 12,
            'renewal_reminder_days' => 7,
        ]);

        $this->actingAs($owner)->get(route('x-165.plans'))->assertOk()->assertSee('555.55');
    }

    public function test_staff_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(Plans::class)->assertForbidden();
    }

    public function test_manager_is_admitted(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Manager;
        $user->save();
        TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(Plans::class)->assertOk();
    }

    /**
     * [N-165-01]
     */
    public function test_a_refused_pricebook_row_shows_the_sentence(): void
    {
        $owner = User::factory()->create();
        $owner->role = UserRole::Owner;
        $owner->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);
        $item = $this->pricebookItem($biz->id, 'Brake Pads', 8000, false);

        Livewire::actingAs($owner)
            ->test(Plans::class)
            ->set('newName', 'Brake Plan')
            ->set('newItemId', (string) $item->id)
            ->call('proposePlan')
            ->assertSee('Choose a confirmed price from your pricebook.');
    }

    public function test_the_price_list_offers_a_confirmed_pricebook_row(): void
    {
        $owner = User::factory()->create();
        $owner->role = UserRole::Owner;
        $owner->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);
        $this->pricebookItem($biz->id, 'Annual Tune-Up', 4999, true);

        Livewire::actingAs($owner)
            ->test(Plans::class)
            ->assertSee('Annual Tune-Up ($49.99)');
    }

    public function test_the_typed_price_box_is_gone(): void
    {
        $owner = User::factory()->create();
        $owner->role = UserRole::Owner;
        $owner->save();
        TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(Plans::class)
            ->assertDontSeeHtml('wire:model="newPrice"');
    }

    private function pricebookItem(int $businessId, string $name, int $cents, bool $confirmed): PriceBookItem
    {
        return PriceBookItem::create([
            'business_id' => $businessId,
            'service_name' => $name,
            'price_cents' => $cents,
            'is_sample' => false,
            'is_confirmed' => $confirmed,
        ]);
    }
}
