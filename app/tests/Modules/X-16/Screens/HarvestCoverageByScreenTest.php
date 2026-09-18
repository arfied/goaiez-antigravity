<?php

declare(strict_types=1);

namespace Tests\Modules\X16\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X16\Models\PlacesRecord;
use App\Modules\X16\Models\ServicePolygon;
use App\Modules\X16\Ui\HarvestCoverageBy;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class HarvestCoverageByScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-16.harvest-coverage-by'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No places harvested yet');

        Tenancy::setUser($owner->id);
        ServicePolygon::create([
            'business_id' => $biz->id,
            'polygon_name' => 'Distinctive area 4611',
            'coordinates' => [[30.20, -97.80], [30.30, -97.80], [30.30, -97.70], [30.20, -97.70]],
            'is_active' => true,
        ]);
        PlacesRecord::create([
            'business_id' => $biz->id,
            'place_id' => 'distinctive-4612',
            'name' => 'Distinctive place 4612',
            'address' => '4612 Distinctive St',
            'latitude' => 30.25,
            'longitude' => -97.75,
            'is_chain' => false,
        ]);
        PlacesRecord::create([
            'business_id' => $biz->id,
            'place_id' => 'distinctive-4613',
            'name' => 'Distinctive place 4613',
            'address' => '4613 Distinctive St',
            'latitude' => null,
            'longitude' => null,
            'is_chain' => false,
        ]);
        Tenancy::forget();

        $this->get(route('x-16.harvest-coverage-by'))
            ->assertOk()
            ->assertSee('2 places harvested across 1 territories')
            ->assertSee('Distinctive area 4611')
            ->assertSee('No coordinates')
            ->assertDontSee('No places harvested yet');

        Livewire::test(HarvestCoverageBy::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-16.harvest-coverage-by.admin'))->assertOk();

        Livewire::test(HarvestCoverageBy::class)->assertOk();
    }
}
