<?php

declare(strict_types=1);

namespace Tests\Modules\X07\Screens;

use App\Modules\X07\Models\Forecast;
use App\Modules\X07\Ui\ForecastRiskTiles;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ForecastRiskTilesScreenTest extends TestCase
{
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

        $this->assertDatabaseHas((new Forecast())->getTable(), [
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

        $this->assertDatabaseMissing((new Forecast())->getTable(), [
            'business_id' => $biz->id,
            'period_month' => '2026-09',
        ]);
    }
}
