<?php

declare(strict_types=1);

namespace Tests\Modules\X16;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X16\Actions\MapsGeogridAction;
use App\Modules\X16\Actions\MapsHarvestAction;
use App\Modules\X16\Actions\MapsPolygonAction;
use App\Modules\X16\Models\GeoGrid;
use App\Modules\X16\Models\PlacesRecord;
use App\Modules\X16\Models\ServicePolygon;
use App\Modules\X16\Ui\GeogridMap;
use App\Modules\X16\Ui\HarvestCoverageBy;
use App\Modules\X16\Ui\ServiceareaPolygon;
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
            ->assertSee('points scanned')
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
        Livewire::test(ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->assertOk()
            ->assertSee('No service area yet');
    }

    public function test_servicearea_polygon_sample_state(): void
    {
        Livewire::test(ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->call('toggleSample')
            ->assertSee('Downtown service area')
            ->assertSee('North side')
            ->assertSee('Spans 41.8000…41.9000 lat · -87.7000…-87.6000 lng')
            ->set('name', 'New Sample')
            ->set('pointsText', "1.1,2.2\n3.3,4.4\n5.5,6.6")
            ->call('define')
            ->call('toggle', 9991);

        $this->assertSame(0, ServicePolygon::where('business_id', $this->businessId)->count());
    }

    public function test_servicearea_polygon_define_success(): void
    {
        Livewire::test(ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->set('name', 'South Side')
            ->set('pointsText', "41.8, -87.6\n41.9, -87.6\n41.9, -87.5\n41.8, -87.5")
            ->call('define')
            ->assertSee('South Side')
            ->assertSee('4 points')
            ->assertSee('Active')
            ->assertSee('Spans 41.8000…41.9000 lat · -87.6000…-87.5000 lng')
            ->assertDontSee('Action failed');

        $this->assertSame(1, ServicePolygon::where('business_id', $this->businessId)->count());
    }

    public function test_servicearea_polygon_define_empty_name(): void
    {
        Livewire::test(ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->set('name', '')
            ->set('pointsText', "41.8, -87.6\n41.9, -87.6\n41.9, -87.5")
            ->call('define')
            ->assertHasErrors('name')
            ->assertSee('Name cannot be empty');
    }

    public function test_servicearea_polygon_define_not_enough_points(): void
    {
        Livewire::test(ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->set('name', 'Two points')
            ->set('pointsText', "41.8, -87.6\n41.9, -87.6")
            ->call('define')
            ->assertSee('a service area needs at least three points')
            ->assertSee('Not a service area')
            ->assertDontSee('Action failed');

        $this->assertSame(0, ServicePolygon::where('business_id', $this->businessId)->count());
    }

    /**
     * [G17-22] fence-refusal test
     */
    public function test_servicearea_polygon_define_fence_refusal(): void
    {
        Livewire::test(ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->set('name', 'Tiny Box')
            ->set('pointsText', "41.8001, -87.6001\n41.8001, -87.6002\n41.8002, -87.6002\n41.8002, -87.6001")
            ->call('define')
            ->assertSee('a fence around one building is geo-fenced ad targeting')
            ->assertSee('Not a service area')
            ->assertDontSee('Action failed');

        $this->assertSame(0, ServicePolygon::where('business_id', $this->businessId)->count());
    }

    public function test_servicearea_polygon_toggle_status(): void
    {
        $polygon = ServicePolygon::create([
            'business_id' => $this->businessId,
            'polygon_name' => 'Toggle Box',
            'coordinates' => [[41.8, -87.6], [41.9, -87.6], [41.9, -87.5]],
            'is_active' => true,
        ]);

        Livewire::test(ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->assertSee('Toggle Box')
            ->call('toggle', $polygon->id)
            ->assertSee('Inactive')
            ->assertDontSee('Action failed');

        $this->assertFalse($polygon->fresh()->is_active);
    }

    public function test_servicearea_polygon_error_state(): void
    {
        Livewire::test(ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->call('toggle', 999999)
            ->assertSee('Action failed')
            ->assertDontSee('No query results');
    }

    public function test_servicearea_polygon_define_malformed_line(): void
    {
        Livewire::test(ServiceareaPolygon::class, ['businessId' => $this->businessId])
            ->set('name', 'Bad Line')
            ->set('pointsText', "41.8, -87.6\nbadline\n41.9, -87.5")
            ->call('define')
            ->assertHasErrors(['pointsText' => 'One lat,lng pair per line']);
    }

    public function test_servicearea_polygon_get_shows_seeded_polygon(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        ServicePolygon::create([
            'business_id' => $biz->id,
            'polygon_name' => 'Seeded Polygon',
            'coordinates' => [[41.8, -87.6], [41.9, -87.6], [41.9, -87.5]],
            'is_active' => true,
        ]);

        $this->get(route('x-16.servicearea-polygon'))
            ->assertOk()
            ->assertSee('Seeded Polygon');
    }

    public function test_harvest_coverage_by_mount_and_empty(): void
    {
        Livewire::test(HarvestCoverageBy::class, ['businessId' => $this->businessId])
            ->assertOk()
            ->assertSee('No places harvested yet');
    }

    public function test_harvest_coverage_by_counts_by_territory(): void
    {
        app(MapsPolygonAction::class)->define($this->businessId, 'Downtown', [
            [41.80, -87.70], [41.90, -87.70], [41.90, -87.60], [41.80, -87.60],
        ]);

        app(MapsHarvestAction::class)->harvestPlaces($this->businessId, [
            ['place_id' => '1', 'name' => 'Inside One', 'address' => 'A1', 'latitude' => 41.85, 'longitude' => -87.65],
            ['place_id' => '2', 'name' => 'Inside Two', 'address' => 'A2', 'latitude' => 41.82, 'longitude' => -87.62],
            ['place_id' => '3', 'name' => 'Far Away', 'address' => 'A3', 'latitude' => 42.50, 'longitude' => -88.50],
            ['place_id' => '4', 'name' => 'No Coords', 'address' => 'A4', 'latitude' => null, 'longitude' => null],
        ]);

        Livewire::test(HarvestCoverageBy::class, ['businessId' => $this->businessId])
            ->assertSee('Downtown')
            ->assertSee('2 places')
            ->assertSee('Outside every territory')
            ->assertSee('No coordinates')
            ->assertSee('4 places harvested across 1 territories');
    }

    /**
     * [G7-21] chains filtered out of the prospect set — the screen never shows one
     */
    public function test_harvest_coverage_by_chains_never_listed(): void
    {
        app(MapsHarvestAction::class)->harvestPlaces($this->businessId, [
            ['place_id' => 'bob', 'name' => 'Bob Plumbing', 'address' => 'A', 'is_chain' => false, 'latitude' => 41.85, 'longitude' => -87.65],
            ['place_id' => 'roto', 'name' => 'Roto-Rooter Corporate', 'address' => 'B', 'is_chain' => true, 'latitude' => 41.85, 'longitude' => -87.65],
        ]);

        Livewire::test(HarvestCoverageBy::class, ['businessId' => $this->businessId])
            ->assertSee('1 places harvested')
            ->assertDontSee('Roto-Rooter');

        $this->assertSame(1, PlacesRecord::where('business_id', $this->businessId)->count());
    }

    public function test_harvest_coverage_by_no_territory_yet(): void
    {
        app(MapsHarvestAction::class)->harvestPlaces($this->businessId, [
            ['place_id' => 'p1', 'name' => 'P1', 'address' => 'A', 'latitude' => 41.85, 'longitude' => -87.65],
        ]);

        Livewire::test(HarvestCoverageBy::class, ['businessId' => $this->businessId])
            ->assertSee('Outside every territory')
            ->assertSee('across 0 territories');
    }

    public function test_harvest_coverage_by_sample_state(): void
    {
        Livewire::test(HarvestCoverageBy::class, ['businessId' => $this->businessId])
            ->call('toggleSample')
            ->assertSee('Downtown')
            ->assertSee('20 places harvested');
    }

    public function test_harvest_coverage_by_get_shows_seeded_place(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        app(MapsPolygonAction::class)->define($biz->id, 'Seeded territory', [
            [41.80, -87.70], [41.90, -87.70], [41.90, -87.60], [41.80, -87.60],
        ]);

        app(MapsHarvestAction::class)->harvestPlaces($biz->id, [
            ['place_id' => 'seed1', 'name' => 'Seed 1', 'address' => 'A1', 'latitude' => 41.85, 'longitude' => -87.65],
        ]);

        $this->get(route('x-16.harvest-coverage-by'))
            ->assertOk()
            ->assertSee('Seeded territory');
    }
}
