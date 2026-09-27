<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GbpAccountBinding;
use App\Models\GbpConnection;
use App\Models\Location;
use App\Models\User;
use App\Modules\X177\Events\GbpPosted;
use App\Modules\X177\Models\GbpPost;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

function sendZernioWebhook(TestCase $testCase, array $payload): TestResponse
{
    Config::set('credentials.zernio_webhook_secret', 'test_secret');
    $json = json_encode($payload, JSON_THROW_ON_ERROR);
    $sig = hash_hmac('sha256', $json, 'test_secret');

    return $testCase->call(
        'POST',
        '/webhooks/zernio',
        [], [], [],
        ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $json,
    );
}

beforeEach(function () {
    Http::fake();
});

it('settles a post on published webhook', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    $location = Location::factory()->create(['business_id' => $biz->id]);
    $conn = GbpConnection::factory()->connected()->create(['location_id' => $location->id, 'business_id' => $biz->id, 'account_ref' => 'my-account']);
    GbpAccountBinding::create([
        'account_ref' => 'my-account',
        'business_id' => $biz->id,
        'location_id' => $location->id,
    ]);

    $post = GbpPost::create([
        'business_id' => $biz->id,
        'connection_id' => $conn->id,
        'content' => 'hello',
        'status' => 'publishing',
    ]);

    Event::fake([GbpPosted::class]);

    $response = sendZernioWebhook($this, [
        'id' => 'evt_1',
        'event' => 'post.platform.published',
        'account' => ['id' => 'my-account'],
        'platform' => ['name' => 'googlebusiness'],
        'post' => [
            '_id' => 'zp_4958',
            'metadata' => ['gbp_post_id' => $post->id],
        ],
    ]);
    
    $post->refresh();

    $response->assertStatus(200);

    $post->refresh();
    expect($post->status)->toBe('posted');
    expect($post->zernio_dispatch_id)->toBe('zp_4958');

    Event::assertDispatched(GbpPosted::class, function ($event) use ($biz, $post) {
        return $event->businessId === (int) $biz->id && $event->postId === $post->id && $event->zernioDispatchId === 'zp_4958';
    });
});

it('settles a post on failed webhook', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    $location = Location::factory()->create(['business_id' => $biz->id]);
    $conn = GbpConnection::factory()->connected()->create(['location_id' => $location->id, 'business_id' => $biz->id, 'account_ref' => 'my-account']);
    GbpAccountBinding::create([
        'account_ref' => 'my-account',
        'business_id' => $biz->id,
        'location_id' => $location->id,
    ]);

    $post = GbpPost::create([
        'business_id' => $biz->id,
        'connection_id' => $conn->id,
        'content' => 'hello',
        'status' => 'publishing',
    ]);

    $response = sendZernioWebhook($this, [
        'id' => 'evt_2',
        'event' => 'post.platform.failed',
        'account' => ['id' => 'my-account'],
        'platform' => [
            'name' => 'googlebusiness',
            'error' => 'Request contains an invalid argument.',
        ],
        'post' => [
            'metadata' => ['gbp_post_id' => $post->id],
        ],
    ]);

    $response->assertStatus(200);

    $post->refresh();
    expect($post->status)->toBe('failed');
    expect($post->failure_reason)->toBe('Request contains an invalid argument.');
});

it('deduplicates the same event id', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    $location = Location::factory()->create(['business_id' => $biz->id]);
    $conn = GbpConnection::factory()->connected()->create(['location_id' => $location->id, 'business_id' => $biz->id, 'account_ref' => 'my-account']);
    GbpAccountBinding::create([
        'account_ref' => 'my-account',
        'business_id' => $biz->id,
        'location_id' => $location->id,
    ]);

    $post = GbpPost::create([
        'business_id' => $biz->id,
        'connection_id' => $conn->id,
        'content' => 'hello',
        'status' => 'publishing',
    ]);

    $payload = [
        'id' => 'evt_3',
        'event' => 'post.platform.failed',
        'account' => ['id' => 'my-account'],
        'platform' => [
            'name' => 'googlebusiness',
            'error' => 'Error 1',
        ],
        'post' => [
            'metadata' => ['gbp_post_id' => $post->id],
        ],
    ];

    sendZernioWebhook($this, $payload);

    $payload['platform']['error'] = 'Error 2';
    sendZernioWebhook($this, $payload);

    $post->refresh();
    expect($post->status)->toBe('failed');
    expect($post->failure_reason)->toBe('Error 1');
});

it('ignores failed webhook for already posted row', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    $location = Location::factory()->create(['business_id' => $biz->id]);
    $conn = GbpConnection::factory()->connected()->create(['location_id' => $location->id, 'business_id' => $biz->id, 'account_ref' => 'my-account']);
    GbpAccountBinding::create([
        'account_ref' => 'my-account',
        'business_id' => $biz->id,
        'location_id' => $location->id,
    ]);

    $post = GbpPost::create([
        'business_id' => $biz->id,
        'connection_id' => $conn->id,
        'content' => 'hello',
        'status' => 'posted',
    ]);

    sendZernioWebhook($this, [
        'id' => 'evt_4',
        'event' => 'post.platform.failed',
        'account' => ['id' => 'my-account'],
        'platform' => [
            'name' => 'googlebusiness',
            'error' => 'Request contains an invalid argument.',
        ],
        'post' => [
            'metadata' => ['gbp_post_id' => $post->id],
        ],
    ]);

    $post->refresh();
    expect($post->status)->toBe('posted');
});

it('ignores event with wrong platform', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    $location = Location::factory()->create(['business_id' => $biz->id]);
    $conn = GbpConnection::factory()->connected()->create(['location_id' => $location->id, 'business_id' => $biz->id, 'account_ref' => 'my-account']);
    GbpAccountBinding::create([
        'account_ref' => 'my-account',
        'business_id' => $biz->id,
        'location_id' => $location->id,
    ]);

    $post = GbpPost::create([
        'business_id' => $biz->id,
        'connection_id' => $conn->id,
        'content' => 'hello',
        'status' => 'publishing',
    ]);

    sendZernioWebhook($this, [
        'id' => 'evt_5',
        'event' => 'post.platform.published',
        'account' => ['id' => 'my-account'],
        'platform' => ['name' => 'facebook'],
        'post' => [
            '_id' => 'zp_4958',
            'metadata' => ['gbp_post_id' => $post->id],
        ],
    ]);

    $post->refresh();
    expect($post->status)->toBe('publishing');
});

it('ignores event with no binding', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    $location = Location::factory()->create(['business_id' => $biz->id]);
    $conn = GbpConnection::factory()->connected()->create(['location_id' => $location->id, 'business_id' => $biz->id, 'account_ref' => 'my-account']);
    GbpAccountBinding::create([
        'account_ref' => 'my-account',
        'business_id' => $biz->id,
        'location_id' => $location->id,
    ]);

    $post = GbpPost::create([
        'business_id' => $biz->id,
        'connection_id' => $conn->id,
        'content' => 'hello',
        'status' => 'publishing',
    ]);

    sendZernioWebhook($this, [
        'id' => 'evt_6',
        'event' => 'post.platform.published',
        'account' => ['id' => 'wrong-account'],
        'platform' => ['name' => 'googlebusiness'],
        'post' => [
            '_id' => 'zp_4958',
            'metadata' => ['gbp_post_id' => $post->id],
        ],
    ]);

    $post->refresh();
    expect($post->status)->toBe('publishing');
});
