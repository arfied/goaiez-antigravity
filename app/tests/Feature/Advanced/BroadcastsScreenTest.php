<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\User;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class BroadcastsScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function createTenant(bool $advanced = false): array
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $business = Business::provision([
            'owner_user_id' => $user->id,
            'name' => 'Acme Dental '.rand(100, 999),
        ]);

        if ($advanced) {
            $business->update(['advanced_dashboard_enabled' => true]);
        }

        Tenancy::setUser((int) $user->id);
        Tenancy::set((int) $business->id);

        return [$user, $business];
    }

    public function test_real_get_assert_ok_and_shell_mark(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.broadcasts'));
        $response->assertOk();
        $response->assertSee('Skip to content');
        $response->assertDontSee('Internal Platform Console');
    }

    public function test_empty_state_renders_correctly(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.broadcasts'));
        $response->assertOk();
        $response->assertSee('No broadcasts yet. Your first campaign appears here after it is drafted.');
    }

    public function test_one_broadcast_row_of_this_tenant_renders_its_distinctive_name(): void
    {
        [$userA, $bizA] = $this->createTenant(advanced: true);
        Campaign::factory()->create([
            'business_id' => $bizA->id,
            'name' => 'Distinctive Broadcast 7719',
        ]);

        [$userB, $bizB] = $this->createTenant(advanced: true);
        Campaign::factory()->create([
            'business_id' => $bizB->id,
            'name' => 'Distinctive Broadcast 7720',
        ]);

        Tenancy::setUser((int) $userA->id);
        Tenancy::set((int) $bizA->id);

        $response = $this->actingAs($userA)->get(route('advanced.broadcasts'));
        $response->assertOk();
        $response->assertSee('Distinctive Broadcast 7719');
        $response->assertDontSee('Distinctive Broadcast 7720');
    }
}
