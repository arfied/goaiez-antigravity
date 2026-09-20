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
}
