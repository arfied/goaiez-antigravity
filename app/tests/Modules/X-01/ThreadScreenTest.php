<?php

declare(strict_types=1);

namespace Tests\Modules\X01;

use App\Modules\X01\Ui\Thread;
use App\Modules\X121\Models\Conversation;
use App\Modules\X121\Models\Message;
use App\Modules\X121\Models\Person;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ThreadScreenTest extends TestCase
{
    public function test_thread_screen_renders_five_states(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Thread Tenant', 'currency' => 'USD']);
        Tenancy::actingAs($biz->id, function () use ($biz) {
            // Default and empty state
            $person = Person::create([
                'business_id' => $biz->id,
                'name' => 'Jane Empty',
            ]);

            Livewire::test(Thread::class, ['person' => $person])
                ->assertSee('No conversations recorded') // empty state
                ->assertDontSee('Human takeover');

            // Default with messages
            $conversation = Conversation::create([
                'business_id' => $biz->id,
                'person_id' => $person->id,
                'channel' => 'sms',
                'status' => 'open',
            ]);

            Message::create([
                'business_id' => $biz->id,
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'sender_id' => $person->id,
                'body' => 'I need a quote.',
            ]);

            Livewire::test(Thread::class, ['person' => $person])
                ->assertSee('I need a quote.')
                ->assertDontSee('No conversations recorded');

            // Interaction - Draft AI Reply
            Livewire::test(Thread::class, ['person' => $person])
                ->call('draftAiReply', Message::first()->id)
                ->assertSet('replyText', 'Drafted response based on context')
                ->assertSee('Drafted response based on context');

            // Interaction - Takeover reply
            Livewire::test(Thread::class, ['person' => $person])
                ->set('replyText', 'This is a human takeover reply')
                ->call('sendReply');

            $this->assertDatabaseHas('messages', [
                'conversation_id' => $conversation->id,
                'body' => '[Human takeover by Operator]: This is a human takeover reply',
            ]);

            Livewire::test(Thread::class, ['person' => $person])
                ->assertSee('[Human takeover by Operator]: This is a human takeover reply');

            // SAMPLE state
            $samplePerson = Person::create([
                'business_id' => $biz->id,
                'name' => 'SAMPLE PERSON',
            ]);

            Livewire::test(Thread::class, ['person' => $samplePerson])
                ->assertSee('SAMPLE');
        });
    }
}
