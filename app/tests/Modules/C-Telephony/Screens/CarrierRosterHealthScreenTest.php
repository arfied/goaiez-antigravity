<?php

declare(strict_types=1);

namespace Tests\Modules\CTelephony\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CTelephony\Ui\CarrierRosterHealth;
use Livewire\Livewire;
use Tests\TestCase;

class CarrierRosterHealthScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('c-telephony.carrier-roster-health'))->assertOk();

        Livewire::test(CarrierRosterHealth::class)->assertOk();
    }

    public function test_can_record_carrier_health(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(CarrierRosterHealth::class)
            ->set('carrierName', 'Verizon')
            ->set('status', 'healthy')
            ->call('recordHealth')
            ->assertSet('error', null)
            ->assertSet('success', 'Recorded health for Verizon. This edits the existing row if one exists. This feeds the status list; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas('carrier_health', [
            'business_id' => $biz->id,
            'carrier_name' => 'Verizon',
            'status' => 'healthy',
        ]);

        $this->get(route('c-telephony.carrier-roster-health'))
            ->assertOk()
            ->assertSee('Verizon')
            ->assertDontSee('No carrier health metrics recorded.');
    }

    public function test_refuses_empty_carrier_health(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(CarrierRosterHealth::class)
            ->set('carrierName', '')
            ->set('status', 'healthy')
            ->call('recordHealth')
            ->assertSet('error', 'Carrier name and status are required.');

        $this->assertDatabaseMissing('carrier_health', [
            'business_id' => $biz->id,
        ]);
    }
}
