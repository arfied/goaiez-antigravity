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
     * Test the release control on the thread screen.
     * Proves the latch ended by trying to use replyWithTakeover() which is consulted
     * by something other than the component that released it.
     * Also includes a real GET to ensure the screen renders properly with a latched state.
     * Note: TakeoverReleased still has no listener - this is left for Wave 108 (C-Agent).
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
                'phone' => '+1512555'.rand(1000, 9999),
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
}
