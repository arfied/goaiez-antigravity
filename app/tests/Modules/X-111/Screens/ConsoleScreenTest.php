<?php

declare(strict_types=1);

namespace Tests\Modules\X111\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X111\Ui\Console;
use Livewire\Livewire;
use Tests\TestCase;

class ConsoleScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-111.console.admin'))
            ->assertOk()
            ->assertSeeText('Operator Control Center Console')
            ->assertSeeText('No support tickets.');

        Livewire::test(Console::class)->assertOk();
    }

    public function test_can_create_ticket(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        Livewire::test(Console::class)
            ->set('fullTranscript', 'User needs help')
            ->set('category', 'support')
            ->call('createTicket')
            ->assertSet('error', null)
            ->assertSet('success', 'Ticket created. This feeds the Escalated Tickets list and dispatches TicketOpened; nothing downstream acts on it yet.');

        $this->assertDatabaseHas('tenant_tickets', [
            'business_id' => $biz->id,
            'source' => 'human_requested',
            'status' => 'open',
            'category' => 'support',
        ]);

        $this->get(route('x-111.console.admin'))
            ->assertOk()
            ->assertSee('human_requested')
            ->assertSee('support')
            ->assertDontSee('No support tickets.');
    }

    public function test_refuses_empty_ticket(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        Livewire::test(Console::class)
            ->set('fullTranscript', '')
            ->set('category', 'support')
            ->call('createTicket')
            ->assertSet('error', 'Transcript and category are required.');

        $this->assertDatabaseMissing('tenant_tickets', [
            'business_id' => $biz->id,
            'category' => 'support',
        ]);
    }
}
