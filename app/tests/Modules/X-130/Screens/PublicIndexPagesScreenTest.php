<?php

declare(strict_types=1);

namespace Tests\Modules\X130\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X130\Models\DemandRegion;
use App\Modules\X130\Models\DemandSeries;
use App\Modules\X130\Ui\PublicIndexPages;
use Livewire\Livewire;
use Tests\TestCase;

class PublicIndexPagesScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        DemandRegion::query()->delete();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-130.public-index-pages'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No demand figures yet.');

        $region = DemandRegion::create([
            'region_code' => 'DISTINCT-4646',
            'region_name' => 'Distinctive County 4646',
            'trade_type' => 'distinctive_trade_4646',
        ]);

        DemandSeries::create([
            'region_id' => $region->id,
            'period_date' => now()->toDateString(),
            'demand_index' => 73.50,
            'source_count' => 9,
            'is_published' => true,
        ]);

        $this->get(route('x-130.public-index-pages'))
            ->assertOk()
            ->assertSee('Distinctive County 4646')
            ->assertSee('distinctive_trade_4646')
            ->assertDontSee('No demand figures yet.');

        Livewire::test(PublicIndexPages::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-130.public-index-pages.admin'))->assertOk();

        Livewire::test(PublicIndexPages::class)->assertOk();
    }
}
