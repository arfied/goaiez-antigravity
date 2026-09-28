<?php

declare(strict_types=1);

use App\Models\GbpProfileBinding;
use App\Models\User;
use App\Models\ZernioAccountBinding;
use App\Modules\X182\Models\SocialAccount;
use App\Modules\X182\Models\SocialPost;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

beforeEach(function () {
    $this->biz = TestCase::provisionTenant(['name' => 'Social Biz', 'currency' => 'USD']);
    $this->owner = User::find($this->biz->owner_user_id);

    Config::set('credentials.zernio_webhook_secret', 'test_secret');

    Tenancy::set($this->biz->id);

    GbpProfileBinding::create([
        'business_id' => $this->biz->id,
        'profile_ref' => 'profile_7101',
    ]);

    $this->account = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'provider_profile_ref' => 'profile_7101',
    ]);
    $this->account->account_ref = 'acct_fb_7101';
    $this->account->save();

    ZernioAccountBinding::create([
        'account_ref' => 'acct_fb_7101',
        'profile_ref' => 'profile_7101',
        'platform' => 'facebook',
    ]);

    $this->postRow = SocialPost::create([
        'business_id' => $this->biz->id,
        'account_id' => $this->account->id,
        'content_text' => 'test post',
        'publish_status' => 'publishing',
    ]);
});

test('a post.platform.published', function () {
    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_published',
        'event' => 'post.platform.published',
        'post' => [
            'id' => 'post_7102',
            'metadata' => [
                'social_post_id' => (string) $this->postRow->id,
            ],
        ],
        'platform' => [
            'name' => 'facebook',
            'status' => 'published',
            'platformPostId' => 'fb_7103',
            'publishedUrl' => 'https://facebook.com/7103',
        ],
        'account' => [
            'accountId' => 'acct_fb_7101',
        ],
    ], JSON_THROW_ON_ERROR);

    $sig = hash_hmac('sha256', $payload, 'test_secret');

    $response = $this->call(
        'POST',
        '/webhooks/zernio',
        [], [], [],
        ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );

    $response->assertStatus(200);
    expect($response->json('ok'))->toBeTrue();
    expect($response->json('outcome'))->toBe('handled');

    Tenancy::set($this->biz->id);

    $this->assertDatabaseHas('social_posts', [
        'id' => $this->postRow->id,
        'publish_status' => 'published',
        'is_published' => true,
        'platform_post_url' => 'https://facebook.com/7103',
        'provider_post_id' => 'post_7102',
    ]);
});

test('b post.platform.failed', function () {
    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_failed',
        'event' => 'post.platform.failed',
        'post' => [
            'id' => 'post_7102',
            'metadata' => [
                'social_post_id' => (string) $this->postRow->id,
            ],
        ],
        'platform' => [
            'name' => 'facebook',
            'status' => 'failed',
            'error' => 'Media rejected 7104',
        ],
        'account' => [
            'accountId' => 'acct_fb_7101',
        ],
    ], JSON_THROW_ON_ERROR);

    $sig = hash_hmac('sha256', $payload, 'test_secret');

    $response = $this->call(
        'POST',
        '/webhooks/zernio',
        [], [], [],
        ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );

    $response->assertStatus(200);
    expect($response->json('outcome'))->toBe('handled');

    Tenancy::set($this->biz->id);

    $this->assertDatabaseHas('social_posts', [
        'id' => $this->postRow->id,
        'publish_status' => 'failed',
        'last_error' => 'Media rejected 7104',
    ]);

    $this->actingAs($this->owner);
    $this->get(route('x-182.social-queue'))
        ->assertOk()
        ->assertSee('not published: Media rejected 7104');
});

