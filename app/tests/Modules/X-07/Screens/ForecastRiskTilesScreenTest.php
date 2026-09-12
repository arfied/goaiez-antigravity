<?php

declare(strict_types=1);

namespace Tests\Modules\X07\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X07\Actions\ChurnScoreAction;
use App\Modules\X07\Actions\ForecastComputeAction;
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
}
