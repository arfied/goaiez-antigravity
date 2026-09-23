<?php

use App\Enums\UserRole;
use App\Jobs\Reviews\SyncGoogleReviewsJob;
use App\Models\GbpConnection;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

it('tests SyncGoogleReviewsJob creates one review', function () {
    Http::fake([
        '*zernio.com/api/v1/inbox/reviews*' => Http::response([
            'data' => [
                [
                    'id' => 'google-review-1',
                    'rating' => 5,
                    'text' => 'Great place',
                    'createdAt' => '2023-01-01T12:00:00Z',
                    'updatedAt' => '2023-01-01T12:00:00Z',
                    'author' => ['name' => 'Alice'],
                    'hasReply' => false,
                ],
            ],
            'cursor' => null,
        ]),
    ]);

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    $location = Location::factory()->create(['business_id' => $biz->id]);
    GbpConnection::factory()->connected()->create(['location_id' => $location->id, 'business_id' => $biz->id, 'account_ref' => 'my-account']);
    app(DefaultsRegistry::class)->set('gbp.zernio_enabled', true, 'test');
    config(['credentials.zernio_api_key' => 'test-key']);

    $job = new SyncGoogleReviewsJob((int) $biz->id, (int) $location->id);
    $job->handle();

    $count = Review::where('location_id', $location->id)->count();
    expect($count)->toBe(1);
});

it('tests SyncGoogleReviewsJob with no connection', function () {
    Http::fake();

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    $location = Location::factory()->create(['business_id' => $biz->id]);

    $job = new SyncGoogleReviewsJob((int) $biz->id, (int) $location->id);
    $job->handle();

    Http::assertNothingSent();
});

it('tests SyncGoogleReviewsJob is dispatched by command', function () {
    Queue::fake();

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    $location = Location::factory()->create(['business_id' => $biz->id]);
    GbpConnection::factory()->connected()->create(['location_id' => $location->id, 'business_id' => $biz->id]);

    $this->artisan('gbp:sync')->assertSuccessful();

    Queue::assertPushed(SyncGoogleReviewsJob::class);
});