test('c the same published webhook sent twice', function () {
    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_published_1',
        'event' => 'post.platform.published',
        'post' => [
            'id' => 'post_7102',
            'metadata' => [
                'social_post_id' => (string) $this->postRow->id,
            ],
        ],
        'platform' => [
            'name' => 'facebook',
            'status' => 'published',
            'platformPostId' => 'fb_7103',
            'publishedUrl' => 'https://facebook.com/7103',
        ],
        'account' => [
            'accountId' => 'acct_fb_7101',
        ],
    ], JSON_THROW_ON_ERROR);

    $sig = hash_hmac('sha256', $payload, 'test_secret');

    $response = $this->call(
        'POST',
        '/webhooks/zernio',
        [], [], [],
        ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );

    $response->assertStatus(200);
    expect($response->json('outcome'))->toBe('handled');

    $payload2 = json_encode([
        'id' => 'evt_published_2',
        'event' => 'post.platform.published',
        'post' => [
            'id' => 'post_7102',
            'metadata' => [
                'social_post_id' => (string) $this->postRow->id,
            ],
        ],
        'platform' => [
            'name' => 'facebook',
            'status' => 'published',
            'platformPostId' => 'fb_7103',
            'publishedUrl' => 'https://facebook.com/7103',
        ],
        'account' => [
            'accountId' => 'acct_fb_7101',
        ],
    ], JSON_THROW_ON_ERROR);

    $sig2 = hash_hmac('sha256', $payload2, 'test_secret');

    $response2 = $this->call(
        'POST',
        '/webhooks/zernio',
        [], [], [],
        ['HTTP_X_ZERNIO_SIGNATURE' => $sig2, 'CONTENT_TYPE' => 'application/json'],
        $payload2,
    );

    $response2->assertStatus(200);
    expect($response2->json('outcome'))->toBe('already_settled');
});

test('d cross tenant post', function () {
    $biz2 = TestCase::provisionTenant(['name' => 'Biz 2', 'currency' => 'USD']);

    Tenancy::set((int) $biz2->id);

    GbpProfileBinding::create([
        'business_id' => $biz2->id,
        'profile_ref' => 'profile_7199',
    ]);

    $account2 = SocialAccount::create([
        'business_id' => $biz2->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'provider_profile_ref' => 'profile_7199',
    ]);
    $account2->account_ref = 'acct_fb_7199';
    $account2->save();

    ZernioAccountBinding::create([
        'account_ref' => 'acct_fb_7199',
        'profile_ref' => 'profile_7199',
        'platform' => 'facebook',
    ]);

    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_cross',
        'event' => 'post.platform.published',
        'post' => [
            'id' => 'post_7102',
            'metadata' => [
                'social_post_id' => (string) $this->postRow->id,
            ],
        ],
        'platform' => [
            'name' => 'facebook',
            'status' => 'published',
            'platformPostId' => 'fb_7103',
            'publishedUrl' => 'https://facebook.com/7103',
        ],
        'account' => [
            'accountId' => 'acct_fb_7199',
        ],
    ], JSON_THROW_ON_ERROR);

    $sig = hash_hmac('sha256', $payload, 'test_secret');

    $response = $this->call(
        'POST',
        '/webhooks/zernio',
        [], [], [],
        ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );

    $response->assertStatus(200);
    expect($response->json('outcome'))->not->toBe('handled');

    Tenancy::set($this->biz->id);

    $this->assertDatabaseHas('social_posts', [
        'id' => $this->postRow->id,
        'publish_status' => 'publishing',
    ]);
});

test('e no social_post_id in metadata', function () {
    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_no_meta',
        'event' => 'post.platform.published',
        'post' => [
            'id' => 'post_7102',
            'metadata' => [],
        ],
        'platform' => [
            'name' => 'facebook',
            'status' => 'published',
            'platformPostId' => 'fb_7103',
            'publishedUrl' => 'https://facebook.com/7103',
        ],
        'account' => [
            'accountId' => 'acct_fb_7101',
        ],
    ], JSON_THROW_ON_ERROR);

    $sig = hash_hmac('sha256', $payload, 'test_secret');

    $response = $this->call(
        'POST',
        '/webhooks/zernio',
        [], [], [],
        ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );

    $response->assertStatus(200);
    expect($response->json('outcome'))->toBe('ignored');

    Tenancy::set($this->biz->id);

    $this->assertDatabaseHas('social_posts', [
        'id' => $this->postRow->id,
        'publish_status' => 'publishing',
    ]);
});
