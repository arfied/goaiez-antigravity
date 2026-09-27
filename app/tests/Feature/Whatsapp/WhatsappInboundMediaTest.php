<?php

declare(strict_types=1);

use App\Jobs\Whatsapp\StoreWhatsappMediaJob;
use App\Livewire\Account\Inbox;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\CWhatsapp\Models\WhatsappConnection;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function () {
    Http::preventStrayRequests();
});

function postZernioMediaWebhook(TestCase $test, array $payloadData): TestResponse
{
    Config::set('credentials.zernio_webhook_secret', 'test_secret');

    $payload = json_encode($payloadData, JSON_THROW_ON_ERROR);
    $sig = hash_hmac('sha256', $payload, 'test_secret');

    return $test->call(
        'POST',
        '/webhooks/zernio',
        [], [], [],
        ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );
}

function setupMediaTest(TestCase $test)
{
    $biz = TestCase::provisionTenant();
    Tenancy::set($biz->id);

    DB::table('gbp_profile_bindings')->insert([
        'business_id' => $biz->id,
        'profile_ref' => 'profile_5801',
    ]);

    $connection = new WhatsappConnection;
    $connection->business_id = $biz->id;
    $connection->status = 'connected';
    $connection->account_ref = 'acct_wa_5802';
    $connection->save();

    Config::set('credentials.zernio_api_key', 'test-key');

    return $biz;
}

it('a', function () {
    $biz = setupMediaTest($this);
    PlatformSetting::write('whatsapp.zernio_enabled', true, 'test');
    Queue::fake();
    PlatformSetting::write('whatsapp.zernio_enabled', true, 'test');

    $payload = [
        'id' => 'evt_wa_5803',
        'event' => 'message.received',
        'message' => [
            'id' => 'm1',
            'conversationId' => 'conv_5804',
            'platform' => 'whatsapp',
            'platformMessageId' => 'wamid.IN5805',
            'direction' => 'incoming',
            'text' => '',
            'attachments' => [
                ['type' => 'image', 'mimeType' => 'image/jpeg', 'url' => 'http://169.254.169.254/latest/meta-data/', 'payload' => ['id' => 'media_8301']],
            ],
            'sender' => [
                'name' => 'Ana',
                'phoneNumber' => '+15125550199',
                'businessScopedUserId' => 'bsuid_5807',
            ],
        ],
        'conversation' => [
            'id' => 'conv_5804',
        ],
        'account' => [
            'accountId' => 'acct_wa_5802',
            'profileId' => 'profile_5801',
        ],
    ];

    postZernioMediaWebhook($this, $payload)->assertStatus(200);

    Tenancy::set($biz->id);
    $msg = Message::query()->first();
    expect($msg)->not->toBeNull();
    $atts = $msg->attachments;
    expect(count($atts))->toBe(1);
    expect($atts[0]['status'])->toBe('pending');
    expect($atts[0]['media_id'])->toBe('media_8301');
    expect($atts[0]['account_ref'])->toBe('acct_wa_5802');

    Queue::assertPushed(StoreWhatsappMediaJob::class, 1);
});

it('b', function () {
    $biz = setupMediaTest($this);
    PlatformSetting::write('whatsapp.zernio_enabled', true, 'test');
    Storage::fake('local');
    PlatformSetting::write('whatsapp.zernio_enabled', true, 'test');
    Http::fake([
        'https://zernio.com/api/v1/whatsapp/media/media_8301*' => Http::response('JPEGBYTES8302', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $payload = [
        'id' => 'evt_wa_5803',
        'event' => 'message.received',
        'message' => [
            'id' => 'm1',
            'conversationId' => 'conv_5804',
            'platform' => 'whatsapp',
            'platformMessageId' => 'wamid.IN5805',
            'direction' => 'incoming',
            'text' => '',
            'attachments' => [
                ['type' => 'image', 'mimeType' => 'image/jpeg', 'url' => 'http://169.254.169.254/latest/meta-data/', 'payload' => ['id' => 'media_8301']],
            ],
            'sender' => [
                'name' => 'Ana',
                'phoneNumber' => '+15125550199',
                'businessScopedUserId' => 'bsuid_5807',
            ],
        ],
        'conversation' => [
            'id' => 'conv_5804',
        ],
        'account' => [
            'accountId' => 'acct_wa_5802',
            'profileId' => 'profile_5801',
        ],
    ];
    postZernioMediaWebhook($this, $payload);
    Tenancy::set($biz->id);
    $msg = Message::query()->first();

    $job = new StoreWhatsappMediaJob($biz->id, $msg->id);
    $job->handle();

    $msg->refresh();
    expect($msg->attachments[0]['status'])->toBe('stored');
    expect($msg->attachments[0]['size'])->toBe(13);

    Storage::disk('local')->assertExists($msg->attachments[0]['path']);
    expect(Storage::disk('local')->get($msg->attachments[0]['path']))->toBe('JPEGBYTES8302');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'zernio.com/api/v1/whatsapp/media/media_8301') &&
               str_contains($request->url(), 'acct_wa_5802') &&
               $request->hasHeader('Authorization');
    });
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '169.254');
    });
    Http::assertSentCount(1);
});

