<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Enums\UserRole;
use App\Livewire\Support\Tickets;
use App\Models\User;
use App\Services\Support\SupportDesk;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class TicketsScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_renders_support_layout(): void
    {
        $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get(route('support.tickets'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_owner_gets_forbidden(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->get(route('support.tickets'))
            ->assertForbidden();
    }

    public function test_renders_distinctive_subject(): void
    {
        $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::set((int) $business->id);
        Tenancy::setUser($owner->id);

        $ticket = app(SupportDesk::class)->raise($owner, 'Distinctive Ticket Subject 7719', 'Body');

        Tenancy::forgetAll();

        Livewire::actingAs($admin)
            ->test(Tickets::class)
            ->call('open', $ticket->id)
            ->assertSee('Distinctive Ticket Subject 7719');
    }
}
