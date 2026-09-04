<?php

namespace Tests\Modules\X07\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ForecastRiskTilesScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-07.forecast-risk-tiles'))->assertOk();

        Livewire::test(\App\Modules\X07\Ui\ForecastRiskTiles::class)->assertOk();
    }
}
