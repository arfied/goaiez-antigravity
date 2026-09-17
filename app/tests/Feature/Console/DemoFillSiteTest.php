<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\User;
use App\Modules\X121\Models\EntityHistoryRecord;
use App\Support\Tenancy;
use Tests\TestCase;

class DemoFillSiteTest extends TestCase
{
    public function test_site_fillers_write_marked_rows_once(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();

        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-157,X-176,X-177,X-203,X-108,X-121,X-123,X-191,X-16'])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseHas('edge_zones', ['business_id' => $biz->id, 'domain_name' => 'demo·example.com']);
        $this->assertDatabaseHas('deployments', ['business_id' => $biz->id, 'deploy_hash' => 'demo·a1b2c3d4']);
        $this->assertDatabaseHas('schema_snapshots', ['business_id' => $biz->id, 'entity_type' => 'demo·LocalBusiness']);
        $this->assertDatabaseHas('gbp_connections', ['business_id' => $biz->id, 'external_label' => 'demo·Demo Store', 'location_id' => (string) Location::where('business_id', $biz->id)->where('name', 'demo·Demo Location')->value('id')]);
        $this->assertDatabaseHas('locations', ['business_id' => $biz->id, 'name' => 'demo·Demo Location']);
        $this->assertDatabaseHas('gbp_posts', ['business_id' => $biz->id, 'content' => 'demo·Demo post 1']);
        $this->assertDatabaseHas('gbp_state_log', ['business_id' => $biz->id, 'event_type' => 'demo·demo_event']);
        $this->assertDatabaseHas('restore_tests', ['business_id' => $biz->id, 'backup_id' => 'demo·bkp1']);
        $this->assertDatabaseHas('runbooks', ['business_id' => $biz->id, 'title' => 'demo·Demo Runbook']);
        $this->assertDatabaseHas('resources', ['business_id' => $biz->id, 'name' => 'demo·Demo Resource']);
        $this->assertDatabaseHas('appointments', ['business_id' => $biz->id, 'service_name' => 'demo·Demo Service']);
        $this->assertDatabaseHas('waitlists', ['business_id' => $biz->id, 'customer_name' => 'demo·Demo Customer']);
        $this->assertDatabaseHas('people', ['business_id' => $biz->id, 'first_name' => 'demo·Dana']);
        $this->assertDatabaseHas('entity_history', ['business_id' => $biz->id, 'entity_type' => 'demo·people']);
        $this->assertDatabaseHas('dead_letters', ['business_id' => $biz->id, 'error_message' => 'demo·Webhook endpoint returned 503']);
        $this->assertDatabaseHas('link_placements', ['business_id' => $biz->id, 'anchor_text' => 'demo·local plumbing guide']);
        $this->assertDatabaseHas('geo_grids', ['business_id' => $biz->id, 'grid_name' => 'demo·Downtown grid']);
        $this->assertDatabaseHas('service_polygons', ['business_id' => $biz->id, 'polygon_name' => 'demo·North side']);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-157,X-176,X-177,X-203,X-108,X-121,X-123,X-191,X-16'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertSame(2, EntityHistoryRecord::where('business_id', $biz->id)->where('entity_type', 'demo·people')->count());

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--purge' => true, '--only' => 'X-157,X-176,X-177,X-203,X-108,X-121,X-123,X-191,X-16'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertDatabaseMissing('edge_zones', ['business_id' => $biz->id, 'domain_name' => 'demo·example.com']);
        $this->assertDatabaseMissing('deployments', ['business_id' => $biz->id, 'deploy_hash' => 'demo·a1b2c3d4']);
        $this->assertDatabaseMissing('schema_snapshots', ['business_id' => $biz->id, 'entity_type' => 'demo·LocalBusiness']);
        $this->assertDatabaseMissing('gbp_connections', ['business_id' => $biz->id, 'external_label' => 'demo·Demo Store']);
        $this->assertDatabaseMissing('locations', ['business_id' => $biz->id, 'name' => 'demo·Demo Location']);
        $this->assertDatabaseMissing('gbp_posts', ['business_id' => $biz->id, 'content' => 'demo·Demo post 1']);
        $this->assertDatabaseMissing('gbp_state_log', ['business_id' => $biz->id, 'event_type' => 'demo·demo_event']);
        $this->assertDatabaseMissing('restore_tests', ['business_id' => $biz->id, 'backup_id' => 'demo·bkp1']);
        $this->assertDatabaseMissing('runbooks', ['business_id' => $biz->id, 'title' => 'demo·Demo Runbook']);
        $this->assertDatabaseMissing('resources', ['business_id' => $biz->id, 'name' => 'demo·Demo Resource']);
        $this->assertDatabaseMissing('appointments', ['business_id' => $biz->id, 'service_name' => 'demo·Demo Service']);
        $this->assertDatabaseMissing('waitlists', ['business_id' => $biz->id, 'customer_name' => 'demo·Demo Customer']);
        $this->assertDatabaseMissing('people', ['business_id' => $biz->id, 'first_name' => 'demo·Dana']);
        $this->assertDatabaseMissing('entity_history', ['business_id' => $biz->id, 'entity_type' => 'demo·people']);
        $this->assertDatabaseMissing('dead_letters', ['business_id' => $biz->id, 'error_message' => 'demo·Webhook endpoint returned 503']);
        $this->assertDatabaseMissing('link_placements', ['business_id' => $biz->id, 'anchor_text' => 'demo·local plumbing guide']);
        $this->assertDatabaseMissing('geo_grids', ['business_id' => $biz->id, 'grid_name' => 'demo·Downtown grid']);
        $this->assertDatabaseMissing('service_polygons', ['business_id' => $biz->id, 'polygon_name' => 'demo·North side']);
    }

    public function test_the_site_screens_show_the_demo_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-157,X-176,X-177,X-203,X-108,X-121,X-123,X-191,X-16'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->actingAs($owner);

        $this->get(route('x-157.edge-status-per'))->assertOk()->assertSee('demo·example.com');
        $this->get(route('x-176.seo-tab-website'))->assertOk()->assertSee('demo·LocalBusiness');
        $this->get(route('x-177.gbp-card'))->assertOk()->assertSee('demo·Demo Store');
        $this->get(route('x-177.suspensionrisk-events-fleetwide'))->assertOk()->assertSee('demo·Demo Store');
        $this->get(route('x-203.dr-dashboard'))->assertOk()->assertSee('demo·bkp1');
        $this->get(route('x-203.restorationtest-log'))->assertOk()->assertSee('demo·bkp1');
        $this->get(route('x-203.runbook-runner'))->assertOk()->assertSee('demo·Demo Runbook');
        $this->get(route('x-108.waitlist'))->assertOk()->assertSee('demo·Demo Customer');
        $this->get(route('x-108.calendar'))->assertOk()->assertSee('demo·Demo Service');
        $this->get(route('x-121.entity-history-viewer'))->assertOk()->assertSee('demo·people');
        $this->get(route('x-123.dlq-request-inspector'))->assertOk()->assertSee('demo·Webhook endpoint returned 503');
        $this->get(route('x-191.links-earned'))->assertOk()->assertSee('demo·local plumbing guide');
        $this->get(route('x-191.pitchacquire-ratio'))->assertOk()->assertSee('3 pitches sent')->assertSee('2 links earned');
        $this->get(route('x-16.geogrid-map'))->assertOk()->assertSee('demo·Downtown grid')->assertSee('12 of 25 points scanned');
        $this->get(route('x-16.servicearea-polygon'))->assertOk()->assertSee('demo·North side')->assertSee('demo·South side');
    }
}
