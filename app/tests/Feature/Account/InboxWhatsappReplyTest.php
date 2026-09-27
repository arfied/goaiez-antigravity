<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Contracts\MessageSender;
use App\Enums\CapturedBy;
use App\Enums\CaptureSurface;
use App\Enums\ConsentType;
use App\Enums\OutreachChannel;
use App\Enums\UserRole;
use App\Livewire\Account\Inbox;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Modules\CWhatsapp\Actions\WhatsappTemplateLookupAction;
use App\Modules\CWhatsapp\Domain\WhatsappEngine;
use App\Modules\CWhatsapp\Models\WhatsappConnection;
use App\Modules\CWhatsapp\Models\WhatsappSession;
use App\Modules\CWhatsapp\Models\WhatsappTemplate;
use App\Modules\X01\Domain\UnifiedInboxManager;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\ConsentCapture;
use App\Services\Consent\ConsentService;
use App\Services\Messaging\Outbound\SendOutcome;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class InboxWhatsappReplyTest extends TestCase
{
    use RefreshesTenantDatabase;

    private User $owner;

    private $biz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
        $this->actingAs($this->owner);
        Tenancy::setUser($this->owner->id);
        Tenancy::set((int) $this->biz->id);

        Mail::fake();
        Notification::fake();
    }

    private function setupZernio(bool $withConnection = true, bool $windowOpen = true)
    {
        app(DefaultsRegistry::class)->set('whatsapp.zernio_enabled', true, 'test');
        config(['credentials.zernio_api_key' => 'test_key']);

        if ($withConnection) {
            WhatsappConnection::forceCreate([
                'business_id' => $this->biz->id,
                'account_ref' => 'acct_wa_7004',
                'status' => 'connected',
            ]);
        }

        app(WhatsappEngine::class)->recordInbound($this->biz->id, '+15558887001');

        app(UnifiedInboxManager::class)->ingestMessage($this->biz->id, 'whatsapp', '+15558887001', 'Wendy Chat 7002', 'Hello from WhatsApp 7003');

        WhatsappSession::where('recipient_phone', '+15558887001')->update([
            'last_inbound_at' => $windowOpen ? Carbon::now() : Carbon::now()->subHours(25),
            'session_window_expires_at' => $windowOpen ? Carbon::now()->addHours(23) : Carbon::now()->subHour(),
            'is_window_open' => $windowOpen,
            'zernio_conversation_id' => 'conv_7005',
        ]);

        $conv = Conversation::where('channel', 'whatsapp')->orderByDesc('id')->first();

        return $conv;
    }

    public function test_get_and_layout()
    {
        $this->setupZernio();

        $this->get(route('account.inbox'))
            ->assertOk()
            ->assertSee('Wendy Chat 7002', false)
            ->assertSee('WhatsApp', false);
    }

    public function test_send_success()
    {
        $conv = $this->setupZernio();

        Http::fake([
            'zernio.com/api/v1/inbox/conversations/conv_7005/messages' => Http::response(['success' => true, 'data' => ['messageId' => 'wamid.OUT7006', 'conversationId' => 'conv_7005']]),
        ]);

        Livewire::test(Inbox::class)
            ->call('open', $conv->id)
            ->set('reply', 'Thanks Wendy 7007')
            ->call('send')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conv->id,
            'body' => 'Thanks Wendy 7007',
            'direction' => 'outbound',
        ]);

        $conv->refresh();
        $this->assertNotEquals('unhandled', $conv->agent_status);

        Http::assertSentCount(1);
    }

    public function test_no_connection()
    {
        $conv = $this->setupZernio(false);

        Http::fake();

        Livewire::test(Inbox::class)
            ->call('open', $conv->id)
            ->set('reply', 'Thanks Wendy 7007')
            ->call('send')
            ->assertHasErrors(['reply' => 'Not sent — connect a WhatsApp number first.']);

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
        ]);

        Http::assertNothingSent();
    }

    public function test_window_closed()
    {
        $conv = $this->setupZernio(true, false);

        Http::fake();

        Livewire::test(Inbox::class)
            ->call('open', $conv->id)
            ->set('reply', 'Thanks Wendy 7007')
            ->call('send')
            ->assertHasErrors(['reply' => 'Not sent — this person last wrote more than 24 hours ago. WhatsApp only allows an approved template now.']);

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
        ]);

        Http::assertNothingSent();
    }

    public function test_sms_conversation_still_goes_sms_way()
    {
        $person = Customer::factory()->create([
            'business_id' => $this->biz->id,
            'name' => 'Distinctive Person WhatsApp',
            'phone' => '+15559990000',
        ]);

        $conv = Conversation::create([
            'business_id' => $this->biz->id,
            'customer_id' => $person->id,
            'channel' => 'sms',
            'status' => 'open',
            'consent_logged_at' => now(),
        ]);

        $senderMock = \Mockery::mock(MessageSender::class);
        $senderMock->shouldReceive('send')->andReturnUsing(function ($message) {
            return SendOutcome::accepted(
                key: $message->key,
                providerMessageId: 'ref123'
            );
        });
        $this->app->instance(MessageSender::class, $senderMock);

        app(ConsentService::class)->record(
            $person,
            OutreachChannel::Sms,
            new ConsentCapture(
                CapturedBy::Platform,
                CaptureSurface::Chat,
                ConsentType::ExpressWritten,
                'v1.0',
                'web',
                ['url' => 'http://localhost', 'ip_hash' => 'dummy_hash', 'user_agent' => 'Pest']
            ),
            'test'
        );

        Livewire::test(Inbox::class)
            ->call('open', $conv->id)
            ->set('reply', 'Hello on SMS')
            ->call('send')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conv->id,
            'body' => 'Hello on SMS',
        ]);
    }

    public function test_send_template_success()
    {
        $conv = $this->setupZernio(true, false);

        $tpl = WhatsappTemplate::forceCreate([
            'business_id' => $this->biz->id,
            'name' => 'booking_followup_8101',
            'category' => 'marketing',
            'language' => 'en',
            'body_text' => 'Hi, following up on your booking 8102',
            'status' => 'approved',
        ]);

        Http::fake([
            'zernio.com/api/v1/inbox/conversations*' => Http::response(['success' => true, 'data' => ['messageId' => 'wamid.T8103', 'conversationId' => 'conv_8104', 'participantId' => '15558887001']], 201),
        ]);

        Livewire::test(Inbox::class)
            ->call('open', $conv->id)
            ->set('templateId', $tpl->id)
            ->call('sendTemplate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conv->id,
            'body' => 'Hi, following up on your booking 8102',
            'direction' => 'outbound',
        ]);

        Http::assertSentCount(1);
    }

    public function test_template_with_placeholder_not_offered()
    {
        $conv = $this->setupZernio(true, false);

        $tpl = WhatsappTemplate::forceCreate([
            'business_id' => $this->biz->id,
            'name' => 'has_placeholder',
            'category' => 'marketing',
            'language' => 'en',
            'body_text' => 'Hi, following up on your booking {{1}}',
            'status' => 'approved',
        ]);

        $this->assertEmpty(app(WhatsappTemplateLookupAction::class)->sendable($this->biz->id));

        Livewire::test(Inbox::class)
            ->call('open', $conv->id)
            ->set('templateId', $tpl->id)
            ->call('sendTemplate')
            ->assertHasErrors(['templateId' => 'Not sent — that template is not approved for sending.']);

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
        ]);
    }

    public function test_pending_template_not_offered()
    {
        $conv = $this->setupZernio(true, false);

        $tpl = WhatsappTemplate::forceCreate([
            'business_id' => $this->biz->id,
            'name' => 'pending_tpl',
            'category' => 'marketing',
            'language' => 'en',
            'body_text' => 'Hi, following up on your booking 8102',
            'status' => 'pending_approval',
        ]);

        Livewire::test(Inbox::class)
            ->call('open', $conv->id)
            ->set('templateId', $tpl->id)
            ->call('sendTemplate')
            ->assertHasErrors(['templateId' => 'Not sent — that template is not approved for sending.']);

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
        ]);
    }

    public function test_no_sendable_template_does_not_show_send_template()
    {
        $conv = $this->setupZernio(true, false);

        Livewire::test(Inbox::class)
            ->call('open', $conv->id)
            ->assertDontSee('Send template');
    }

    public function test_template_id_null()
    {
        $conv = $this->setupZernio(true, false);

        Livewire::test(Inbox::class)
            ->call('open', $conv->id)
            ->set('templateId', null)
            ->call('sendTemplate')
            ->assertHasErrors(['templateId' => 'Choose a template to send.']);
    }
}
