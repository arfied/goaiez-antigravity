<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\ZernioWebhookEvent;
use App\Modules\CWhatsapp\Models\WhatsappConnection;
use App\Modules\CWhatsapp\Models\WhatsappSession;
use App\Modules\X01\Events\ConversationUpdated;
use App\Modules\X121\Models\Person;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function () {
    Http::preventStrayRequests();
});

function postZernioWebhook(TestCase $test, array $payloadData): TestResponse
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

it('handles incoming whatsapp message successfully', function () {
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

    $payload = [
        'id' => 'evt_wa_5803',
        'event' => 'message.received',
        'message' => [
            'id' => 'm1',
            'conversationId' => 'conv_5804',
            'platform' => 'whatsapp',
            'platformMessageId' => 'wamid.IN5805',
            'direction' => 'incoming',
            'text' => 'Distinctive inbound 5806',
            'attachments' => [],
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

    Event::listen(ConversationUpdated::class, function ($e) use (&$convUpdated) {
        $convUpdated = $e;
    });
    $response = postZernioWebhook($this, $payload);
    $response->assertStatus(200);
    expect($response->json('ok'))->toBeTrue();

    Tenancy::set($biz->id);

    // assert session refs
    $session = WhatsappSession::where('business_id', $biz->id)
        ->where('zernio_conversation_id', 'conv_5804')
        ->where('zernio_participant_ref', 'bsuid_5807')
        ->first();
    expect($session)->not->toBeNull();

    // assert unified inbox has a conversation for +15125550199 whose message body is Distinctive inbound 5806
    $person = Person::where('business_id', $biz->id)->where('phone', '+15125550199')->first();
    expect($person)->not->toBeNull();

    $conv = Conversation::where('business_id', $biz->id)->where('person_id', $person->id)->first();
    expect($conv)->not->toBeNull();
    expect($convUpdated->messageSnippet)->toContain('Distinctive inbound 5806');
    $this->assertEquals(1, Conversation::where('business_id', $biz->id)->where('person_id', $person->id)->count());
});

it('ignores duplicate event id', function () {
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

    ZernioWebhookEvent::create([
        'event_id' => 'evt_wa_5803',
        'event_type' => 'message.received',
        'received_at' => now(),
    ]);

    $payload = [
        'id' => 'evt_wa_5803',
        'event' => 'message.received',
    ];

    Event::listen(ConversationUpdated::class, function ($e) use (&$convUpdated) {
        $convUpdated = $e;
    });
    $response = postZernioWebhook($this, $payload);
    $response->assertStatus(200);
});

it('ignores outgoing direction', function () {
    $payload = [
        'id' => 'evt_wa_5808',
        'event' => 'message.received',
        'message' => [
            'platform' => 'whatsapp',
            'direction' => 'outgoing',
        ],
    ];

    Event::listen(ConversationUpdated::class, function ($e) use (&$convUpdated) {
        $convUpdated = $e;
    });
    $response = postZernioWebhook($this, $payload);
    $response->assertStatus(200);
});

it('returns unbound for unknown account', function () {
    $biz = TestCase::provisionTenant();
    Tenancy::set($biz->id);

    DB::table('gbp_profile_bindings')->insert([
        'business_id' => $biz->id,
        'profile_ref' => 'profile_5801',
    ]);

    $payload = [
        'id' => 'evt_wa_5809',
        'event' => 'message.received',
        'message' => [
            'platform' => 'whatsapp',
            'direction' => 'incoming',
        ],
        'account' => [
            'accountId' => 'acct_other',
            'profileId' => 'profile_5801',
        ],
    ];

    Event::listen(ConversationUpdated::class, function ($e) use (&$convUpdated) {
        $convUpdated = $e;
    });
    $response = postZernioWebhook($this, $payload);
    $response->assertStatus(200);
});

it('ignores other platforms like instagram', function () {
    $payload = [
        'id' => 'evt_wa_5810',
        'event' => 'message.received',
        'message' => [
            'platform' => 'instagram',
            'direction' => 'incoming',
        ],
    ];

    Event::listen(ConversationUpdated::class, function ($e) use (&$convUpdated) {
        $convUpdated = $e;
    });
    $response = postZernioWebhook($this, $payload);
    $response->assertStatus(200);
});

it('handles null phone number but saves session refs', function () {
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

    $payload = [
        'id' => 'evt_wa_5811',
        'event' => 'message.received',
        'message' => [
            'id' => 'm2',
            'conversationId' => 'conv_5811',
            'platform' => 'whatsapp',
            'platformMessageId' => 'wamid.IN5811',
            'direction' => 'incoming',
            'text' => 'Distinctive inbound null phone',
            'attachments' => [],
            'sender' => [
                'name' => 'Ana',
                'phoneNumber' => null,
                'businessScopedUserId' => 'bsuid_5811',
            ],
        ],
        'conversation' => [
            'id' => 'conv_5811',
        ],
        'account' => [
            'accountId' => 'acct_wa_5802',
            'profileId' => 'profile_5801',
        ],
    ];

    Event::listen(ConversationUpdated::class, function ($e) use (&$convUpdated) {
        $convUpdated = $e;
    });
    $response = postZernioWebhook($this, $payload);
    $response->assertStatus(200);

    Tenancy::set($biz->id);

    $session = WhatsappSession::where('business_id', $biz->id)
        ->where('zernio_conversation_id', 'conv_5811')
        ->first();
    expect($session)->not->toBeNull();

    $persons = Person::where('business_id', $biz->id)->count();
    expect($persons)->toBe(0);
});
