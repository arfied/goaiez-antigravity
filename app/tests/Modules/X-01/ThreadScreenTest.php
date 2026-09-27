<?php

declare(strict_types=1);

namespace Tests\Modules\X01;

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Modules\X01\Domain\UnifiedInboxManager;
use App\Modules\X01\Exceptions\TakeoverNotLatchedRefused;
use App\Modules\X01\Ui\History;
use App\Modules\X01\Ui\Thread;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ThreadScreenTest extends TestCase
{
    public function test_thread_screen_renders_states(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Thread Tenant', 'currency' => 'USD']);
        $user = User::factory()->create();
        Tenancy::actingAs($biz->id, function () use ($user) {
            Tenancy::setUser($user->id);
            // Default and empty state
            $customer = Customer::factory()->create(['name' => 'Jane Empty']);

            Livewire::test(Thread::class, ['customer' => $customer])
                ->assertSee('No messages yet') // empty state
                ->assertDontSee('Human takeover');

            // Default with messages
            $conversation = Conversation::factory()->create([
                'customer_id' => $customer->id,
                'channel' => 'sms',
                'status' => 'open',
            ]);

            Message::factory()->create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'sender_id' => (string) $customer->id,
                'body' => 'I need a quote.',
            ]);

            Livewire::test(Thread::class, ['customer' => $customer])
                ->assertSee('I need a quote.')
                ->assertDontSee('No messages yet');

            // Interaction - Draft AI Reply
            $messageId = DB::table('messages')->where('conversation_id', $conversation->id)->value('id');
            Livewire::test(Thread::class, ['customer' => $customer])
                ->call('draftAiReply', $messageId)
                ->assertSet('replyText', 'Drafted response based on context');

            // Interaction - Takeover reply
            Livewire::test(Thread::class, ['customer' => $customer])
                ->set('replyText', 'This is a human takeover reply')
                ->call('sendReply');

            $this->assertDatabaseHas('messages', [
                'conversation_id' => $conversation->id,
                'body' => '[Human takeover by Operator]: This is a human takeover reply',
            ]);

            Livewire::test(Thread::class, ['customer' => $customer])
                ->assertSee('[Human takeover by Operator]: This is a human takeover reply');
        });
    }

    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::actingAs($biz->id, function () {
            Conversation::factory()->count(3)->create();
        });

        $this->get(route('x-01.thread'))
            ->assertOk()
            ->assertSee('3 found.');
    }

    /**
     * Verifies the release control in the thread screen unlatches an active takeover.
     * Proves the latch is cleared by ensuring the unified inbox manager refuses
     * subsequent takeover replies, as it correctly reads the cleared state.
     * Also confirms the real GET route renders the release control when latched,
     * and that the control vanishes once released.
     */
    public function test_thread_screen_release_takeover(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $customer = null;
        $conversation = null;

        Tenancy::actingAs($biz->id, function () use (&$customer, &$conversation, $owner) {
            Tenancy::setUser($owner->id);
            $customer = Customer::factory()->create([
                'name' => 'Release Customer',
                'phone' => '+1512557'.rand(1000, 9999),
            ]);

            $conversation = Conversation::factory()->create([
                'customer_id' => $customer->id,
                'channel' => 'sms',
                'status' => 'open',
            ]);

            Message::factory()->create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'sender_id' => (string) $customer->id,
                'body' => 'I need help',
            ]);

            // Send reply to latch it
            Livewire::test(Thread::class, ['customer' => $customer])
                ->set('replyText', 'I am here')
                ->call('sendReply')
                ->assertSet('hasActiveTakeover', true);
        });

        // 1. A real GET to prove it renders
        $this->get(route('x-01.thread', ['customer' => $customer->id]))
            ->assertOk()
            ->assertSee('Release Takeover');

        Tenancy::actingAs($biz->id, function () use ($customer, $conversation) {
            // 2. The load-bearing assertion: release the takeover via component
            Livewire::test(Thread::class, ['customer' => $customer])
                ->call('releaseTakeover')
                ->assertSet('hasActiveTakeover', false)
                ->assertDontSee('Release Takeover');

            // 3. Consulted by something other than the component: the manager
            // If the latch actually ended, replyWithTakeover will throw
            $manager = app(UnifiedInboxManager::class);
            $this->expectException(TakeoverNotLatchedRefused::class);
            $manager->replyWithTakeover($conversation->business_id, $conversation->id, 'another reply');
        });
    }

    /** @test This test proves that the Thread screen displays an ingested message manually inserted into the messages table, confirming resolvePersonId() successfully bridges the customer to its person_id conversations. */
    public function test_thread_screen_displays_ingested_message(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::actingAs($biz->id, function () use ($owner, $biz) {
            Tenancy::setUser($owner->id);
            $customer = Customer::factory()->create([
                'name' => 'Ingest Customer',
                'phone' => '+15125559999',
            ]);

            $manager = app(UnifiedInboxManager::class);
            $res = $manager->ingestMessage(
                $biz->id,
                'sms',
                '+15125559999',
                'Ingest Customer',
                'This is an ingested message.'
            );
            DB::table('messages')->insert([
                'business_id' => $biz->id,
                'conversation_id' => $res['conversation_id'],
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'sender_id' => '1',
                'body' => 'This is an ingested message.',
                'created_at' => now(),
            ]);

            Livewire::test(Thread::class, ['customer' => $customer])
                ->assertSee('This is an ingested message.');
        });
    }

    /** @test This test proves that resolvePersonId() refuses to match when the customer has no identifiers, preventing tenant data leaks between different persons in the same business. */
    public function test_thread_screen_does_not_leak_messages_when_customer_lacks_identifiers(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::actingAs($biz->id, function () use ($owner, $biz) {
            Tenancy::setUser($owner->id);

            $firstCustomer = Customer::factory()->create([
                'name' => 'First Customer',
                'phone' => '+15125551111',
            ]);

            $manager = app(UnifiedInboxManager::class);
            $res = $manager->ingestMessage(
                $biz->id,
                'sms',
                '+15125551111',
                'First Customer',
                'Secret message for first customer.'
            );
            DB::table('messages')->insert([
                'business_id' => $biz->id,
                'conversation_id' => $res['conversation_id'],
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'sender_id' => '1',
                'body' => 'Secret message for first customer.',
                'created_at' => now(),
            ]);

            $secondCustomer = Customer::factory()->create(['name' => 'Second Customer', 'phone' => null, 'email' => null]);
            Livewire::test(Thread::class, ['customer' => $secondCustomer])->assertDontSee('Secret message for first customer.');
        });
    }

    public function test_the_activity_screen_still_renders_after_an_operator_reply(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Thread Tenant', 'currency' => 'USD']);
        $user = User::factory()->create();
        Tenancy::actingAs($biz->id, function () use ($user) {
            Tenancy::setUser($user->id);
            // Default and empty state
            $customer = Customer::factory()->create(['name' => 'Jane Empty']);

            Livewire::test(Thread::class, ['customer' => $customer])
                ->assertSee('No messages yet') // empty state
                ->assertDontSee('Human takeover');

            // Default with messages
            $conversation = Conversation::factory()->create([
                'customer_id' => $customer->id,
                'channel' => 'sms',
                'status' => 'open',
            ]);

            Message::factory()->create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'sender_id' => (string) $customer->id,
                'body' => 'I need a quote.',
            ]);

            Livewire::test(Thread::class, ['customer' => $customer])
                ->set('replyText', 'Distinctive reply 4846')
                ->call('sendReply');

            $this->assertDatabaseHas('messages', [
                'conversation_id' => $conversation->id,
                'sender_type' => 'person',
            ]);

            Livewire::test(History::class)
                ->assertOk()
                ->assertSee('Distinctive reply 4846');
        });
    }

    public function test_send_reply_adds_notice_and_changes_button_text(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::actingAs($biz->id, function () use ($owner) {
            Tenancy::setUser($owner->id);
            $customer = Customer::factory()->create(['name' => 'Jane Empty']);

            $conversation = Conversation::factory()->create([
                'customer_id' => $customer->id,
                'channel' => 'sms',
                'status' => 'open',
            ]);

            Message::factory()->create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'sender_id' => (string) $customer->id,
                'body' => 'I need a quote.',
            ]);

            Livewire::test(Thread::class, ['customer' => $customer])
                ->set('replyText', 'Distinctive note 5501')
                ->call('sendReply')
                ->assertSet('notice', 'Added to the conversation. Nothing was sent to the customer — to text them, reply from the Inbox.');
        });

        $this->get(route('x-01.thread'))
            ->assertOk()
            ->assertSee('Add to the conversation')
            ->assertDontSee('Send Reply');
    }
}
