<?php

declare(strict_types=1);

namespace Tests\Modules\X130\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X130\Actions\DemandPublishAction;
use App\Modules\X130\Models\DemandRegion;
use App\Modules\X130\Models\DemandSeries;
use App\Modules\X130\Ui\DemandTile;
use Livewire\Livewire;
use Tests\TestCase;

class DemandTileScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-130.demand-tile'))->assertOk();

        Livewire::test(DemandTile::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-130.demand-tile.admin'))->assertOk();

        Livewire::test(DemandTile::class)->assertOk();
    }

    public function test_the_demand_tile_shows_the_published_index_or_says_there_is_none(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-130.demand-tile'))
            ->assertSee('No published demand figure yet');

        $region = DemandRegion::create([
            'region_code' => 'DISTINCT-4682',
            'region_name' => 'Distinctive County 4682',
            'trade_type' => 'distinctive_trade_4682',
        ]);

        app(DemandPublishAction::class)->publishCell($region->id, '2026-09-01', 73.5, 9);

        $this->get(route('x-130.demand-tile'))
            ->assertSee('73.5');

        DemandSeries::query()->delete();

        app(DemandPublishAction::class)->publishCell($region->id, '2026-08-01', 41.0, 2);

        $this->get(route('x-130.demand-tile'))
            ->assertSee('No published demand figure yet');
    }
}
