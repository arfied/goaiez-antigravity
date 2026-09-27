<?php

declare(strict_types=1);

use App\Jobs\Whatsapp\StoreWhatsappMediaJob;
use App\Models\GbpProfileBinding;
use App\Models\Message;
use App\Models\User;
use App\Models\ZernioAccountBinding;
use App\Modules\X182\Models\SocialAccount;
use App\Modules\X182\Ui\SocialQueue;
use App\Services\Config\DefaultsRegistry;
use App\Services\Conversations\ConversationThreads;
use App\Services\Fetch\PublicAddressGuard;
use App\Services\Zernio\ZernioWhatsappMedia;
use App\Support\Tenancy;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function () {
    $this->biz = TestCase::provisionTenant(['name' => 'Social Biz', 'currency' => 'USD']);
    $this->owner = User::find($this->biz->owner_user_id);

    Config::set('credentials.zernio_webhook_secret', 'test_secret');
    Config::set('credentials.zernio_api_key', 'test_key');
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');

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

test('a dm with media', function () {
    Queue::fake();
    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_msg_1',
        'event' => 'message.received',
        'message' => [
            'id' => 'm_7802',
            'conversationId' => 'conv_8341',
            'platform' => 'facebook',
            'platformMessageId' => 'mid_8342',
            'direction' => 'incoming',
            'text' => null,
            'attachments' => [
                ['type' => 'image', 'url' => 'http://169.254.169.254/x'],
            ],
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

    $msg = Message::firstOrFail();
    expect($msg->attachments[0])->toMatchArray([
        'source' => 'meta',
        'status' => 'pending',
        'conversation_ref' => 'conv_8341',
        'platform_message_id' => 'mid_8342',
        'index' => 0,
    ]);
    expect($msg->attachments[0])->not->toHaveKey('url');

    Queue::assertPushed(StoreWhatsappMediaJob::class);
});

test('b run the job', function () {
    Storage::fake(ZernioWhatsappMedia::DISK);
    Http::preventStrayRequests();

    $this->app->instance(PublicAddressGuard::class, new class extends PublicAddressGuard
    {
        protected function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });

    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_msg_1',
        'event' => 'message.received',
        'message' => [
            'id' => 'm_7802',
            'conversationId' => 'conv_8341',
            'platform' => 'facebook',
            'platformMessageId' => 'mid_8342',
            'direction' => 'incoming',
            'text' => null,
            'attachments' => [
                ['type' => 'image'],
            ],
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
    $this->call('POST', '/webhooks/zernio', [], [], [], ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload);

    Tenancy::set($this->biz->id);
    $msg = Message::firstOrFail();

    Http::fake([
        'https://zernio.com/api/v1/inbox/conversations/conv_8341/messages/mid_8342/attachments/0*' => Http::response(['status' => 'success', 'url' => 'https://scontent.example-cdn.com/img.jpg', 'refreshed' => false], 200),
        'https://scontent.example-cdn.com/img.jpg' => Http::response('IMGBYTES8343', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    app(Dispatcher::class)->dispatchNow(new StoreWhatsappMediaJob($this->biz->id, $msg->id));

    $msg->refresh();
    expect($msg->attachments[0]['status'])->toBe('stored');
    Storage::disk(ZernioWhatsappMedia::DISK)->assertExists($msg->attachments[0]['path']);

    Http::assertSentCount(2);
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'accountId=') && str_contains($request->url(), 'format=json');
    });
});

test('c resolve returns 127.0.0.1', function () {
    Storage::fake(ZernioWhatsappMedia::DISK);
    Http::preventStrayRequests();

    $this->app->instance(PublicAddressGuard::class, new class extends PublicAddressGuard
    {
        protected function resolve(string $host): array
        {
            return ['127.0.0.1'];
        }
    });

    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_msg_1',
        'event' => 'message.received',
        'message' => [
            'id' => 'm_7802',
            'conversationId' => 'conv_8341',
            'platform' => 'facebook',
            'platformMessageId' => 'mid_8342',
            'direction' => 'incoming',
            'text' => null,
            'attachments' => [
                ['type' => 'image'],
            ],
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
    $this->call('POST', '/webhooks/zernio', [], [], [], ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload);

    Tenancy::set($this->biz->id);
    $msg = Message::firstOrFail();

    Http::fake([
        'https://zernio.com/api/v1/inbox/conversations/conv_8341/messages/mid_8342/attachments/0*' => Http::response(['status' => 'success', 'url' => 'https://scontent.example-cdn.com/img.jpg', 'refreshed' => false], 200),
    ]);

    app(Dispatcher::class)->dispatchNow(new StoreWhatsappMediaJob($this->biz->id, $msg->id));

    $msg->refresh();
    expect($msg->attachments[0]['status'])->toBe('failed');

    Http::assertSentCount(1);
});

test('d cdn answers 302', function () {
    Storage::fake(ZernioWhatsappMedia::DISK);
    Http::preventStrayRequests();

    $this->app->instance(PublicAddressGuard::class, new class extends PublicAddressGuard
    {
        protected function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });

    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_msg_1',
        'event' => 'message.received',
        'message' => [
            'id' => 'm_7802',
            'conversationId' => 'conv_8341',
            'platform' => 'facebook',
            'platformMessageId' => 'mid_8342',
            'direction' => 'incoming',
            'text' => null,
            'attachments' => [
                ['type' => 'image'],
            ],
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
    $this->call('POST', '/webhooks/zernio', [], [], [], ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload);

    Tenancy::set($this->biz->id);
    $msg = Message::firstOrFail();

    Http::fake([
        'https://zernio.com/api/v1/inbox/conversations/conv_8341/messages/mid_8342/attachments/0*' => Http::response(['status' => 'success', 'url' => 'https://scontent.example-cdn.com/img.jpg', 'refreshed' => false], 200),
        'https://scontent.example-cdn.com/img.jpg' => Http::response('', 302, ['Location' => 'http://169.254.169.254/target']),
    ]);

    app(Dispatcher::class)->dispatchNow(new StoreWhatsappMediaJob($this->biz->id, $msg->id));

    $msg->refresh();
    expect($msg->attachments[0]['status'])->toBe('failed');
    Http::assertSentCount(2);
});

test('e a phi business', function () {
    Queue::fake();

    $phiBiz = TestCase::provisionTenant(['name' => 'PHI Biz', 'currency' => 'USD']);
    DB::table('businesses')->where('id', $phiBiz->id)->update(['data_classification' => 'phi']);

    Tenancy::set($phiBiz->id);
    GbpProfileBinding::create(['business_id' => $phiBiz->id, 'profile_ref' => 'profile_phi']);
    $phiAccount = SocialAccount::create(['business_id' => $phiBiz->id, 'platform' => 'facebook', 'status' => 'connected', 'is_connected' => true, 'provider_profile_ref' => 'profile_phi']);
    $phiAccount->account_ref = 'acct_phi';
    $phiAccount->save();
    ZernioAccountBinding::create(['account_ref' => 'acct_phi', 'profile_ref' => 'profile_phi', 'platform' => 'facebook']);

    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_msg_phi',
        'event' => 'message.received',
        'message' => [
            'id' => 'm_phi',
            'conversationId' => 'conv_phi',
            'platform' => 'facebook',
            'platformMessageId' => 'mid_phi',
            'direction' => 'incoming',
            'text' => null,
            'attachments' => [
                ['type' => 'image', 'url' => 'http://169.254.169.254/x'],
            ],
            'sender' => [
                'id' => 'psid_phi',
                'name' => 'Robin Asker',
            ],
            'sentAt' => '2026-09-27T10:00:00Z',
        ],
        'account' => [
            'accountId' => 'acct_phi',
        ],
    ], JSON_THROW_ON_ERROR);

    $sig = hash_hmac('sha256', $payload, 'test_secret');
    $this->call('POST', '/webhooks/zernio', [], [], [], ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload);

    Tenancy::set($phiBiz->id);

    $msg = Message::firstOrFail();
    expect($msg->attachments[0])->toMatchArray([
        'type' => 'image',
        'status' => 'refused_health_tenant',
    ]);
    Queue::assertNotPushed(StoreWhatsappMediaJob::class);
});

test('f the social queue', function () {
    Storage::fake(ZernioWhatsappMedia::DISK);
    Storage::disk(ZernioWhatsappMedia::DISK)->put('1/m/1.jpg', 'fake-bytes');

    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_msg_1',
        'event' => 'message.received',
        'message' => [
            'id' => 'm_7802',
            'conversationId' => 'conv_8341',
            'platform' => 'facebook',
            'platformMessageId' => 'mid_8342',
            'direction' => 'incoming',
            'text' => null,
            'attachments' => [
                ['type' => 'image'],
            ],
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
    $this->call('POST', '/webhooks/zernio', [], [], [], ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload);

    Tenancy::set($this->biz->id);
    $msg = Message::firstOrFail();

    app(ConversationThreads::class)->markAttachment($msg, 0, [
        'status' => 'stored',
        'path' => '1/m/1.jpg',
        'size' => 10,
        'mime' => 'image/jpeg',
    ]);

    $convId = $msg->conversation_id;

    $this->actingAs($this->owner);

    Livewire::test(SocialQueue::class)
        ->assertSee('Download the image')
        ->call('downloadDmAttachment', $convId, $msg->id, 0)
        ->assertFileDownloaded('attachment-'.$msg->id.'-1');

    Livewire::test(SocialQueue::class)
        ->call('downloadDmAttachment', $convId, 9999, 0)
        ->assertStatus(404);
});

test('g a resolve URL of http gives status failed and no CDN request', function () {
    Storage::fake(ZernioWhatsappMedia::DISK);
    Http::preventStrayRequests();

    $this->app->instance(PublicAddressGuard::class, new class extends PublicAddressGuard
    {
        protected function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });

    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_msg_1',
        'event' => 'message.received',
        'message' => [
            'id' => 'm_7802',
            'conversationId' => 'conv_8341',
            'platform' => 'facebook',
            'platformMessageId' => 'mid_8342',
            'direction' => 'incoming',
            'text' => null,
            'attachments' => [
                ['type' => 'image'],
            ],
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
    $this->call('POST', '/webhooks/zernio', [], [], [], ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $payload);

    Tenancy::set($this->biz->id);
    $msg = Message::firstOrFail();

    Http::fake([
        'https://zernio.com/api/v1/inbox/conversations/conv_8341/messages/mid_8342/attachments/0*' => Http::response(['status' => 'success', 'url' => 'http://scontent.example-cdn.com/img.jpg', 'refreshed' => false], 200),
    ]);

    app(Dispatcher::class)->dispatchNow(new StoreWhatsappMediaJob($this->biz->id, $msg->id));

    $msg->refresh();
    expect($msg->attachments[0]['status'])->toBe('failed');
    Http::assertSentCount(1);
});
