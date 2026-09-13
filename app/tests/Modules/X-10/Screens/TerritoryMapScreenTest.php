<?php

declare(strict_types=1);

namespace Tests\Modules\X10\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X10\Actions\TerritoryDefineAction;
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
}
