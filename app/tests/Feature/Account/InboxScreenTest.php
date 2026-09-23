<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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
}
