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
        'publish_status' => 'published',
        'provider_post_id' => 'post_7901',
    ]);
});

test('a comment.received', function () {
    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_comment_1',
        'event' => 'comment.received',
        'comment' => [
            'id' => 'fbc_7902',
            'postId' => 'post_7901',
            'platform' => 'facebook',
            'text' => 'Love this 7903',
            'author' => [
                'id' => 'u_7904',
                'name' => 'Pat Reader',
                'isOwnAccount' => false,
            ],
            'createdAt' => '2026-09-27T10:00:00Z',
            'isReply' => false,
            'parentCommentId' => null,
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

    $this->assertDatabaseHas('comments', [
        'platform_comment_id' => 'fbc_7902',
        'comment_text' => 'Love this 7903',
        'sentiment' => 'unclassified',
        'is_publicly_replied' => false,
    ]);
});

test('b the same event twice', function () {
    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_comment_2',
        'event' => 'comment.received',
        'comment' => [
            'id' => 'fbc_7902_dup',
            'postId' => 'post_7901',
            'platform' => 'facebook',
            'text' => 'Love this 7903',
            'author' => [
                'id' => 'u_7904',
                'name' => 'Pat Reader',
                'isOwnAccount' => false,
            ],
            'createdAt' => '2026-09-27T10:00:00Z',
            'isReply' => false,
            'parentCommentId' => null,
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
        'id' => 'evt_comment_2_b',
        'event' => 'comment.received',
        'comment' => [
            'id' => 'fbc_7902_dup',
            'postId' => 'post_7901',
            'platform' => 'facebook',
            'text' => 'Love this 7903',
            'author' => [
                'id' => 'u_7904',
                'name' => 'Pat Reader',
                'isOwnAccount' => false,
            ],
            'createdAt' => '2026-09-27T10:00:00Z',
            'isReply' => false,
            'parentCommentId' => null,
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
    expect($response2->json('outcome'))->toBe('handled');

    Tenancy::set($this->biz->id);
    $this->assertDatabaseCount('comments', 1);
});

test('c author.isOwnAccount true', function () {
    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_comment_3',
        'event' => 'comment.received',
        'comment' => [
            'id' => 'fbc_7902_own',
            'postId' => 'post_7901',
            'platform' => 'facebook',
            'text' => 'Love this 7903',
            'author' => [
                'id' => 'u_7904',
                'name' => 'Pat Reader',
                'isOwnAccount' => true,
            ],
            'createdAt' => '2026-09-27T10:00:00Z',
            'isReply' => false,
            'parentCommentId' => null,
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
    expect($response->json('outcome'))->toBe('own_reply');

    Tenancy::set($this->biz->id);
    $this->assertDatabaseCount('comments', 0);
});

test('d comment.postId null', function () {
    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_comment_4',
        'event' => 'comment.received',
        'comment' => [
            'id' => 'fbc_7902_no_post',
            'postId' => null,
            'platform' => 'facebook',
            'text' => 'Love this 7903',
            'author' => [
                'id' => 'u_7904',
                'name' => 'Pat Reader',
                'isOwnAccount' => false,
            ],
            'createdAt' => '2026-09-27T10:00:00Z',
            'isReply' => false,
            'parentCommentId' => null,
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
    expect($response->json('outcome'))->toBe('unknown_post');

    Tenancy::set($this->biz->id);
    $this->assertDatabaseCount('comments', 0);
});

test('e cross tenant post', function () {
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
        'id' => 'evt_cross_comment',
        'event' => 'comment.received',
        'comment' => [
            'id' => 'fbc_7902_cross',
            'postId' => 'post_7901',
            'platform' => 'facebook',
            'text' => 'Love this 7903',
            'author' => [
                'id' => 'u_7904',
                'name' => 'Pat Reader',
                'isOwnAccount' => false,
            ],
            'createdAt' => '2026-09-27T10:00:00Z',
            'isReply' => false,
            'parentCommentId' => null,
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
    expect($response->json('outcome'))->toBe('unknown_post');

    Tenancy::set($this->biz->id);
    $this->assertDatabaseCount('comments', 0);
    Tenancy::set($biz2->id);
    $this->assertDatabaseCount('comments', 0);
});

test('f real GET route as owner after a', function () {
    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_comment_f',
        'event' => 'comment.received',
        'comment' => [
            'id' => 'fbc_7902_f',
            'postId' => 'post_7901',
            'platform' => 'facebook',
            'text' => 'Love this 7903',
            'author' => [
                'id' => 'u_7904',
                'name' => 'Pat Reader',
                'isOwnAccount' => false,
            ],
            'createdAt' => '2026-09-27T10:00:00Z',
            'isReply' => false,
            'parentCommentId' => null,
        ],
        'account' => [
            'accountId' => 'acct_fb_7101',
        ],
    ], JSON_THROW_ON_ERROR);

    $sig = hash_hmac('sha256', $payload, 'test_secret');

    $this->call(
        'POST',
        '/webhooks/zernio',
        [], [], [],
        ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );

    Tenancy::set($this->biz->id);

    $this->actingAs($this->owner);
    $this->get(route('x-182.social-queue'))
        ->assertOk()
        ->assertSee('Pat Reader: Love this 7903');
});
