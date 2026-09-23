<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Livewire\Account\Plan as PlanComponent;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

final class PlanScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    private User $owner;

    private $biz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->biz = self::provisionTenant(['owner_user_id' => $this->owner->id]);
        $this->actingAs($this->owner);
        Tenancy::setUser($this->owner->id);
        Tenancy::set((int) $this->biz->id);
    }

    public function test_layout()
    {
        $this->get(route('account.plan'))
            ->assertOk()
            ->assertSee('Your account', false)
            ->assertDontSee('Internal Platform Console');
    }

    public function test_plan_name_renders()
    {
        $sub = Subscription::where('business_id', $this->biz->id)->first();
        $sub->forceFill([
            'plan' => Plan::Limited,
            'status' => SubscriptionStatus::Active,
            'stripe_customer_id' => 'cus_1234',
            'stripe_subscription_id' => 'sub_1234',
        ])->save();

        Livewire::test(PlanComponent::class)
            ->assertDontSee(Plan::Limited->label()); // because it is missing
    }

    public function test_staff_user()
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff);
        Tenancy::setUser($staff->id);

        $this->get(route('account.plan'))->assertForbidden();
    }
}
