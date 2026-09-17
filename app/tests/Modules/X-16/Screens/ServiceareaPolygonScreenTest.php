<?php

declare(strict_types=1);

namespace Tests\Modules\X16\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X16\Ui\ServiceareaPolygon;
use App\Modules\X16\Models\ServicePolygon;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceareaPolygonScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-16.servicearea-polygon'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No service area yet');

        Tenancy::setUser($owner->id);
        ServicePolygon::create([
            'business_id' => $biz->id,
            'polygon_name' => 'Distinctive area 4610',
            'coordinates' => [[30.20, -97.80], [30.30, -97.80], [30.30, -97.70], [30.20, -97.70]],
            'is_active' => true,
        ]);
        Tenancy::forget();

        $this->get(route('x-16.servicearea-polygon'))
            ->assertOk()
            ->assertSee('Distinctive area 4610')
            ->assertSee('4 points')
            ->assertSee('Active')
            ->assertDontSee('No service area yet');

        Livewire::test(ServiceareaPolygon::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-16.servicearea-polygon.admin'))->assertOk();

        Livewire::test(ServiceareaPolygon::class)->assertOk();
    }
}