it('c', function () {
    $biz = setupMediaTest($this);
    PlatformSetting::write('whatsapp.zernio_enabled', true, 'test');
    Storage::fake('local');
    PlatformSetting::write('whatsapp.zernio_enabled', true, 'test');
    Http::fake([
        'https://zernio.com/api/v1/whatsapp/media/media_8301*' => Http::response('Expired', 400),
    ]);

    $payload = [
        'id' => 'evt_wa_5803',
        'event' => 'message.received',
        'message' => [
            'id' => 'm1',
            'conversationId' => 'conv_5804',
            'platform' => 'whatsapp',
            'platformMessageId' => 'wamid.IN5805',
            'direction' => 'incoming',
            'text' => '',
            'attachments' => [
                ['type' => 'image', 'mimeType' => 'image/jpeg', 'url' => 'http://169.254.169.254/latest/meta-data/', 'payload' => ['id' => 'media_8301']],
            ],
            'sender' => [
                'name' => 'Ana',
                'phoneNumber' => '+15125550199',
                'businessScopedUserId' => 'bsuid_5807',
            ],
        ],
        'conversation' => [
            'id' => 'conv_5804',
        ],
        'account' => [
            'accountId' => 'acct_wa_5802',
            'profileId' => 'profile_5801',
        ],
    ];
    postZernioMediaWebhook($this, $payload);
    Tenancy::set($biz->id);
    $msg = Message::query()->first();

    $job = new StoreWhatsappMediaJob($biz->id, $msg->id);
    $job->handle();

    $msg->refresh();
    expect($msg->attachments[0]['status'])->toBe('expired');
});

it('d', function () {
    $biz = setupMediaTest($this);
    PlatformSetting::write('whatsapp.zernio_enabled', true, 'test');
    Storage::fake('local');
    PlatformSetting::write('whatsapp.zernio_enabled', true, 'test');
    Http::fake([
        'https://zernio.com/api/v1/whatsapp/media/media_8301*' => Http::response('123456', 200, ['Content-Type' => 'image/jpeg']),
    ]);
    PlatformSetting::write('whatsapp.media_max_bytes', 5, 'test');

    $payload = [
        'id' => 'evt_wa_5803',
        'event' => 'message.received',
        'message' => [
            'id' => 'm1',
            'conversationId' => 'conv_5804',
            'platform' => 'whatsapp',
            'platformMessageId' => 'wamid.IN5805',
            'direction' => 'incoming',
            'text' => '',
            'attachments' => [
                ['type' => 'image', 'mimeType' => 'image/jpeg', 'url' => 'http://169.254.169.254/latest/meta-data/', 'payload' => ['id' => 'media_8301']],
            ],
            'sender' => [
                'name' => 'Ana',
                'phoneNumber' => '+15125550199',
                'businessScopedUserId' => 'bsuid_5807',
            ],
        ],
        'conversation' => [
            'id' => 'conv_5804',
        ],
        'account' => [
            'accountId' => 'acct_wa_5802',
            'profileId' => 'profile_5801',
        ],
    ];
    postZernioMediaWebhook($this, $payload);
    Tenancy::set($biz->id);
    $msg = Message::query()->first();

    $job = new StoreWhatsappMediaJob($biz->id, $msg->id);
    $job->handle();

    $msg->refresh();
    expect($msg->attachments[0]['status'])->toBe('too_large');
});

