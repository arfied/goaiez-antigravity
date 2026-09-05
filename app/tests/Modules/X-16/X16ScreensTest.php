<?php

declare(strict_types=1);

namespace Tests\Modules\X16;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X16\Actions\MapsGeogridAction;
use App\Modules\X16\Models\GeoGrid;
use App\Modules\X16\Ui\GeogridMap;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class X16ScreensTest extends TestCase
{
    protected int $businessId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->businessId = TestCase::provisionTenant(['name' => 'Grid Tenant'])->id;
        Tenancy::set($this->businessId);

        GeoGrid::where('business_id', $this->businessId)->delete();
    }

    public function test_geogrid_map_mount_and_empty(): void
    {
        Livewire::test(GeogridMap::class, ['businessId' => $this->businessId])
            ->assertOk()
            ->assertSee('No grids yet');
    }

    public function test_geogrid_map_generate_creates_25_points(): void
    {
        Livewire::test(GeogridMap::class, ['businessId' => $this->businessId])
            ->set('gridName', 'Downtown')
            ->set('centerLat', '41.8781')
            ->set('centerLng', '-87.6298')
            ->set('radiusKm', 10)
            ->call('generate')
            ->assertSee('Downtown')
            ->assertSee('Waiting on a Places key')
            ->assertSee('0 of 25');

        $grid = GeoGrid::where('business_id', $this->businessId)->first();
        $this->assertNotNull($grid);
        $this->assertEquals(25, count($grid->grid_points));
    }

    public function test_geogrid_map_regenerate(): void
    {
        $grid = app(MapsGeogridAction::class)->generateGrid($this->businessId, 'Downtown', 41.8781, -87.6298, 10);

        Livewire::test(GeogridMap::class, ['businessId' => $this->businessId])
            ->call('regenerate', $grid->id)
            ->assertSee('Downtown');

        $this->assertEquals(1, GeoGrid::where('business_id', $this->businessId)->count());
        $grid->refresh();
        $this->assertEquals(25, count($grid->grid_points));
    }

    public function test_geogrid_map_error_state(): void
    {
        Livewire::test(GeogridMap::class, ['businessId' => $this->businessId])
            ->call('regenerate', 999999)
            ->assertSee('Action failed');
    }

    public function test_geogrid_map_sample_state(): void
    {
        Livewire::test(GeogridMap::class, ['businessId' => $this->businessId])
            ->call('toggleSample')
            ->assertSee('Downtown Sample Grid')
            ->set('gridName', 'Should not write')
            ->set('centerLat', '41.8781')
            ->set('centerLng', '-87.6298')
            ->set('radiusKm', 10)
            ->call('generate');

        $this->assertSame(0, GeoGrid::where('business_id', $this->businessId)->count());
    }

    public function test_geogrid_map_get_shows_seeded_grid(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        app(MapsGeogridAction::class)->generateGrid($biz->id, 'Seeded Grid', 41.8781, -87.6298, 10);

        $this->get(route('x-16.geogrid-map'))
            ->assertOk()
            ->assertSee('Seeded Grid');
    }
}
