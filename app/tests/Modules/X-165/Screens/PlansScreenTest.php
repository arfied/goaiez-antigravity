<?php

declare(strict_types=1);

namespace Tests\Modules\X165\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X165\Models\MembershipPlan;
use App\Modules\X165\Ui\Plans;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PlansScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);
        $this->actingAs($owner);

        $this->get(route('x-165.plans'))
            ->assertOk()
            ->assertSee('No plans yet. Propose one from the jobs you already do.');

        $plan = MembershipPlan::create([
            'business_id' => $biz->id,
            'name' => 'Premium Service Plan',
            'price_cents' => 49900,
            'billing_interval_months' => 12,
            'renewal_reminder_days' => 14,
        ]);

        $this->get(route('x-165.plans'))
            ->assertOk()
            ->assertSee('Premium Service Plan');

        Livewire::test(Plans::class)->assertOk();
    }
}
