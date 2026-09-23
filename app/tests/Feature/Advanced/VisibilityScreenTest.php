<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\User;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class VisibilityScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function createTenant(bool $advanced = false): array
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant([
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
        $response = $this->actingAs($user)->get(route('advanced.visibility'));
        $response->assertOk();
        $response->assertSee('Skip to content');
    }

    public function test_empty_state_renders_correctly(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        Location::where('business_id', $business->id)->delete();
        $response = $this->actingAs($user)->get(route('advanced.visibility'));
        $response->assertSee('Add a location to measure its visibility.');
    }

    public function test_an_unmeasured_location_shows_the_reports_honest_sentence(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);

        $location = Location::factory()->create(['business_id' => $business->id, 'name' => 'Distinctive Location 7719']);

        $response = $this->actingAs($user)->get(route('advanced.visibility'));
        $response->assertSee('Distinctive Location 7719');
        $response->assertSee('Connect Google Search Console under Google reviews to see search movement.');
    }

    public function test_another_tenants_location_is_not_listed(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        Location::where('business_id', $business->id)->delete();
        $location = Location::factory()->create(['business_id' => $business->id, 'name' => 'Tenant A Location']);

        [$userB, $businessB] = $this->createTenant(advanced: true);
        Location::where('business_id', $businessB->id)->delete();
        $locationB = Location::factory()->create(['business_id' => $businessB->id, 'name' => 'Tenant B Location']);

        Tenancy::setUser((int) $userB->id);
        Tenancy::set((int) $businessB->id);

        $response = $this->actingAs($userB)->get(route('advanced.visibility'));
        $response->assertSee('Tenant B Location');
        $response->assertDontSee('Tenant A Location');
    }
}
