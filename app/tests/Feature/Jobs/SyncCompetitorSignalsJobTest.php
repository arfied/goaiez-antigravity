<?php

use App\Enums\UserRole;
use App\Jobs\Visibility\SyncCompetitorSignalsJob;
use App\Models\Competitor;
use App\Models\Location;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

it('tests SyncCompetitorSignalsJob writes one signal row', function () {
    Http::fake([
        'places.googleapis.com/v1/places:searchNearby' => Http::response([
            'places' => [
                [
                    'id' => 'place-123',
                    'displayName' => ['text' => 'Competitor Shop'],
                    'location' => ['latitude' => 34.0, 'longitude' => -118.0],
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

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    // Need places api key
    config(['credentials.google_places_key' => 'test-key']);

    $location = Location::factory()->create(['business_id' => $biz->id, 'google_place_id' => 'self-place-1']);

    $job = new SyncCompetitorSignalsJob((int) $biz->id, (int) $location->id);
    $job->handle();

    $count = Competitor::where('location_id', $location->id)->count();
    expect($count)->toBe(1);
});

it('tests SyncCompetitorSignalsJob no competitors means no HTTP', function () {
    Http::fake();

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    // No google_place_id -> should not fire HTTP request
    $location = Location::factory()->create(['business_id' => $biz->id, 'google_place_id' => null]);

    $job = new SyncCompetitorSignalsJob((int) $biz->id, (int) $location->id);
    $job->handle();

    Http::assertNothingSent();
});

it('tests SyncCompetitorSignalsJob is dispatched', function () {
    Queue::fake();

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    $location = Location::factory()->create(['business_id' => $biz->id, 'google_place_id' => 'self-place-1']);

    $this->artisan('visibility:sync-competitors')->assertSuccessful();

    Queue::assertPushed(SyncCompetitorSignalsJob::class);
});
