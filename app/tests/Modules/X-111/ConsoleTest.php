<?php

declare(strict_types=1);

namespace Tests\Modules\X111;

use App\Models\Business;
use App\Modules\X111\Models\OperatorAlert;
use App\Modules\X111\Models\TenantTicket;
use App\Modules\X111\Ui\Console;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ConsoleTest extends TestCase
{
    public function test_renders_alerts_and_tickets(): void
    {
        $business = Business::factory()->create();
        Tenancy::set((int) $business->id);

        OperatorAlert::factory()->create([
            'business_id' => $business->id,
            'action_verb_message' => 'Test alert message',
        ]);

        TenantTicket::factory()->create([
            'business_id' => $business->id,
            'category' => 'Test category',
        ]);

        Livewire::test(Console::class)
            ->assertSee('Test alert message')
            ->assertSee('Test category');
    }

    public function test_resolves_alert(): void
    {
        $business = Business::factory()->create();
        Tenancy::set((int) $business->id);

        $alert = OperatorAlert::factory()->create([
            'business_id' => $business->id,
            'action_verb_message' => 'Resolve this alert',
            'status' => 'open',
        ]);

        Livewire::test(Console::class)
            ->assertSee('Resolve this alert')
            ->call('resolveAlert', $alert->id)
            ->assertDontSee('Resolve this alert');
    }

    public function test_resolves_ticket(): void
    {
        $business = Business::factory()->create();
        Tenancy::set((int) $business->id);

        $ticket = TenantTicket::factory()->create([
            'business_id' => $business->id,
            'category' => 'Resolve this ticket',
            'status' => 'open',
        ]);

        Livewire::test(Console::class)
            ->assertSee('Resolve this ticket')
            ->call('resolveTicket', $ticket->id)
            ->assertDontSee('Resolve this ticket');
    }
}
