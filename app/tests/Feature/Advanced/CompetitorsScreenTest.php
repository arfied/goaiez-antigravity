<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\UserRole;
use App\Jobs\Visibility\SyncCompetitorSignalsJob;
use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class CompetitorsScreenTest extends TestCase
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
        $response = $this->actingAs($user)->get(route('advanced.competitors'));
        $response->assertOk();
        $response->assertSee('Skip to content');
    }

    public function test_empty_state_renders_correctly(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.competitors'));
        $response->assertOk();
        $response->assertSee('Add a location to compare against nearby competitors.');
    }

    public function test_one_competitor_of_this_tenant_renders_and_another_tenants_does_not(): void
    {
        Http::fake([
            'places.googleapis.com/v1/places:searchNearby' => Http::response([
                'places' => [
                    [
                        'id' => 'place-123',
                        'displayName' => ['text' => 'Competitor Shop'],
                        'location' => ['latitude' => 34.0, 'longitude' => -118.0],
                        'rating' => 4.8,
                    ],
                ],
            ]),
            'places.googleapis.com/v1/places/*' => Http::response([
                'id' => 'self-place-1',
                'displayName' => ['text' => 'My Shop'],
                'location' => ['latitude' => 34.0, 'longitude' => -118.0],
                'primaryType' => 'store',
            ]),
        ]);

        config(['credentials.google_places_key' => 'test-key']);

        [$userA, $bizA] = $this->createTenant(advanced: true);
        $locationA = Location::factory()->create(['business_id' => $bizA->id, 'google_place_id' => 'self-place-1', 'name' => 'Loc A', 'current_rating' => 4.5]);
        $jobA = new SyncCompetitorSignalsJob((int) $bizA->id, (int) $locationA->id);
        $jobA->handle();

        [$userB, $bizB] = $this->createTenant(advanced: true);
        $locationB = Location::factory()->create(['business_id' => $bizB->id, 'google_place_id' => 'self-place-2', 'name' => 'Loc B', 'current_rating' => 4.2]);
        $jobB = new SyncCompetitorSignalsJob((int) $bizB->id, (int) $locationB->id);
        $jobB->handle();

        Tenancy::setUser((int) $userA->id);
        Tenancy::set((int) $bizA->id);

        $response = $this->actingAs($userA)->get(route('advanced.competitors'));
        $response->assertOk();
        $response->assertSee('Loc A');
        $response->assertSee('Businesses like yours nearby average 4.8★. You are at 4.5★.');

        $response->assertDontSee('Loc B');
        $response->assertDontSee('Businesses like yours nearby average 4.2★');
    }
}
