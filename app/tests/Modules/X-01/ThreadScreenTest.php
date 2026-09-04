<?php

declare(strict_types=1);

namespace Tests\Modules\X01;

use App\Models\Customer;
use App\Modules\X01\Ui\Thread;
use App\Modules\X121\Models\Conversation;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ThreadScreenTest extends TestCase
{
    public function test_thread_screen_renders_states(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Thread Tenant', 'currency' => 'USD']);
        $user = \App\Models\User::factory()->create();
        Tenancy::actingAs($biz->id, function () use ($biz, $user) {
            Tenancy::setUser($user->id);
            // Default and empty state
            $customer = Customer::create([
                'business_id' => $biz->id,
                'name' => 'Jane Empty',
            ]);

            Livewire::test(Thread::class, ['customer' => $customer])
                ->assertSee('No messages yet') // empty state
                ->assertDontSee('Human takeover');

            // Default with messages
            $conversation = Conversation::create([
                'business_id' => $biz->id,
                'customer_id' => $customer->id,
                'channel' => 'sms',
                'status' => 'open',
            ]);

            DB::table('messages')->insert([
                'business_id' => $biz->id,
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'sender_id' => (string) $customer->id,
                'body' => 'I need a quote.',
                'created_at' => now(),
            ]);

            Livewire::test(Thread::class, ['customer' => $customer])
                ->assertSee('I need a quote.')
                ->assertDontSee('No messages yet');

            // Interaction - Draft AI Reply
            $messageId = DB::table('messages')->where('conversation_id', $conversation->id)->value('id');
            Livewire::test(Thread::class, ['customer' => $customer])
                ->call('draftAiReply', $messageId)
                ->assertSet('replyText', 'Drafted response based on context')
                ->assertSee('Drafted response based on context');

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
}
