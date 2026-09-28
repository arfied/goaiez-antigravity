<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Enums\StoredObjectKind;
use App\Jobs\Whatsapp\StoreWhatsappMediaJob;
use App\Livewire\Account\Inbox;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\CWhatsapp\Models\WhatsappConnection;
use App\Services\Storage\StorageFootprint;
use App\Services\Storage\StorageRetention;
use App\Services\Zernio\ZernioWhatsappMedia;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

function postRetentionWebhook(TestCase $test, array $payloadData): TestResponse
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

function setupRetentionTest(TestCase $test)
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

function buildStoredMedia(TestCase $test, $biz)
{
    PlatformSetting::write('whatsapp.zernio_enabled', true, 'test');
    Storage::fake(ZernioWhatsappMedia::DISK);
    Http::fake([
        'https://zernio.com/api/v1/whatsapp/media/media_8331*' => Http::response('JPEGBYTES8332', 200, ['Content-Type' => 'image/jpeg']),
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
                ['type' => 'image', 'mimeType' => 'image/jpeg', 'url' => 'http://169.254.169.254/latest/meta-data/', 'payload' => ['id' => 'media_8331']],
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
    postRetentionWebhook($test, $payload);
    Tenancy::set($biz->id);
    $msg = Message::query()->first();

    $job = new StoreWhatsappMediaJob($biz->id, $msg->id);
    $job->handle();

    $msg->refresh();

    return $msg;
}

it('a', function () {
    $biz = setupRetentionTest($this);
    $msg = buildStoredMedia($this, $biz);

    $sweep = app(StorageRetention::class)->prune(StoredObjectKind::WhatsappMedia);

    expect($sweep->skipped)->toBeTrue();
    $msg->refresh();
    expect($msg->attachments[0]['status'])->toBe('stored');
    Storage::disk(ZernioWhatsappMedia::DISK)->assertExists($msg->attachments[0]['path']);
});

it('b', function () {
    $biz = setupRetentionTest($this);
    $msg = buildStoredMedia($this, $biz);
    $oldPath = $msg->attachments[0]['path'];

    PlatformSetting::write('storage.retention_days.whatsapp_media', 30, 'test');

    Message::query()->where('id', $msg->id)->update([
        'created_at' => now()->subDays(31),
    ]);

    $sweep = app(StorageRetention::class)->prune(StoredObjectKind::WhatsappMedia);

    expect($sweep->pruned)->toBe(1);
    $msg->refresh();
    expect($msg->attachments[0]['status'])->toBe('pruned');
    expect($msg->attachments[0]['path'])->toBeNull();
    Storage::disk(ZernioWhatsappMedia::DISK)->assertMissing($oldPath);
});

it('c', function () {
    $biz = setupRetentionTest($this);
    $msg = buildStoredMedia($this, $biz);

    PlatformSetting::write('storage.retention_days.whatsapp_media', 30, 'test');

    Message::query()->where('id', $msg->id)->update([
        'created_at' => now()->subDays(1),
    ]);

    $sweep = app(StorageRetention::class)->prune(StoredObjectKind::WhatsappMedia);

    expect($sweep->pruned)->toBe(0);
    $msg->refresh();
    expect($msg->attachments[0]['status'])->toBe('stored');
    Storage::disk(ZernioWhatsappMedia::DISK)->assertExists($msg->attachments[0]['path']);
});

it('d', function () {
    $biz = setupRetentionTest($this);
    $msg = buildStoredMedia($this, $biz);

    $footprint = app(StorageFootprint::class)->forTenant();

    $wa = collect($footprint)->firstWhere('kind', StoredObjectKind::WhatsappMedia);
    expect($wa->objects)->toBe(1);
    expect($wa->bytes)->toBe(13);

    PlatformSetting::write('storage.retention_days.whatsapp_media', 30, 'test');
    Message::query()->where('id', $msg->id)->update([
        'created_at' => now()->subDays(31),
    ]);
    app(StorageRetention::class)->prune(StoredObjectKind::WhatsappMedia);

    $footprintAfter = app(StorageFootprint::class)->forTenant();
    $waAfter = collect($footprintAfter)->firstWhere('kind', StoredObjectKind::WhatsappMedia);
    expect($waAfter->objects)->toBe(0);
});

it('e', function () {
    $biz1 = setupRetentionTest($this);
    $msg = buildStoredMedia($this, $biz1);

    Message::query()->where('id', $msg->id)->update([
        'created_at' => now()->subDays(31),
    ]);

    $biz2 = TestCase::provisionTenant();
    Tenancy::set($biz2->id);

    PlatformSetting::write('storage.retention_days.whatsapp_media', 30, 'test');
    app(StorageRetention::class)->prune(StoredObjectKind::WhatsappMedia);

    Tenancy::set($biz1->id);
    $msg->refresh();
    expect($msg->attachments[0]['status'])->toBe('stored');
});

it('f', function () {
    $biz = setupRetentionTest($this);
    $msg = buildStoredMedia($this, $biz);

    PlatformSetting::write('storage.retention_days.whatsapp_media', 30, 'test');
    Message::query()->where('id', $msg->id)->update([
        'created_at' => now()->subDays(31),
    ]);
    app(StorageRetention::class)->prune(StoredObjectKind::WhatsappMedia);

    $conv = Conversation::find($msg->conversation_id);
    $user = User::find($biz->owner_user_id);
    Livewire::actingAs($user);
    Livewire::test(Inbox::class)
        ->call('open', $conv->id)
        ->assertSee('was deleted after your media retention period')
        ->assertDontSee('Download the image');
});

it('g', function () {
    $biz = setupRetentionTest($this);

    PlatformSetting::write('storage.retention.chunk', 1, 'test');
    PlatformSetting::write('storage.retention_days.whatsapp_media', 30, 'test');

    Storage::fake(ZernioWhatsappMedia::DISK);
    PlatformSetting::write('whatsapp.zernio_enabled', true, 'test');

    $msgs = [];
    foreach ([
        ['phone' => '+15125550191', 'media' => 'media_8351', 'evt' => 'evt_wa_8351', 'conv' => 'conv_8351', 'msgId' => 'm_8351', 'wamid' => 'wamid.8351'],
        ['phone' => '+15125550192', 'media' => 'media_8352', 'evt' => 'evt_wa_8352', 'conv' => 'conv_8352', 'msgId' => 'm_8352', 'wamid' => 'wamid.8352'],
        ['phone' => '+15125550193', 'media' => 'media_8353', 'evt' => 'evt_wa_8353', 'conv' => 'conv_8353', 'msgId' => 'm_8353', 'wamid' => 'wamid.8353'],
    ] as $m) {
        Http::fake([
            "https://zernio.com/api/v1/whatsapp/media/{$m['media']}*" => Http::response('JPEGBYTES', 200, ['Content-Type' => 'image/jpeg']),
        ]);
        $payload = [
            'id' => $m['evt'],
            'event' => 'message.received',
            'message' => [
                'id' => $m['msgId'],
                'conversationId' => $m['conv'],
                'platform' => 'whatsapp',
                'platformMessageId' => $m['wamid'],
                'direction' => 'incoming',
                'text' => '',
                'attachments' => [
                    ['type' => 'image', 'mimeType' => 'image/jpeg', 'url' => 'http://169.254.169.254/latest/meta-data/', 'payload' => ['id' => $m['media']]],
                ],
                'sender' => [
                    'name' => 'Ana',
                    'phoneNumber' => $m['phone'],
                    'businessScopedUserId' => 'bsuid_'.$m['media'],
                ],
            ],
            'conversation' => ['id' => $m['conv']],
            'account' => ['accountId' => 'acct_wa_5802', 'profileId' => 'profile_5801'],
        ];
        postRetentionWebhook($this, $payload);
        Tenancy::set($biz->id);
        $msg = Message::query()->orderBy('id', 'desc')->first();
        $job = new StoreWhatsappMediaJob($biz->id, $msg->id);
        $job->handle();
        $msg->refresh();
        $msgs[] = $msg;
    }

    Message::query()->whereIn('id', collect($msgs)->pluck('id'))->update([
        'created_at' => now()->subDays(31),
    ]);

    $sweep = app(StorageRetention::class)->prune(StoredObjectKind::WhatsappMedia);

    expect($sweep->pruned)->toBe(3);

    foreach ($msgs as $msg) {
        $msg->refresh();
        expect($msg->attachments[0]['status'])->toBe('pruned');
        expect($msg->attachments[0]['path'])->toBeNull();
    }
});

it('labels the kind for what it holds', function () {
    expect(StoredObjectKind::WhatsappMedia->label())->toBe('Photos and files from messages');
    expect(StoredObjectKind::WhatsappMedia->retentionKey())->toBe('storage.retention_days.whatsapp_media');
});
