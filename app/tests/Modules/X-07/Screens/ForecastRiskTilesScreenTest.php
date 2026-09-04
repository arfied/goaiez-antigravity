<?php

declare(strict_types=1);

namespace Tests\Modules\X07\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X07\Ui\ForecastRiskTiles;
use Livewire\Livewire;
use Tests\TestCase;

class ForecastRiskTilesScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-07.forecast-risk-tiles'))->assertOk();

        Livewire::test(ForecastRiskTiles::class)->assertOk();
    }
}
