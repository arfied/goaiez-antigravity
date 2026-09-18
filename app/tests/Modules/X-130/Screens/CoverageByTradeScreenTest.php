<?php

declare(strict_types=1);

namespace Tests\Modules\X130\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X130\Models\DemandRegion;
use App\Modules\X130\Ui\CoverageByTrade;
use Livewire\Livewire;
use Tests\TestCase;

class CoverageByTradeScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        DemandRegion::query()->delete();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-130.coverage-by-trade'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No trades covered yet.');

        DemandRegion::create([
            'region_code' => 'DISTINCT-4646',
            'region_name' => 'Distinctive County 4646',
            'trade_type' => 'distinctive_trade_4646',
        ]);

        $this->get(route('x-130.coverage-by-trade'))
            ->assertOk()
            ->assertSee('distinctive_trade_4646')
            ->assertDontSee('No trades covered yet.');

        Livewire::test(CoverageByTrade::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-130.coverage-by-trade.admin'))->assertOk();

        Livewire::test(CoverageByTrade::class)->assertOk();
    }
}
