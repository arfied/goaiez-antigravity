<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\SendRefusalReason;
use App\Enums\UserRole;
use App\Livewire\Account\Inbox;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Services\Conversations\InboxReplies;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class InboxScreenTest extends TestCase
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

    public function test_get_and_layout(): void
    {
        $this->get(route('account.inbox'))
            ->assertOk()
            ->assertSee('Your account', false)
            ->assertDontSee('Internal Platform Console');
    }

    public function test_empty_state(): void
    {
        $this->get(route('account.inbox'))
            ->assertOk()
            ->assertSee('No conversations yet.', false);
    }

    public function test_renders_one_thread_with_one_inbound_message(): void
    {
        $person = Customer::factory()->create([
            'business_id' => $this->biz->id,

            'name' => 'Distinctive Person 7719',
        ]);

        $conv = Conversation::create([
            'business_id' => $this->biz->id,
            'customer_id' => $person->id,
            'channel' => 'sms',
            'status' => 'open',
        ]);

        DB::table('messages')->insert([
            'business_id' => $this->biz->id,
            'conversation_id' => $conv->id,
            'sender_type' => 'customer',
            'body' => 'Distinctive inbound message body',
            'direction' => 'inbound',
            'created_at' => now(),
        ]);

        $this->get(route('account.inbox'))
            ->assertOk()
            ->assertSee('Distinctive Person 7719', false);
    }

    public function test_whatsapp_conversation_is_refused_honest_and_not_latched(): void
    {
        $person = Customer::factory()->create([
            'business_id' => $this->biz->id,
            'name' => 'Distinctive Person WhatsApp',
        ]);

        $conv = Conversation::create([
            'business_id' => $this->biz->id,
            'customer_id' => $person->id,
            'channel' => 'sms', // Stored as SMS so ConversationThreads::find() loads it
            'status' => 'open',
            'consent_logged_at' => now(),
        ]);

        // Intercept model retrieval and change it to whatsapp in memory
        Event::listen('eloquent.retrieved: App\Models\Conversation', function ($model) {
            $model->channel = 'whatsapp';
        });

        Livewire::test(Inbox::class)
            ->call('open', $conv->id)
            ->set('reply', 'Hello on WhatsApp')
            ->call('send')
            ->assertHasErrors(['reply' => 'Not sent — replying on WhatsApp is not connected yet, so nothing went out.']);

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
        ]);

        $this->assertDatabaseHas('conversations', [
            'id' => $conv->id,
            'agent_status' => 'unhandled',
        ]);
    }

    public function test_service_refuses_whatsapp(): void
    {
        $person = Customer::factory()->create(['business_id' => $this->biz->id]);
        $conv = Conversation::create([
            'business_id' => $this->biz->id,
            'customer_id' => $person->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'consent_logged_at' => now(),
        ]);

        $reason = app(InboxReplies::class)->send($conv, 'Hi 4962', $this->owner, 'k1');

        $this->assertEquals(SendRefusalReason::ChannelUnavailable, $reason);
    }
}
