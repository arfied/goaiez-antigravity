<?php

declare(strict_types=1);

namespace Tests\Modules\CWhatsapp;

use App\Models\Conversation;
use App\Modules\CWhatsapp\Actions\TemplateSubmitAction;
use App\Modules\CWhatsapp\Actions\WhatsappConnectAction;
use App\Modules\CWhatsapp\Actions\WhatsappSendAction;
use App\Modules\CWhatsapp\Domain\WhatsappEngine;
use App\Modules\CWhatsapp\Events\TemplateApproved;
use App\Modules\CWhatsapp\Events\WhatsappSent;
use App\Modules\CWhatsapp\Events\WhatsappSessionOpened;
use App\Modules\CWhatsapp\Models\WhatsappConnection;
use App\Modules\CWhatsapp\Models\WhatsappSession;
use App\Modules\X121\Models\Person;
use App\Modules\X204\Domain\ConsentService;
use App\Services\Config\DefaultsRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CWhatsappTest extends TestCase
{
    private WhatsappEngine $engine;

    private WhatsappSendAction $sendAction;

    private WhatsappConnectAction $connectAction;

    private TemplateSubmitAction $templateAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(WhatsappEngine::class);
        $this->sendAction = new WhatsappSendAction($this->engine);
        $this->connectAction = new WhatsappConnectAction($this->engine);
        $this->templateAction = new TemplateSubmitAction;
    }

    /**
     * TEST ANCHOR
     * a send 25 hours after the last inbound with no approved template is refused with a plain reason, never attempted;
     * a send 23 hours after goes free-form
     */
    public function test_anchor_whatsapp_24h_window_and_template_refusal(): void
    {
        Event::fake([WhatsappSent::class, WhatsappSessionOpened::class, TemplateApproved::class]);

        $biz = TestCase::provisionTenant(['name' => 'WhatsApp Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customerPhone = '+15558889999';

        // 1. Inbound received 23 hours ago (within 24h window) -> Send goes FREE-FORM (TEST ANCHOR)
        $session = WhatsappSession::create([
            'business_id' => $biz->id,
            'recipient_phone' => $customerPhone,
            'last_inbound_at' => Carbon::now()->subHours(23),
            'session_window_expires_at' => Carbon::now()->addHour(),
            'is_window_open' => true,
            'zernio_conversation_id' => 'conv_6202',
        ]);

        app(DefaultsRegistry::class)->set('whatsapp.zernio_enabled', true, 'test');
        config(['credentials.zernio_api_key' => 'test_key']);
        WhatsappConnection::forceCreate([
            'business_id' => $biz->id,
            'account_ref' => 'acct_wa_6201',
            'status' => 'connected',
        ]);
        Http::fake([
            'zernio.com/api/v1/whatsapp/templates' => Http::response(['success' => true, 'template' => ['id' => 'tpl_6206', 'status' => 'PENDING']], 200),
            'zernio.com/api/v1/inbox/conversations/conv_6202/messages' => Http::response(['success' => true, 'data' => ['messageId' => 'wamid.OUT6203', 'conversationId' => 'conv_6202']]),
            'zernio.com/api/v1/inbox/conversations' => Http::response(['success' => true, 'data' => ['messageId' => 'wamid.T6204', 'conversationId' => 'conv_6205', 'participantId' => '15558889999']], 201),
        ]);

        $freeFormRes = $this->sendAction->handle(
            businessId: $biz->id,
            recipientPhone: $customerPhone,
            messageText: 'Your technician is on the way!'
        );

        $this->assertEquals('sent', $freeFormRes['status']);
        $this->assertEquals('free_form', $freeFormRes['mode']);
        Event::assertDispatched(WhatsappSent::class);

        // 2. Inbound received 25 hours ago (outside 24h window) with no template -> REFUSED with plain reason (TEST ANCHOR)
        $session->update([
            'last_inbound_at' => Carbon::now()->subHours(25),
            'session_window_expires_at' => Carbon::now()->subHour(),
            'is_window_open' => false,
        ]);

        $refusedRes = $this->sendAction->handle(
            businessId: $biz->id,
            recipientPhone: $customerPhone,
            messageText: 'Follow-up on your service quote'
        );

        $this->assertEquals('refused', $refusedRes['status']);
        $this->assertEquals('OUTSIDE_24H_WINDOW_TEMPLATE_REQUIRED', $refusedRes['refusal_code']);
        Http::assertSentCount(1); // the one request is step 1's free-form reply; the refusal sent nothing

        // 3. Outside 24h window WITH approved template -> Succeeds via template
        $template = $this->templateAction->handle(
            businessId: $biz->id,
            name: 'service_followup_v1',
            category: 'utility',
            bodyText: 'Hello, your quote is ready for review.'
        );

        $this->engine->approveTemplate($biz->id, $template->id);

        $templateSendRes = $this->sendAction->handle(
            businessId: $biz->id,
            recipientPhone: $customerPhone,
            messageText: 'Hello, your quote is ready for review.',
            templateName: 'service_followup_v1'
        );

        $this->assertEquals('sent', $templateSendRes['status']);
        $this->assertEquals('template', $templateSendRes['mode']);
        $this->assertEquals('service_followup_v1', $templateSendRes['template_name']);
    }

    /**
     * [G10-40] opt-in registration
     */
    public function test_g10_40_opt_in(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'WhatsApp Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customerPhone = '+15551234567';

        $consentService = app(ConsentService::class);
        $consentService->suppress($biz->id, $customerPhone, 'whatsapp', 'SUPPRESSED');

        $refusedRes = $this->sendAction->handle(
            businessId: $biz->id,
            recipientPhone: $customerPhone,
            messageText: 'Hello'
        );

        $this->assertEquals('refused', $refusedRes['status']);
        $this->assertEquals('SUPPRESSED', $refusedRes['refusal_code']);
        Http::assertNothingSent();

        $this->engine->recordInbound($biz->id, $customerPhone);

        $refusedRes2 = $this->sendAction->handle(
            businessId: $biz->id,
            recipientPhone: $customerPhone,
            messageText: 'Hello again'
        );

        $this->assertEquals('refused', $refusedRes2['status']);
        $this->assertEquals('SUPPRESSED', $refusedRes2['refusal_code']);
        Http::assertNothingSent();
    }

    /**
     * [G19-22] GBP through Zernio; every channel lands on ONE Conversation
     * ⛔ REFUSED: G19-22 (Zernio half) — GBP runs through Zernio
     */
    public function test_g19_22_single_conversation(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'WhatsApp Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customerPhone = '+15550001111';

        $this->engine->recordInbound($biz->id, $customerPhone, 'Hello there!', 'John Doe');

        $person = Person::where('phone', $customerPhone)->first();
        $this->assertEquals(1, Conversation::where('person_id', $person->id)->count());

        $this->engine->recordInbound($biz->id, $customerPhone, 'Are you there?', 'John Doe');

        $this->assertEquals(1, Conversation::where('person_id', $person->id)->count());
    }
}
