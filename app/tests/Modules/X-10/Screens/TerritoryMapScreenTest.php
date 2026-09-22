<?php

declare(strict_types=1);

namespace Tests\Modules\X10\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X10\Actions\TerritoryDefineAction;
use App\Modules\X10\Models\Territory;
use App\Modules\X10\Ui\TerritoryMap;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class TerritoryMapScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-10.territory-map'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No service areas yet')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        app(TerritoryDefineAction::class)->handle(
            businessId: (int) $biz->id,
            name: 'North side of Denver',
            zipCodes: ['80202', '80203'],
        );
        Tenancy::forget();

        $this->get(route('x-10.territory-map'))
            ->assertOk()
            ->assertSee('North side of Denver')
            ->assertSee('Not assigned yet')
            ->assertDontSee('No service areas yet');

        Livewire::test(TerritoryMap::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-10.territory-map.admin'))->assertOk();

        Livewire::test(TerritoryMap::class)->assertOk();
    }

    public function test_owner_can_define_a_service_area_from_the_screen(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $this->get(route('x-10.territory-map'))
            ->assertOk()
            ->assertSee('No service areas yet');

        Livewire::test(TerritoryMap::class)
            ->set('name', 'Downtown Denver')
            ->call('defineArea');

        $this->assertDatabaseHas('territories', [
            'business_id' => $biz->id,
            'name' => 'Downtown Denver',
        ]);

        $this->get(route('x-10.territory-map'))
            ->assertOk()
            ->assertSee('Downtown Denver');
    }

    public function test_defining_empty_area_name_is_refused(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(TerritoryMap::class)
            ->set('name', '   ')
            ->call('defineArea')
            ->assertSee('Name cannot be empty.');

        $this->assertSame(0, Territory::where('business_id', $biz->id)->count());
    }
}
