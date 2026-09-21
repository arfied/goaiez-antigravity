<?php

declare(strict_types=1);

namespace Tests\Modules\X07\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X07\Actions\ChurnScoreAction;
use App\Modules\X07\Actions\ForecastComputeAction;
use App\Modules\X07\Models\Forecast;
use App\Modules\X07\Ui\ForecastRiskTiles;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ForecastRiskTilesScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-07.forecast-risk-tiles'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No forecast yet')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        app(ForecastComputeAction::class)->compute(
            businessId: (int) $biz->id,
            periodMonth: '2026-09',
            bookedCents: 1500000,
            collectedCents: 950000,
        );
        app(ChurnScoreAction::class)->evaluateRisk(
            businessId: (int) $biz->id,
            periodMonth: '2026-09',
            riskScore: 42,
        );
        Tenancy::forget();

        $this->get(route('x-07.forecast-risk-tiles'))
            ->assertOk()
            ->assertSee('booked $15,000.00')
            ->assertSee('collected $9,500.00')
            ->assertSee('42% risk that customers leave')
            ->assertDontSee('No forecast yet');

        Livewire::test(ForecastRiskTiles::class)->assertOk();
    }

    public function test_can_submit_forecast_and_see_on_screen()
    {
        $biz = TestCase::provisionTenant(['name' => 'Forecasting Tenant', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        Livewire::test(ForecastRiskTiles::class)
            ->set('periodMonth', '2026-09')
            ->set('riskScore', '75')
            ->call('submit')
            ->assertSet('error', null)
            ->assertSet('success', 'Recorded high-risk forecast. This feeds the forecast tiles; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas((new Forecast)->getTable(), [
            'business_id' => $biz->id,
            'period_month' => '2026-09',
            'is_high_risk' => true,
        ]);

        $this->actingAs($biz->owner)
            ->withSession(['tenant_id' => $biz->id])
            ->get('/app/x-07/forecast-risk-tiles')
            ->assertSee('2026-09')
            ->assertDontSee('No forecast yet');
    }

    public function test_refuses_invalid_input()
    {
        $biz = TestCase::provisionTenant(['name' => 'Forecasting Tenant', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        Livewire::test(ForecastRiskTiles::class)
            ->set('periodMonth', '')
            ->set('riskScore', '75')
            ->call('submit')
            ->assertSet('error', 'Period month is required.');

        Livewire::test(ForecastRiskTiles::class)
            ->set('periodMonth', '2026-09')
            ->set('riskScore', '101')
            ->call('submit')
            ->assertSet('error', 'Risk score must be between 0 and 100.');

        $this->assertDatabaseMissing((new Forecast)->getTable(), [
            'business_id' => $biz->id,
            'period_month' => '2026-09',
        ]);
    }

    public function test_can_record_booked_and_collected_for_a_month()
    {
        $biz = TestCase::provisionTenant(['name' => 'Forecasting Tenant', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        Livewire::test(ForecastRiskTiles::class)
            ->set('amountsMonth', '2026-10')
            ->set('bookedCents', '1550000')
            ->set('collectedCents', '1000000')
            ->call('recordMonth')
            ->assertSet('amountsError', null)
            ->assertSet('amountsSuccess', 'Recorded 2026-10: booked $15,500.00, collected $10,000.00. The tiles below read this row; the risk score for the same month is the other form on this screen.');

        $this->assertDatabaseHas((new Forecast)->getTable(), [
            'business_id' => $biz->id,
            'period_month' => '2026-10',
            'booked_cents' => 1550000,
            'collected_cents' => 1000000,
        ]);
    }

    public function test_amounts_and_risk_compose_on_one_forecast_row()
    {
        $biz = TestCase::provisionTenant(['name' => 'Forecasting Tenant', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        Livewire::test(ForecastRiskTiles::class)
            ->set('amountsMonth', '2026-11')
            ->set('bookedCents', '1600000')
            ->set('collectedCents', '800000')
            ->call('recordMonth')
            ->assertSet('amountsError', null);

        Livewire::test(ForecastRiskTiles::class)
            ->set('periodMonth', '2026-11')
            ->set('riskScore', '33')
            ->call('submit')
            ->assertSet('error', null);

        $this->assertEquals(1, Forecast::where('business_id', $biz->id)->count());

        $this->assertDatabaseHas((new Forecast)->getTable(), [
            'business_id' => $biz->id,
            'period_month' => '2026-11',
            'booked_cents' => 1600000,
            'collected_cents' => 800000,
            'churn_risk_pct' => 33,
        ]);
    }

    public function test_refuses_an_empty_booked_amount()
    {
        $biz = TestCase::provisionTenant(['name' => 'Forecasting Tenant', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        Livewire::test(ForecastRiskTiles::class)
            ->set('amountsMonth', '2026-12')
            ->set('bookedCents', '   ')
            ->set('collectedCents', '500000')
            ->call('recordMonth')
            ->assertSet('amountsError', 'Enter the booked amount in cents.');

        $this->assertDatabaseMissing((new Forecast)->getTable(), [
            'business_id' => $biz->id,
            'period_month' => '2026-12',
        ]);
    }

    public function test_forecast_tile_shows_the_recorded_amounts()
    {
        $biz = TestCase::provisionTenant(['name' => 'Forecasting Tenant', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        Livewire::test(ForecastRiskTiles::class)
            ->set('amountsMonth', '2027-01')
            ->set('bookedCents', '2500000')
            ->set('collectedCents', '2100000')
            ->call('recordMonth');

        Tenancy::forget();

        $this->actingAs($biz->owner)
            ->withSession(['tenant_id' => $biz->id])
            ->get(route('x-07.forecast-risk-tiles'))
            ->assertOk()
            ->assertSee('booked $25,000.00')
            ->assertSee('collected $21,000.00')
            ->assertDontSee('No forecast yet');
    }
}
