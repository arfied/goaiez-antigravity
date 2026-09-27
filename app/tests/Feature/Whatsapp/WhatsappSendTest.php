<?php

declare(strict_types=1);

namespace Tests\Feature\Whatsapp;

use App\Models\GbpProfileBinding;
use App\Modules\CWhatsapp\Actions\WhatsappSendAction;
use App\Modules\CWhatsapp\Domain\WhatsappEngine;
use App\Modules\CWhatsapp\Events\WhatsappSent;
use App\Modules\CWhatsapp\Models\WhatsappConnection;
use App\Modules\CWhatsapp\Models\WhatsappDelivery;
use App\Modules\CWhatsapp\Models\WhatsappSession;
use App\Modules\CWhatsapp\Models\WhatsappTemplate;
use App\Services\Config\DefaultsRegistry;
use App\Services\Zernio\ZernioWhatsappStatuses;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappSendTest extends TestCase
{
    private WhatsappEngine $engine;

    private WhatsappSendAction $sendAction;

    private $biz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(WhatsappEngine::class);
        $this->sendAction = new WhatsappSendAction($this->engine);

        $this->biz = TestCase::provisionTenant(['name' => 'Test Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$this->biz->id}'");

        app(DefaultsRegistry::class)->set('whatsapp.zernio_enabled', true, 'test');
        config(['credentials.zernio_api_key' => 'test_key']);

        $this->profileRef = 'profile_'.uniqid();
        GbpProfileBinding::create(['business_id' => $this->biz->id, 'profile_ref' => $this->profileRef]);
    }

    private function setupConnection()
    {
        $this->accountRef = 'acct_wa_'.uniqid();

        return WhatsappConnection::forceCreate([
            'business_id' => $this->biz->id,
            'account_ref' => $this->accountRef,
            'status' => 'connected',
        ]);
    }

    private function setupSession($phone = '+15558889999', $isInside = true, $convId = 'conv_6202')
    {
        return WhatsappSession::create([
            'business_id' => $this->biz->id,
            'recipient_phone' => $phone,
            'last_inbound_at' => $isInside ? Carbon::now()->subHours(23) : Carbon::now()->subHours(25),
            'session_window_expires_at' => $isInside ? Carbon::now()->addHour() : Carbon::now()->subHour(),
            'is_window_open' => $isInside,
            'zernio_conversation_id' => $convId,
        ]);
    }

    public function test_a_reply_request_carries_header_and_body()
    {
        Event::fake([WhatsappSent::class]);
        $this->setupConnection();
        $this->setupSession();

        Http::fake([
            'zernio.com/api/v1/inbox/conversations/conv_6202/messages' => Http::response(['success' => true, 'data' => ['messageId' => "wamid_h_{$this->accountRef}", 'conversationId' => 'conv_6202']]),
        ]);

        $this->sendAction->handle($this->biz->id, '+15558889999', 'hello');

        $row = WhatsappDelivery::where('business_id', $this->biz->id)->first();

        Http::assertSent(function (Request $request) use ($row) {
            return $request->url() === 'https://zernio.com/api/v1/inbox/conversations/conv_6202/messages' &&
                   $request->header('Idempotency-Key')[0] === 'whatsapp-message-'.$row->id &&
                   $request['accountId'] === $this->accountRef &&
                   $request['message'] === 'hello';
        });
    }

    public function test_b_200_no_message_id_unconfirmed_no_event()
    {
        Event::fake([WhatsappSent::class]);
        $this->setupConnection();
        $this->setupSession();

        Http::fake([
            'zernio.com/api/v1/inbox/conversations/conv_6202/messages' => Http::response(['success' => true, 'data' => ['conversationId' => 'conv_6202']]),
        ]);

        $res = $this->sendAction->handle($this->biz->id, '+15558889999', 'hello');
        $this->assertEquals('unconfirmed', $res['status']);
        Event::assertNotDispatched(WhatsappSent::class);
    }

    public function test_c_400_window_closed()
    {
        $this->setupConnection();
        $session = $this->setupSession();

        Http::fake([
            'zernio.com/api/v1/inbox/conversations/conv_6202/messages' => Http::response(['error' => '(#131047) Re-engagement message', 'code' => 'platform_api_error'], 400),
        ]);

        $res = $this->sendAction->handle($this->biz->id, '+15558889999', 'hello');
        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('OUTSIDE_24H_WINDOW_TEMPLATE_REQUIRED', $res['refusal_code']);

        $this->assertFalse($session->refresh()->is_window_open);
    }

    public function test_d_500_is_unconfirmed_and_exact_one_request()
    {
        $this->setupConnection();
        $this->setupSession();

        Http::fake([
            'zernio.com/api/v1/inbox/conversations/conv_6202/messages' => Http::response('Server error', 500),
        ]);

        $res = $this->sendAction->handle($this->biz->id, '+15558889999', 'hello');
        $this->assertEquals('unconfirmed', $res['status']);
        Http::assertSentCount(1);
    }

    public function test_e_connection_exception_is_unconfirmed()
    {
        $this->setupConnection();
        $this->setupSession();

        Http::fake(fn () => throw new ConnectionException('down'));

        $res = $this->sendAction->handle($this->biz->id, '+15558889999', 'hello');
        $this->assertEquals('unconfirmed', $res['status']);
    }

    public function test_f_no_connection_refused()
    {
        $this->setupSession();
        $res = $this->sendAction->handle($this->biz->id, '+15558889999', 'hello');

        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('WHATSAPP_NOT_CONNECTED', $res['refusal_code']);
        Http::assertNothingSent();
    }

    public function test_g_template_send()
    {
        $this->setupConnection();
        $session = $this->setupSession('+15558889999', false, null);
        WhatsappTemplate::create([
            'business_id' => $this->biz->id,
            'name' => 'hello_world',
            'language' => 'en',
            'status' => 'approved',
            'category' => 'utility',
            'body_text' => 'hello',
        ]);

        Http::fake([
            'zernio.com/api/v1/inbox/conversations' => Http::response(['success' => true, 'data' => ['messageId' => uniqid('wamid_'), 'conversationId' => 'conv_6205', 'participantId' => '15558889999']], 201),
        ]);

        $res = $this->sendAction->handle($this->biz->id, '+15558889999', 'hello', 'hello_world');
        $this->assertEquals('sent', $res['status']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://zernio.com/api/v1/inbox/conversations' &&
                   $request['participantId'] === '15558889999' &&
                   $request['templateName'] === 'hello_world';
        });

        $this->assertEquals('conv_6205', $session->refresh()->zernio_conversation_id);
    }

    public function test_h_status_forward_only()
    {
        $this->setupConnection();
        WhatsappDelivery::forceCreate([
            'business_id' => $this->biz->id,
            'mode' => 'free_form',
            'status' => 'sent',
            'provider_message_ref' => "wamid_h_{$this->accountRef}",
        ]);

        $webhook = app(ZernioWhatsappStatuses::class);

        // delivered
        $webhook->handle([
            'type' => 'message.delivered',
            'account' => ['accountId' => $this->accountRef, 'profileId' => $this->profileRef],
            'message' => ['platform' => 'whatsapp', 'platformMessageId' => "wamid_h_{$this->accountRef}"],
        ]);

        $row = WhatsappDelivery::where('provider_message_ref', "wamid_h_{$this->accountRef}")->first();
        $this->assertEquals('delivered', $row->status);

        // try sent again
        $webhook->handle([
            'type' => 'message.sent',
            'account' => ['accountId' => $this->accountRef, 'profileId' => $this->profileRef],
            'message' => ['platform' => 'whatsapp', 'platformMessageId' => "wamid_h_{$this->accountRef}"],
        ]);

        $row = WhatsappDelivery::where('provider_message_ref', "wamid_h_{$this->accountRef}")->first();
        $this->assertEquals('delivered', $row->status); // stays delivered
    }

    public function test_i_another_tenant_account_unbound()
    {
        $webhook = app(ZernioWhatsappStatuses::class);
        $res = $webhook->handle([
            'type' => 'message.delivered',
            'account' => ['accountId' => 'acct_other', 'profileId' => 'profile_other'],
            'message' => ['platform' => 'whatsapp', 'platformMessageId' => uniqid('wamid_')],
        ]);

        $this->assertEquals('unbound', $res);
    }

    public function test_j_message_failed_with_code()
    {
        $this->setupConnection();
        WhatsappDelivery::forceCreate([
            'business_id' => $this->biz->id,
            'mode' => 'free_form',
            'status' => 'sent',
            'provider_message_ref' => "wamid_j_{$this->accountRef}",
        ]);

        $webhook = app(ZernioWhatsappStatuses::class);

        $webhook->handle([
            'type' => 'message.failed',
            'account' => ['accountId' => $this->accountRef, 'profileId' => $this->profileRef],
            'message' => ['platform' => 'whatsapp', 'platformMessageId' => "wamid_j_{$this->accountRef}", 'error' => ['code' => '131026']],
        ]);

        $row = WhatsappDelivery::where('provider_message_ref', "wamid_j_{$this->accountRef}")->first();
        $this->assertEquals('failed', $row->status);
        $this->assertEquals('131026', $row->error_code);
    }
}
