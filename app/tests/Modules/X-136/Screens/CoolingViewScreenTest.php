<?php

declare(strict_types=1);

namespace Tests\Modules\X136\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X136\Ui\CoolingView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class CoolingViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-136.cooling'))->assertOk();

        Livewire::test(CoolingView::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-136.cooling.admin'))->assertOk();

        Livewire::test(CoolingView::class)->assertOk();
    }

    public function test_can_record_a_cooling_signal(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        Livewire::test(CoolingView::class)
            ->set('prospectIdentifier', 'bluewater-hvac')
            ->set('signalType', 'pricing_visit')
            ->set('signalScore', '60')
            ->call('recordSignal')
            ->assertSet('success', 'Recorded pricing_visit for bluewater-hvac at 60 — cooling, so it is listed below.');

        $this->assertDatabaseHas('signals', [
            'prospect_identifier' => 'bluewater-hvac',
            'signal_type' => 'pricing_visit',
        ]);
        $this->assertDatabaseHas('signal_scores', [
            'prospect_identifier' => 'bluewater-hvac',
            'cooling_status' => 'cooling',
            'is_high_intent' => false,
        ]);
    }

    public function test_a_high_intent_signal_is_fresh_and_stays_off_the_cooling_list(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        Livewire::test(CoolingView::class)
            ->set('prospectIdentifier', 'bluewater-hvac')
            ->set('signalType', 'pricing_visit')
            ->set('signalScore', '90')
            ->call('recordSignal')
            ->assertSet('success', 'Recorded pricing_visit for bluewater-hvac at 90 — fresh, so it is not on this list; the signal volume screen counts it.');

        $this->assertDatabaseHas('signal_scores', [
            'prospect_identifier' => 'bluewater-hvac',
            'cooling_status' => 'fresh',
            'is_high_intent' => true,
        ]);

        Tenancy::forget();

        $this->actingAs($owner);
        $this->get(route('x-136.cooling'))
            ->assertOk()
            ->assertSee('Nobody is cooling')
            ->assertDontSee('bluewater-hvac');
    }

    public function test_refuses_a_non_numeric_score(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        Livewire::test(CoolingView::class)
            ->set('prospectIdentifier', 'bluewater-hvac')
            ->set('signalType', 'pricing_visit')
            ->set('signalScore', 'abc')
            ->call('recordSignal')
            ->assertSet('error', 'Enter the score as a number.');

        $this->assertDatabaseMissing('signals', [
            'prospect_identifier' => 'bluewater-hvac',
        ]);
        $this->assertDatabaseMissing('signal_scores', [
            'prospect_identifier' => 'bluewater-hvac',
        ]);
    }

    public function test_cooling_list_shows_the_recorded_prospect(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        Livewire::test(CoolingView::class)
            ->set('prospectIdentifier', 'bluewater-hvac')
            ->set('signalType', 'pricing_visit')
            ->set('signalScore', '60')
            ->call('recordSignal');

        Tenancy::forget();

        $this->actingAs($owner);
        $this->get(route('x-136.cooling'))
            ->assertOk()
            ->assertSee('bluewater-hvac')
            ->assertDontSee('Nobody is cooling');
    }
}
