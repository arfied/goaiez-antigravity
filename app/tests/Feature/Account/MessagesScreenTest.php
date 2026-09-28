<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\OutreachMessage;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class MessagesScreenTest extends TestCase
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
        $this->get(route('account.messages'))
            ->assertOk()
            ->assertSee('Your account', false)
            ->assertDontSee('Internal Platform Console');
    }

    public function test_empty_state(): void
    {
        $this->get(route('account.messages'))
            ->assertOk()
            ->assertSee('When we start asking your customers for reviews', false);
    }

    public function test_renders_one_sent_message_body(): void
    {
        $customer = Customer::factory()->create([
            'business_id' => $this->biz->id,
            'name' => 'Distinctive Person 7719',
        ]);

        OutreachMessage::factory()->create([
            'business_id' => $this->biz->id,
            'customer_id' => $customer->id,
            'body' => 'Distinctive sent message body 7719',
        ]);

        $this->get(route('account.messages'))
            ->assertOk()
            ->assertSee('Distinctive sent message body 7719', false);
    }
}
