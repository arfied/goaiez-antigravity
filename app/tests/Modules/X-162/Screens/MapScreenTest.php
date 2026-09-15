<?php

declare(strict_types=1);

namespace Tests\Modules\X162\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X162\Models\Route;
use App\Modules\X162\Ui\Map;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class MapScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-162.map'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No routes yet.');

        Tenancy::setUser($owner->id);
        Route::create([
            'business_id' => $biz->id,
            'tech_id' => $owner->id,
            'stop_order' => [770144],
            'total_distance_km' => 12.5,
        ]);
        Tenancy::forget();

        $this->get(route('x-162.map'))
            ->assertOk()
            ->assertSee('1 technician route')
            ->assertSee('12.5 km')
            ->assertSee('Job #770144')
            ->assertDontSee('No routes yet.');

        Livewire::test(Map::class)->assertOk();
    }
}
