<?php

use App\Enums\UserRole;
use App\Jobs\Visibility\SyncCompetitorSignalsJob;
use App\Models\CompetitorSiteNote;
use App\Models\Location;
use App\Models\User;
use App\Services\Visibility\CompetitorSiteNotes;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// The boss, 2026-10-02: "read their full page text, not just headings" — the page text is now kept (capped, menus and
// code removed) for the AI designer's checklist. Pictures are still never fetched or stored.
it('keeps a note of a peer site — title, description, headings and its own page text — but never its pictures', function () {
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
        'https://peer-4471.example/' => Http::response('<html><head><title>Distinctive Peer Title 4471</title><meta name="description" content="Distinctive peer description 4472"></head><body><h1>Distinctive heading 4473</h1><nav>Distinctive menu 4477</nav><p>Distinctive body sentence 4474.</p><script>var tracker = "Distinctive script 4478";</script><h2>Distinctive heading 4475</h2><img src="/distinctive-image-4476.png"></body></html>', 200, ['Content-Type' => 'text/html']),
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

    expect($note->text)->toContain('Distinctive body sentence 4474')
        ->and($note->text)->not->toContain('Distinctive menu 4477')
        ->and($note->text)->not->toContain('Distinctive script 4478');
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

it('reports what the peers cover without naming them', function () {
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

    $t = app(CompetitorSiteNotes::class)->topicsFor((int) $biz->id);
    expect($t['read'])->toBe(1);
    expect($t['topics'])->toBe(['Distinctive heading 4473', 'Distinctive heading 4475']);
    expect(json_encode($t))->not->toContain('4471');
});

// The competitor_site source may fetch 2 pages a minute, so only the two best-ranked peers are read in one sync — which
// is why the fetch order matters as much as the order notes are handed to the designer.
it('reads and hands the designer the best-ranked peers first, by reviews then rating, with their page text', function () {
    Http::fake([
        '*/robots.txt' => Http::response("User-agent: *\nAllow: /", 200, ['Content-Type' => 'text/plain']),
        'places.googleapis.com/v1/places:searchNearby' => Http::response([
            'places' => [
                ['id' => 'place-a', 'displayName' => ['text' => 'Peer Few Reviews 4481'], 'rating' => 4.9, 'userRatingCount' => 5, 'websiteUri' => 'https://peer-a.example/'],
                ['id' => 'place-b', 'displayName' => ['text' => 'Peer Many Lower 4482'], 'rating' => 4.1, 'userRatingCount' => 300, 'websiteUri' => 'https://peer-b.example/'],
                ['id' => 'place-c', 'displayName' => ['text' => 'Peer Many Higher 4483'], 'rating' => 4.7, 'userRatingCount' => 300, 'websiteUri' => 'https://peer-c.example/'],
            ],
        ]),
        'places.googleapis.com/v1/places/*' => Http::response([
            'id' => 'self-place-1',
            'displayName' => ['text' => 'My Shop'],
            'location' => ['latitude' => 34.0, 'longitude' => -118.0],
            'primaryType' => 'store',
        ]),
        'https://peer-a.example/' => Http::response('<html><head><title>A</title></head><body><p>Text of peer A 4491.</p></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://peer-b.example/' => Http::response('<html><head><title>B</title></head><body><p>Text of peer B 4492.</p></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://peer-c.example/' => Http::response('<html><head><title>C</title></head><body><p>Text of peer C 4493.</p></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);
    config(['credentials.google_places_key' => 'test-key']);
    $location = Location::factory()->create(['business_id' => $biz->id, 'google_place_id' => 'self-place-1']);

    (new SyncCompetitorSignalsJob((int) $biz->id, (int) $location->id))->handle();

    $names = array_column(app(CompetitorSiteNotes::class)->notesFor((int) $biz->id), 'name');
    expect($names)->toBe(['Peer Many Higher 4483', 'Peer Many Lower 4482']);

    $block = app(CompetitorSiteNotes::class)->referenceBlock((int) $biz->id);
    expect($block)->toContain('Text of peer C 4493')
        ->and(strpos($block, 'Text of peer C 4493'))->toBeLessThan(strpos($block, 'Text of peer B 4492'))
        ->and($block)->not->toContain('Text of peer A 4491')
        ->and($block)->toContain('never reuse their names, sentences or wording');
});
