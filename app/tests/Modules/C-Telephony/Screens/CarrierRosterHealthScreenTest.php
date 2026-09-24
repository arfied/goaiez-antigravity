<?php

declare(strict_types=1);

namespace Tests\Modules\CTelephony\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CTelephony\Actions\CarrierSendAction;
use App\Modules\CTelephony\Ui\CarrierRosterHealth;
use Illuminate\Support\Facades\DB;
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

    public function test_a_carrier_marked_cold_on_the_screen_refuses_rcs_the_way_the_router_promises(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(CarrierRosterHealth::class)
            ->set('carrierName', 'sinch')
            ->set('status', 'cold')
            ->call('recordHealth');

        $this->assertDatabaseHas('carrier_health', [
            'carrier_name' => 'sinch',
            'status' => 'cold',
        ]);

        DB::table('carrier_credentials')->insert([
            ['business_id' => $biz->id, 'carrier_name' => 'telnyx', 'api_key' => 'key_telnyx', 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => $biz->id, 'carrier_name' => 'infobip', 'api_key' => 'key_infobip', 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => $biz->id, 'carrier_name' => 'sinch', 'api_key' => 'key_sinch', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $rcsRes = app(CarrierSendAction::class)->handle(
            businessId: $biz->id,
            threadKey: 'rcs-thread-sinch-cold',
            toPhone: '+15125550188',
            body: 'Rich RCS card payload',
            isRcs: true,
            preferredCarrier: 'sinch'
        );

        $this->assertEquals('refused', $rcsRes['status']);
        $this->assertEquals('sinch', $rcsRes['carrier']);
        $this->assertEquals('RCS_CARRIER_COLD', $rcsRes['reason']);

        $this->get(route('c-telephony.carrier-roster-health'))
            ->assertSee('value="cold"', false)
            ->assertSee('value="degraded"', false)
            ->assertSee('value="down"', false)
            ->assertDontSee('value="unhealthy"', false);
    }
}
