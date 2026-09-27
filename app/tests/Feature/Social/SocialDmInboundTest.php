<?php

declare(strict_types=1);

use App\Models\GbpProfileBinding;
use App\Models\User;
use App\Models\ZernioAccountBinding;
use App\Modules\X182\Models\SocialAccount;
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
        'profile_ref' => 'profile_7801',
    ]);

    $this->account = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'provider_profile_ref' => 'profile_7801',
    ]);
    $this->account->account_ref = 'acct_fb_7801';
    $this->account->save();

    ZernioAccountBinding::create([
        'account_ref' => 'acct_fb_7801',
        'profile_ref' => 'profile_7801',
        'platform' => 'facebook',
    ]);
});

test('a message.received', function () {
    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_msg_1',
        'event' => 'message.received',
        'message' => [
            'id' => 'm_7802',
            'conversationId' => 'conv_fb_7803',
            'platform' => 'facebook',
            'platformMessageId' => 'mid_7804',
            'direction' => 'incoming',
            'text' => 'Do you open Sundays 7805',
            'attachments' => [],
            'sender' => [
                'id' => 'psid_7806',
                'name' => 'Robin Asker',
            ],
            'sentAt' => '2026-09-27T10:00:00Z',
        ],
        'account' => [
            'accountId' => 'acct_fb_7801',
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

    $this->assertDatabaseHas('conversations', [
        'channel' => 'facebook',
        'provider_conversation_ref' => 'conv_fb_7803',
        'contact_label' => 'Robin Asker',
    ]);

    $this->assertDatabaseHas('messages', [
        'body' => 'Do you open Sundays 7805',
    ]);
});

test('b a second message', function () {
    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_msg_1',
        'event' => 'message.received',
        'message' => [
            'id' => 'm_7802',
            'conversationId' => 'conv_fb_7803',
            'platform' => 'facebook',
            'platformMessageId' => 'mid_7804',
            'direction' => 'incoming',
            'text' => 'Do you open Sundays 7805',
            'attachments' => [],
            'sender' => [
                'id' => 'psid_7806',
                'name' => 'Robin Asker',
            ],
            'sentAt' => '2026-09-27T10:00:00Z',
        ],
        'account' => [
            'accountId' => 'acct_fb_7801',
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

    $payload2 = json_encode([
        'id' => 'evt_msg_2',
        'event' => 'message.received',
        'message' => [
            'id' => 'm_7802_b',
            'conversationId' => 'conv_fb_7803',
            'platform' => 'facebook',
            'platformMessageId' => 'mid_7804_b',
            'direction' => 'incoming',
            'text' => 'Another message',
            'attachments' => [],
            'sender' => [
                'id' => 'psid_7806',
                'name' => 'Robin Asker',
            ],
            'sentAt' => '2026-09-27T10:01:00Z',
        ],
        'account' => [
            'accountId' => 'acct_fb_7801',
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
    $this->assertDatabaseCount('conversations', 1);
    $this->assertDatabaseCount('messages', 2);
});

test('c direction outgoing', function () {
    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_msg_3',
        'event' => 'message.received',
        'message' => [
            'id' => 'm_7802_c',
            'conversationId' => 'conv_fb_7803',
            'platform' => 'facebook',
            'platformMessageId' => 'mid_7804_c',
            'direction' => 'outgoing',
            'text' => 'Outgoing message',
            'attachments' => [],
            'sender' => [
                'id' => 'psid_7806',
                'name' => 'Robin Asker',
            ],
            'sentAt' => '2026-09-27T10:00:00Z',
        ],
        'account' => [
            'accountId' => 'acct_fb_7801',
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
    $this->assertDatabaseCount('conversations', 0);
    $this->assertDatabaseCount('messages', 0);
});

test('e account bound to another tenant', function () {
    $biz2 = TestCase::provisionTenant(['name' => 'Biz 2', 'currency' => 'USD']);

    Tenancy::set((int) $biz2->id);

    GbpProfileBinding::create([
        'business_id' => $biz2->id,
        'profile_ref' => 'profile_7899',
    ]);

    $account2 = SocialAccount::create([
        'business_id' => $biz2->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'provider_profile_ref' => 'profile_7899',
    ]);
    $account2->account_ref = 'acct_fb_7899';
    $account2->save();

    ZernioAccountBinding::create([
        'account_ref' => 'acct_fb_7899',
        'profile_ref' => 'profile_7899',
        'platform' => 'facebook',
    ]);

    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_msg_e',
        'event' => 'message.received',
        'message' => [
            'id' => 'm_7802_e',
            'conversationId' => 'conv_fb_7803_e',
            'platform' => 'facebook',
            'platformMessageId' => 'mid_7804_e',
            'direction' => 'incoming',
            'text' => 'Message to biz 2',
            'attachments' => [],
            'sender' => [
                'id' => 'psid_7806',
                'name' => 'Robin Asker',
            ],
            'sentAt' => '2026-09-27T10:00:00Z',
        ],
        'account' => [
            'accountId' => 'acct_fb_7899',
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
    $this->assertDatabaseCount('conversations', 0);

    Tenancy::set($biz2->id);
    $this->assertDatabaseCount('conversations', 1);
});

test('f the account inbox does not list it', function () {
    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_msg_1',
        'event' => 'message.received',
        'message' => [
            'id' => 'm_7802',
            'conversationId' => 'conv_fb_7803',
            'platform' => 'facebook',
            'platformMessageId' => 'mid_7804',
            'direction' => 'incoming',
            'text' => 'Do you open Sundays 7805',
            'attachments' => [],
            'sender' => [
                'id' => 'psid_7806',
                'name' => 'Robin Asker',
            ],
            'sentAt' => '2026-09-27T10:00:00Z',
        ],
        'account' => [
            'accountId' => 'acct_fb_7801',
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
    $this->get(route('account.inbox'))
        ->assertOk()
        ->assertDontSee('Robin Asker');
});

test('g real GET route as owner after a', function () {
    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_msg_1',
        'event' => 'message.received',
        'message' => [
            'id' => 'm_7802',
            'conversationId' => 'conv_fb_7803',
            'platform' => 'facebook',
            'platformMessageId' => 'mid_7804',
            'direction' => 'incoming',
            'text' => 'Do you open Sundays 7805',
            'attachments' => [],
            'sender' => [
                'id' => 'psid_7806',
                'name' => 'Robin Asker',
            ],
            'sentAt' => '2026-09-27T10:00:00Z',
        ],
        'account' => [
            'accountId' => 'acct_fb_7801',
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

    $this->actingAs($this->owner);

    $this->get(route('x-182.social-queue'))
        ->assertOk()
        ->assertSee('Robin Asker · Facebook')
        ->assertSee('They wrote: Do you open Sundays 7805');
});

test('g no DMs', function () {
    $this->actingAs($this->owner);
    $this->get(route('x-182.social-queue'))
        ->assertOk()
        ->assertSee('No Facebook or Instagram messages yet.');
});
