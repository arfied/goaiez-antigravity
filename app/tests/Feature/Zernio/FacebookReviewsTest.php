<?php

use App\Exceptions\GbpRequestFailed;
use App\Models\Business;
use App\Models\Location;
use App\Services\Config\DefaultsRegistry;
use App\Services\Reviews\FacebookReviewIngest;
use App\Services\Zernio\ZernioSocialClient;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    config(['credentials.zernio_api_key' => 'test-key']);
});

it('fetches facebook reviews and handles missing rating by preserving it as null', function () {
    $biz = Business::factory()->create();
    Tenancy::set((int) $biz->id);

    Http::fake([
        'zernio.com/api/v1/inbox/reviews*' => Http::response([
            'data' => [
                [
                    'id' => 'fbr_5601',
                    'rating' => 5,
                    'recommendationType' => 'positive',
                ],
                [
                    'id' => 'fbr_5602',
                    'rating' => null,
                    'recommendationType' => 'positive',
                ],
                [
                    'id' => 'fbr_5603',
                    'rating' => 2,
                    'hasReply' => true,
                ],
            ],
            'pagination' => [
                'nextCursor' => 'c2',
            ],
        ]),
    ]);

    $client = app(ZernioSocialClient::class);
    $page = $client->facebookReviews('acct_fb_5600');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://zernio.com/api/v1/inbox/reviews?accountId=acct_fb_5600&platform=facebook&limit=25&sortBy=date';
    });

    // GbpReview::fromZernio returns null for the rating if it is missing, so it remains in the page with rating = null
    expect($page->reviews)->toHaveCount(3);
    expect($page->nextCursor)->toBe('c2');
    expect($page->reviews[0]->externalId)->toBe('fbr_5601');
    expect($page->reviews[1]->externalId)->toBe('fbr_5602');
    expect($page->reviews[1]->rating)->toBeNull();
    expect($page->reviews[2]->externalId)->toBe('fbr_5603');
});

it('upserts page skipping unrated reviews', function () {
    $biz = Business::factory()->create();
    $location = Location::factory()->create(['business_id' => $biz->id]);
    Tenancy::set((int) $biz->id);

    Http::fake([
        'zernio.com/api/v1/inbox/reviews*' => Http::response([
            'data' => [
                ['id' => 'fbr_5601', 'rating' => 5, 'recommendationType' => 'positive'],
                ['id' => 'fbr_5602', 'rating' => null, 'recommendationType' => 'positive'],
                ['id' => 'fbr_5603', 'rating' => 2, 'hasReply' => true],
            ],
            'pagination' => ['nextCursor' => 'c2'],
        ]),
    ]);

    $client = app(ZernioSocialClient::class);
    $page = $client->facebookReviews('acct_fb_5600');

    $ingest = app(FacebookReviewIngest::class);
    $result = $ingest->upsertPage($location, $page);

    expect($result)->toBe(['inserted' => 2, 'updated' => 0, 'unrated' => 1]);

    $rows = DB::table('reviews')->where('location_id', $location->id)->get();
    expect($rows)->toHaveCount(2);

    $row1 = $rows->where('provider_review_id', 'fbr_5601')->first();
    expect($row1->source)->toBe('facebook');
    expect($row1->recommendation)->toBe('positive');
    expect((bool) $row1->display_on_website)->toBeFalse();

    // Second upsert
    $result2 = $ingest->upsertPage($location, $page);
    expect($result2)->toBe(['inserted' => 0, 'updated' => 2, 'unrated' => 1]);
    expect(DB::table('reviews')->where('location_id', $location->id)->count())->toBe(2);
});

it('refuses location of another tenant', function () {
    $biz1 = Business::factory()->create();
    $biz2 = Business::factory()->create();
    $location = Location::factory()->create(['business_id' => $biz2->id]);
    Tenancy::set((int) $biz1->id);

    Http::fake([
        'zernio.com/api/v1/inbox/reviews*' => Http::response(['data' => []]),
    ]);

    $client = app(ZernioSocialClient::class);
    $page = $client->facebookReviews('acct_fb_5600');

    $ingest = app(FacebookReviewIngest::class);

    expect(fn () => $ingest->upsertPage($location, $page))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses positive recommendation on google source', function () {
    $biz = Business::factory()->create();
    $location = Location::factory()->create(['business_id' => $biz->id]);
    Tenancy::set((int) $biz->id);

    expect(fn () => DB::table('reviews')->insert([
        'location_id' => $location->id,
        'source' => 'google',
        'rating' => 5,
        'status' => 'approved',
        'reviewer_name' => 'Test',
        'is_platform' => true,
        'ingest_method' => 'api',
        'display_on_website' => true,
        'raw_payload' => '{}',
        'recommendation' => 'positive',
    ]))->toThrow(QueryException::class);
});

it('throws when flag is off', function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', false, 'test');

    Http::fake();

    $client = app(ZernioSocialClient::class);

    expect(fn () => $client->facebookReviews('acct_fb_5600'))
        ->toThrow(GbpRequestFailed::class);

    Http::assertNothingSent();
});