it('e', function () {
    $biz = setupMediaTest($this);
    PlatformSetting::write('whatsapp.zernio_enabled', true, 'test');
    Queue::fake();
    PlatformSetting::write('whatsapp.zernio_enabled', true, 'test');

    $payload = [
        'id' => 'evt_wa_5803',
        'event' => 'message.received',
        'message' => [
            'id' => 'm1',
            'conversationId' => 'conv_5804',
            'platform' => 'whatsapp',
            'platformMessageId' => 'wamid.IN5805',
            'direction' => 'incoming',
            'text' => '',
            'attachments' => [
                ['type' => 'image', 'mimeType' => 'image/jpeg', 'url' => 'http://169.254.169.254/latest/meta-data/', 'payload' => []],
            ],
            'sender' => [
                'name' => 'Ana',
                'phoneNumber' => '+15125550199',
                'businessScopedUserId' => 'bsuid_5807',
            ],
        ],
        'conversation' => [
            'id' => 'conv_5804',
        ],
        'account' => [
            'accountId' => 'acct_wa_5802',
            'profileId' => 'profile_5801',
        ],
    ];
    postZernioMediaWebhook($this, $payload);
    Tenancy::set($biz->id);
    $msg = Message::query()->first();
    expect($msg->attachments[0]['status'])->toBe('unavailable');

    Queue::assertNotPushed(StoreWhatsappMediaJob::class);
});

it('f', function () {
    $biz = setupMediaTest($this);
    PlatformSetting::write('whatsapp.zernio_enabled', true, 'test');
    Storage::fake('local');
    PlatformSetting::write('whatsapp.zernio_enabled', true, 'test');
    Http::fake([
        'https://zernio.com/api/v1/whatsapp/media/media_8301*' => Http::response('JPEGBYTES8302', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $payload = [
        'id' => 'evt_wa_5803',
        'event' => 'message.received',
        'message' => [
            'id' => 'm1',
            'conversationId' => 'conv_5804',
            'platform' => 'whatsapp',
            'platformMessageId' => 'wamid.IN5805',
            'direction' => 'incoming',
            'text' => '',
            'attachments' => [
                ['type' => 'image', 'mimeType' => 'image/jpeg', 'url' => 'http://169.254.169.254/latest/meta-data/', 'payload' => ['id' => 'media_8301']],
            ],
            'sender' => [
                'name' => 'Ana',
                'phoneNumber' => '+15125550199',
                'businessScopedUserId' => 'bsuid_5807',
            ],
        ],
        'conversation' => [
            'id' => 'conv_5804',
        ],
        'account' => [
            'accountId' => 'acct_wa_5802',
            'profileId' => 'profile_5801',
        ],
    ];
    postZernioMediaWebhook($this, $payload);
    Tenancy::set($biz->id);
    $msg = Message::query()->first();
    $conv = Conversation::query()->first();

    $job = new StoreWhatsappMediaJob($biz->id, $msg->id);
    $job->handle();
    $msg->refresh();

    $user = User::find($biz->owner_user_id);
    Livewire::actingAs($user);
    Livewire::test(Inbox::class)
        ->call('open', $conv->id)
        ->assertSee('Download the image')
        ->call('downloadAttachment', $msg->id, 0)
        ->assertFileDownloaded('whatsapp-'.$msg->id.'-1');

    $other = TestCase::provisionTenant();
    Tenancy::set($other->id);
    $otherUser = User::find($other->owner_user_id);
    Livewire::actingAs($otherUser);
    Livewire::test(Inbox::class)
        ->call('open', $conv->id)
        ->assertStatus(404);
    Tenancy::set($biz->id);
});

it('g', function () {
    $biz = setupMediaTest($this);
    PlatformSetting::write('whatsapp.zernio_enabled', true, 'test');

    $payload = [
        'id' => 'evt_wa_5803',
        'event' => 'message.received',
        'message' => [
            'id' => 'm1',
            'conversationId' => 'conv_5804',
            'platform' => 'whatsapp',
            'platformMessageId' => 'wamid.IN5805',
            'direction' => 'incoming',
            'text' => '',
            'attachments' => [
                ['type' => 'image', 'mimeType' => 'image/jpeg', 'url' => 'http://169.254.169.254/latest/meta-data/', 'payload' => ['id' => 'media_8301']],
            ],
            'sender' => [
                'name' => 'Ana',
                'phoneNumber' => '+15125550199',
                'businessScopedUserId' => 'bsuid_5807',
            ],
        ],
        'conversation' => [
            'id' => 'conv_5804',
        ],
        'account' => [
            'accountId' => 'acct_wa_5802',
            'profileId' => 'profile_5801',
        ],
    ];
    postZernioMediaWebhook($this, $payload);
    Tenancy::set($biz->id);
    $conv = Conversation::query()->first();

    $user = User::find($biz->owner_user_id);
    Livewire::actingAs($user);
    Livewire::test(Inbox::class)
        ->call('open', $conv->id)
        ->assertSee('Saving the image…')
        ->assertDontSee('Download the image');
});
