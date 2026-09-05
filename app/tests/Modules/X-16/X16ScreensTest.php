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
            ->assertSee('Downtown')
            ->assertDontSee('Action failed');

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

    public function test_servicearea_polygon_mount_and_empty(): void
    {
        Livewire::test(\App\Modules\X16\Ui\ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->assertOk()
            ->assertSee('No polygons defined yet');
    }

    public function test_servicearea_polygon_sample_state(): void
    {
        Livewire::test(\App\Modules\X16\Ui\ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->call('toggleSample')
            ->assertSee('Downtown Area')
            ->assertSee('North Side')
            ->set('name', 'New Sample')
            ->set('pointsText', "1.1,2.2\n3.3,4.4\n5.5,6.6")
            ->call('define')
            ->call('toggle', 1);

        $this->assertSame(0, \App\Modules\X16\Models\ServicePolygon::where('business_id', $this->businessId)->count());
    }

    public function test_servicearea_polygon_define_success(): void
    {
        Livewire::test(\App\Modules\X16\Ui\ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->set('name', 'South Side')
            ->set('pointsText', "41.8, -87.6\n41.9, -87.6\n41.9, -87.5\n41.8, -87.5")
            ->call('define')
            ->assertSee('South Side');

        $this->assertSame(1, \App\Modules\X16\Models\ServicePolygon::where('business_id', $this->businessId)->count());
    }

    public function test_servicearea_polygon_define_empty_name(): void
    {
        Livewire::test(\App\Modules\X16\Ui\ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->set('name', '')
            ->set('pointsText', "41.8, -87.6\n41.9, -87.6\n41.9, -87.5")
            ->call('define')
            ->assertSee('Name cannot be empty');
    }

    public function test_servicearea_polygon_define_not_enough_points(): void
    {
        Livewire::test(\App\Modules\X16\Ui\ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->set('name', 'Two points')
            ->set('pointsText', "41.8, -87.6\n41.9, -87.6")
            ->call('define')
            ->assertSee('Polygon requires at least 3 points.');
    }

    /**
     * [G17-22] fence-refusal test
     */
    public function test_servicearea_polygon_define_fence_refusal(): void
    {
        Livewire::test(\App\Modules\X16\Ui\ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->set('name', 'Tiny Box')
            ->set('pointsText', "41.8001, -87.6001\n41.8001, -87.6002\n41.8002, -87.6002\n41.8002, -87.6001")
            ->call('define')
            ->assertSee('a fence around one building is geo-fenced ad targeting');
    }

    public function test_servicearea_polygon_toggle_status(): void
    {
        $polygon = \App\Modules\X16\Models\ServicePolygon::create([
            'business_id' => $this->businessId,
            'polygon_name' => 'Toggle Box',
            'coordinates' => [[41.8, -87.6], [41.9, -87.6], [41.9, -87.5]],
            'is_active' => true,
        ]);

        Livewire::test(\App\Modules\X16\Ui\ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->assertSee('Toggle Box')
            ->call('toggle', $polygon->id);

        $this->assertFalse($polygon->fresh()->is_active);
    }

    public function test_servicearea_polygon_get_shows_seeded_polygon(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        \App\Modules\X16\Models\ServicePolygon::create([
            'business_id' => $biz->id,
            'polygon_name' => 'Seeded Polygon',
            'coordinates' => [[41.8, -87.6], [41.9, -87.6], [41.9, -87.5]],
            'is_active' => true,
        ]);

        $this->get(route('x-16.servicearea-polygon'))
            ->assertOk()
            ->assertSee('Seeded Polygon');
    }
}