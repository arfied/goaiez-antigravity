<?php

use App\Enums\UserRole;
use App\Jobs\Visibility\SyncCompetitorSignalsJob;
use App\Models\CompetitorSiteNote;
use App\Models\Location;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

it('keeps a note of a peer site — title, description, headings — and nothing else', function () {
    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
        'places.googleapis.com/v1/places:searchNearby' => Http::response([
            'places' => [
                ['id' => 'place-4471', 'displayName' => ['text' => 'Distinctive Peer 4471'], 'rating' => 4.6, 'userRatingCount' => 88, 'websiteUri' => 'https://peer-4471.example/'],
                ['id' => 'place-4472', 'displayName' => ['text' => 'Distinctive Peer 4472'], 'rating' => 4.1],
            ],
        ]),
        'places.googleapis.com/v1/places/*' => Http::response([
            'id' => 'self-place-1',
            'displayName' => ['text' => 'My Shop'],
            'location' => ['latitude' => 34.0, 'longitude' => -118.0],
            'primaryType' => 'store',
        ]),
        'https://peer-4471.example/' => Http::response('<html><head><title>Distinctive Peer Title 4471</title><meta name="description" content="Distinctive peer description 4472"></head><body><h1>Distinctive heading 4473</h1><p>Distinctive body sentence 4474 that must never be stored.</p><h2>Distinctive heading 4475</h2><img src="/distinctive-image-4476.png"></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);
    config(['credentials.google_places_key' => 'test-key']);

    $location = Location::factory()->create(['business_id' => $biz->id, 'google_place_id' => 'self-place-1']);

    $job = new SyncCompetitorSignalsJob((int) $biz->id, (int) $location->id);
    $job->handle();

    $note = CompetitorSiteNote::query()->first();
    expect($note->status)->toBe('noted');
    expect($note->title)->toBe('Distinctive Peer Title 4471');
    expect($note->description)->toBe('Distinctive peer description 4472');
    expect($note->headings)->toBe(['Distinctive heading 4473', 'Distinctive heading 4475']);

    expect(CompetitorSiteNote::count())->toBe(1);

    expect(DB::table('competitor_site_notes')->whereRaw("CAST(row_to_json(competitor_site_notes) AS text) LIKE '%4474%'")->count())->toBe(0);
    expect(DB::table('competitor_site_notes')->whereRaw("CAST(row_to_json(competitor_site_notes) AS text) LIKE '%4476%'")->count())->toBe(0);

    Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://peer-4471.example'));
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'distinctive-image-4476.png'));
    $this->assertDatabaseHas('fetch_attempts', ['source_key' => 'competitor_site']);
});

it('records a robots refusal as a refusal and fetches nothing', function () {
    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nDisallow: /", 200, ['Content-Type' => 'text/plain']),
        'places.googleapis.com/v1/places:searchNearby' => Http::response([
            'places' => [
                ['id' => 'place-4471', 'displayName' => ['text' => 'Distinctive Peer 4471'], 'rating' => 4.6, 'userRatingCount' => 88, 'websiteUri' => 'https://peer-4471.example/'],
                ['id' => 'place-4472', 'displayName' => ['text' => 'Distinctive Peer 4472'], 'rating' => 4.1],
            ],
        ]),
        'places.googleapis.com/v1/places/*' => Http::response([
            'id' => 'self-place-1',
            'displayName' => ['text' => 'My Shop'],
            'location' => ['latitude' => 34.0, 'longitude' => -118.0],
            'primaryType' => 'store',
        ]),
        'https://peer-4471.example/' => Http::response('<html><head><title>Distinctive Peer Title 4471</title><meta name="description" content="Distinctive peer description 4472"></head><body><h1>Distinctive heading 4473</h1><p>Distinctive body sentence 4474 that must never be stored.</p><h2>Distinctive heading 4475</h2><img src="/distinctive-image-4476.png"></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);
    config(['credentials.google_places_key' => 'test-key']);

    $location = Location::factory()->create(['business_id' => $biz->id, 'google_place_id' => 'self-place-1']);

    $job = new SyncCompetitorSignalsJob((int) $biz->id, (int) $location->id);
    $job->handle();

    $note = CompetitorSiteNote::query()->first();
    expect($note->status)->toBe('refused');
    expect($note->refusal_reason)->toBe('robots_disallow');
    expect($note->title)->toBeNull();

    Http::assertNotSent(fn ($r) => $r->url() === 'https://peer-4471.example/');
});

it('does not fetch again inside the freshness window', function () {
    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
        'places.googleapis.com/v1/places:searchNearby' => Http::response([
            'places' => [
                ['id' => 'place-4471', 'displayName' => ['text' => 'Distinctive Peer 4471'], 'rating' => 4.6, 'userRatingCount' => 88, 'websiteUri' => 'https://peer-4471.example/'],
                ['id' => 'place-4472', 'displayName' => ['text' => 'Distinctive Peer 4472'], 'rating' => 4.1],
            ],
        ]),
        'places.googleapis.com/v1/places/*' => Http::response([
            'id' => 'self-place-1',
            'displayName' => ['text' => 'My Shop'],
            'location' => ['latitude' => 34.0, 'longitude' => -118.0],
            'primaryType' => 'store',
        ]),
        'https://peer-4471.example/' => Http::response('<html><head><title>Distinctive Peer Title 4471</title><meta name="description" content="Distinctive peer description 4472"></head><body><h1>Distinctive heading 4473</h1><p>Distinctive body sentence 4474 that must never be stored.</p><h2>Distinctive heading 4475</h2><img src="/distinctive-image-4476.png"></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);
    config(['credentials.google_places_key' => 'test-key']);

    $location = Location::factory()->create(['business_id' => $biz->id, 'google_place_id' => 'self-place-1']);

    $job = new SyncCompetitorSignalsJob((int) $biz->id, (int) $location->id);
    $job->handle();
    $job->handle(); // Run twice

    Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://peer-4471.example'));
    expect(collect(Http::recorded())->filter(fn ($pair) => $pair[0]->url() === 'https://peer-4471.example/')->count())->toBe(1);
});
