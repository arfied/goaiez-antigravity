<?php

declare(strict_types=1);

namespace Tests\Modules\X16\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X16\Ui\GeogridMap;
use App\Modules\X16\Models\GeoGrid;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class GeogridMapScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-16.geogrid-map'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No grids yet');

        Tenancy::setUser($owner->id);
        $points = array_fill(0, 25, ['rank' => null]);
        $points[0]['rank'] = 1;
        $points[1]['rank'] = 2;
        $points[2]['rank'] = 1;
        GeoGrid::create([
            'business_id' => $biz->id,
            'grid_name' => 'Distinctive grid 4609',
            'center_lat' => 30.2672,
            'center_lng' => -97.7431,
            'radius_km' => 5,
            'grid_points' => $points,
        ]);
        Tenancy::forget();

        $this->get(route('x-16.geogrid-map'))
            ->assertOk()
            ->assertSee('Distinctive grid 4609')
            ->assertSee('3 of 25 points scanned')
            ->assertSee('Scanned')
            ->assertDontSee('No grids yet');

        Livewire::test(GeogridMap::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-16.geogrid-map.admin'))->assertOk();

        Livewire::test(GeogridMap::class)->assertOk();
    }
}
