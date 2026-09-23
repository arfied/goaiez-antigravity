<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\GrowthPage;
use App\Models\Location;
use App\Models\User;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class HomeScreenTest extends TestCase
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

    public function test_get_and_shell_mark(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        Location::factory()->create(['business_id' => $business->id]);
        $response = $this->actingAs($user)->get(route('advanced.home'));
        $response->assertOk();
        $response->assertSee('Skip to content');
        $response->assertDontSee('Internal Platform Console');
    }

    public function test_a_fresh_tenant_shows_zero_counts_and_no_fiction(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        Location::factory()->create(['business_id' => $business->id]);
        $response = $this->actingAs($user)->get(route('advanced.home'));
        $response->assertOk();
        $response->assertSee('Published pages');
        $response->assertSee('Calls, last 30 days');
        $response->assertSee('Broadcasts');
        $response->assertDontSee('89%');
        $response->assertDontSee('#1.6');
        $response->assertDontSee('dominance');
        $response->assertDontSee('catchment');
    }

    public function test_counts_follow_this_tenants_rows(): void
    {
        [$userA, $bizA] = $this->createTenant(advanced: true);
        $locationA = Location::factory()->create(['business_id' => $bizA->id]);

        Campaign::factory()->create(['business_id' => $bizA->id]);
        GrowthPage::factory()->create(['business_id' => $bizA->id, 'location_id' => $locationA->id]);
        Call::factory()->create(['business_id' => $bizA->id, 'started_at' => now()]);

        [$ownerB, $bizB] = $this->createTenant(advanced: true);
        $locationB = Location::factory()->create(['business_id' => $bizB->id]);

        Tenancy::setUser((int) $ownerB->id);
        Tenancy::set((int) $bizB->id);

        Campaign::factory()->create(['business_id' => $bizB->id]);
        GrowthPage::factory()->create(['business_id' => $bizB->id, 'location_id' => $locationB->id]);
        Call::factory()->create(['business_id' => $bizB->id, 'started_at' => now()]);

        Tenancy::setUser((int) $userA->id);
        Tenancy::set((int) $bizA->id);

        $response = $this->actingAs($userA)->get(route('advanced.home'));
        $response->assertOk();
        $response->assertSeeInOrder(['Published pages', '1']);
        $response->assertSeeInOrder(['Calls, last 30 days', '1']);
        $response->assertSeeInOrder(['Broadcasts', '1']);
    }
}
