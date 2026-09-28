<?php

declare(strict_types=1);

use App\Jobs\Reviews\SyncFacebookReviewsJob;
use App\Models\Location;
use App\Modules\X182\Models\SocialAccount;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

beforeEach(function () {
    $this->biz = TestCase::provisionTenant();
    Tenancy::set((int) $this->biz->id);

    $this->location = Location::factory()->create(['business_id' => $this->biz->id]);

    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    Config::set('credentials.zernio_api_key', 'test-key');

    $this->account = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'location_id' => $this->location->id,
        'provider_profile_ref' => 'profile_7301',
    ]);

    $this->account->account_ref = 'acct_fb_7301';
    $this->account->save();
});

it('syncs facebook reviews successfully', function () {
    Http::fake([
        'zernio.com/api/v1/inbox/reviews*' => Http::response([
            'data' => [
                ['id' => 'fbr_7301', 'rating' => 5, 'recommendationType' => 'positive'],
                ['id' => 'fbr_7302', 'rating' => 4, 'recommendationType' => 'positive'],
            ],
        ]),
    ]);

    $job = new SyncFacebookReviewsJob((int) $this->biz->id, (int) $this->account->id);
    $job->handle();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'accountId=acct_fb_7301') && str_contains($request->url(), 'platform=facebook');
    });

    $reviews = DB::table('reviews')
        ->where('location_id', $this->location->id)
        ->where('source', 'facebook')
        ->get();

    expect($reviews)->toHaveCount(2);
});

it('fetches multiple pages until cursor is null', function () {
    Http::fake([
        'zernio.com/api/v1/inbox/reviews*cursor=c7302*' => Http::response([
            'data' => [
                ['id' => 'fbr_7303', 'rating' => 3, 'recommendationType' => 'positive'],
            ],
        ]),
        'zernio.com/api/v1/inbox/reviews*' => Http::response([
            'data' => [
                ['id' => 'fbr_7301', 'rating' => 5, 'recommendationType' => 'positive'],
                ['id' => 'fbr_7302', 'rating' => 4, 'recommendationType' => 'positive'],
            ],
            'pagination' => ['nextCursor' => 'c7302'],
        ]),
    ]);

    $job = new SyncFacebookReviewsJob((int) $this->biz->id, (int) $this->account->id);
    $job->handle();

    Http::assertSentCount(2);

    $reviews = DB::table('reviews')
        ->where('location_id', $this->location->id)
        ->where('source', 'facebook')
        ->get();

    expect($reviews)->toHaveCount(3);
});

it('does nothing when location_id is null', function () {
    $this->account->location_id = null;
    $this->account->save();

    Http::fake();

    $job = new SyncFacebookReviewsJob((int) $this->biz->id, (int) $this->account->id);
    $job->handle();

    Http::assertNothingSent();
});

it('does nothing when zernio is disabled', function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', false, 'test');

    Http::fake();

    $job = new SyncFacebookReviewsJob((int) $this->biz->id, (int) $this->account->id);
    $job->handle();

    Http::assertNothingSent();
});

it('command pushes correct jobs', function () {
    Queue::fake();

    // one instagram account
    SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'instagram',
        'status' => 'connected',
        'is_connected' => true,
        'location_id' => $this->location->id,
        'provider_profile_ref' => 'profile_ig',
        'account_ref' => 'acct_ig',
    ]);

    // one facebook account with no account_ref
    SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'location_id' => $this->location->id,
        'provider_profile_ref' => 'profile_no_ref',
    ]);

    $this->artisan('facebook:sync-reviews')->assertSuccessful();

    Queue::assertPushed(SyncFacebookReviewsJob::class, 1);
});

it('isolates per tenant', function () {
    Http::fake([
        'zernio.com/api/v1/inbox/reviews*accountId=acct_fb_7301*' => Http::response([
            'data' => [
                ['id' => 'fbr_7301', 'rating' => 5, 'recommendationType' => 'positive'],
            ],
        ]),
        'zernio.com/api/v1/inbox/reviews*accountId=acct_fb_7399*' => Http::response([
            'data' => [
                ['id' => 'fbr_7399', 'rating' => 4, 'recommendationType' => 'positive'],
            ],
        ]),
    ]);

    // First tenant's job
    $job1 = new SyncFacebookReviewsJob((int) $this->biz->id, (int) $this->account->id);
    $job1->handle();

    // Create a second tenant
    $biz2 = TestCase::provisionTenant();
    Tenancy::set((int) $biz2->id);

    $location2 = Location::factory()->create(['business_id' => $biz2->id]);

    $account2 = SocialAccount::create([
        'business_id' => $biz2->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'location_id' => $location2->id,
        'provider_profile_ref' => 'profile_7399',
    ]);

    $account2->account_ref = 'acct_fb_7399';
    $account2->save();

    $job2 = new SyncFacebookReviewsJob((int) $biz2->id, (int) $account2->id);
    $job2->handle();

    // Assert each location got only its own review
    Tenancy::set((int) $this->biz->id);
    $reviews1 = DB::table('reviews')->where('location_id', $this->location->id)->get();
    expect($reviews1)->toHaveCount(1);
    expect($reviews1->first()->provider_review_id)->toBe('fbr_7301');

    Tenancy::set((int) $biz2->id);
    $reviews2 = DB::table('reviews')->where('location_id', $location2->id)->get();
    expect($reviews2)->toHaveCount(1);
    expect($reviews2->first()->provider_review_id)->toBe('fbr_7399');
});
