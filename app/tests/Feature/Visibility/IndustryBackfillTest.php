<?php

declare(strict_types=1);

namespace Tests\Feature\Visibility;

use App\Enums\UserRole;
use App\Jobs\Visibility\SyncCompetitorSignalsJob;
use App\Models\Location;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class IndustryBackfillTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_backfills_industry_if_null(): void
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
                'primaryType' => 'car_repair',
                'types' => ['car_repair'],
            ]),
        ]);

        config(['credentials.google_places_key' => 'test-key']);

        $userA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = TestCase::provisionTenant(['owner_user_id' => $userA->id]);
        Tenancy::setUser((int) $userA->id);
        Tenancy::set((int) $bizA->id);
        $bizA->update(['advanced_dashboard_enabled' => true, 'industry' => null]);
        $locationA = Location::factory()->create(['business_id' => $bizA->id, 'google_place_id' => 'self-place-1', 'name' => 'Loc A', 'current_rating' => 4.5]);
        $jobA = new SyncCompetitorSignalsJob((int) $bizA->id, (int) $locationA->id);
        $jobA->handle();

        $this->assertEquals('auto', $bizA->fresh()->industry->value);

        // Run again with pre-set industry
        $bizA->update(['industry' => 'trades']);
        $jobA->handle();
        $this->assertEquals('trades', $bizA->fresh()->industry->value);
    }
}
