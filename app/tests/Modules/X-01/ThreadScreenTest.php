<?php

declare(strict_types=1);

namespace Tests\Modules\X01;

use App\Models\Customer;
use App\Models\User;
use App\Modules\X01\Ui\Thread;
use App\Models\Conversation;
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
        Tenancy::actingAs($biz->id, function () use ($biz, $user) {
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

            \App\Models\Message::factory()->create([
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
        $owner = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        
        \App\Support\Tenancy::actingAs($biz->id, function() {
            Conversation::factory()->count(3)->create();
        });

        $this->get(route('x-01.thread'))
            ->assertOk()
            ->assertSee('3 found.');
    }
}
