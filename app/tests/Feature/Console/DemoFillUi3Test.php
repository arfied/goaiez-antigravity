<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X07\Models\Forecast;
use App\Support\Tenancy;
use Tests\TestCase;

class DemoFillUi3Test extends TestCase
{
    protected function tearDown(): void
    {
        Tenancy::forgetAll();
        parent::tearDown();
    }

    public function test_ui3_fillers_write_marked_rows_once(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', [
            'email' => $owner->email,
            '--only' => 'X-07,X-124,X-132,X-138,X-139,X-183,X-192',
        ])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseHas('forecasts', ['business_id' => $biz->id, 'period_month' => 'demo·October 2026']);
        $this->assertDatabaseHas('assistant_unsupported', ['business_id' => $biz->id, 'utterance' => 'demo·can you walk my dog']);
        $this->assertDatabaseHas('assistant_recommendations', ['business_id' => $biz->id, 'title' => 'demo·Enable feature']);
        $this->assertDatabaseHas('person_links', ['business_id' => $biz->id, 'confidence_rate' => 0.999]);
        $this->assertDatabaseHas('resolution_evidence', ['business_id' => $biz->id, 'field_value' => 'demo·evidence1']);
        $this->assertDatabaseHas('attribution_queries', ['business_id' => $biz->id, 'attribution_status' => 'demo·single']);
        $this->assertDatabaseHas('roi_snapshots', ['business_id' => $biz->id, 'campaign_name' => 'demo·Autumn Promo']);
        $this->assertDatabaseHas('ad_connections', ['business_id' => $biz->id, 'platform' => 'demo·GoogleAds']);
        $this->assertDatabaseHas('conversion_uploads', ['business_id' => $biz->id, 'gclid_or_fbc' => 'demo·101']);
        $this->assertDatabaseHas('content_drafts', ['business_id' => $biz->id, 'title' => 'demo·Spring Maintenance Guide']);
        $this->assertDatabaseHas('gate_results', ['business_id' => $biz->id, 'rejection_reason' => 'demo·Missing citation']);
        $this->assertDatabaseHas('directory_memberships', ['business_id' => $biz->id, 'directory_name' => 'demo·Yelp']);

        $c1 = Forecast::count();

        Tenancy::forgetAll();
        $this->artisan('demo:fill', [
            'email' => $owner->email,
            '--only' => 'X-07,X-124,X-132,X-138,X-139,X-183,X-192',
        ])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertEquals($c1, Forecast::count());

        Tenancy::forgetAll();
        $this->artisan('demo:fill', [
            'email' => $owner->email,
            '--purge' => true,
            '--only' => 'X-07,X-124,X-132,X-138,X-139,X-183,X-192',
        ])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseMissing('forecasts', ['business_id' => $biz->id, 'period_month' => 'demo·October 2026']);
        $this->assertDatabaseMissing('assistant_unsupported', ['business_id' => $biz->id, 'utterance' => 'demo·can you walk my dog']);
        $this->assertDatabaseMissing('assistant_recommendations', ['business_id' => $biz->id, 'title' => 'demo·Enable feature']);
        $this->assertDatabaseMissing('person_links', ['business_id' => $biz->id, 'confidence_rate' => 0.999]);
        $this->assertDatabaseMissing('resolution_evidence', ['business_id' => $biz->id, 'field_value' => 'demo·evidence1']);
        $this->assertDatabaseMissing('attribution_queries', ['business_id' => $biz->id, 'attribution_status' => 'demo·single']);
        $this->assertDatabaseMissing('roi_snapshots', ['business_id' => $biz->id, 'campaign_name' => 'demo·Autumn Promo']);
        $this->assertDatabaseMissing('ad_connections', ['business_id' => $biz->id, 'platform' => 'demo·GoogleAds']);
        $this->assertDatabaseMissing('conversion_uploads', ['business_id' => $biz->id, 'gclid_or_fbc' => 'demo·101']);
        $this->assertDatabaseMissing('content_drafts', ['business_id' => $biz->id, 'title' => 'demo·Spring Maintenance Guide']);
        $this->assertDatabaseMissing('gate_results', ['business_id' => $biz->id, 'rejection_reason' => 'demo·Missing citation']);
        $this->assertDatabaseMissing('directory_memberships', ['business_id' => $biz->id, 'directory_name' => 'demo·Yelp']);
    }

    public function test_the_ui3_screens_show_the_demo_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', [
            'email' => $owner->email,
            '--only' => 'X-07,X-124,X-132,X-138,X-139,X-183,X-192',
        ])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->actingAs($owner);

        // X-07
        $this->get(route('x-07.forecast-risk-tiles'))->assertOk()->assertSee('demo·October 2026');

        // X-124
        $this->get(route('x-124.assistantunsupported-log'))->assertOk()->assertSee('demo·can you walk my dog');
        $this->get(route('x-124.preview-card'))->assertOk();

        // X-132
        $this->get(route('x-132.person-timeline'))->assertOk()->assertSee('No evidence found');
        $this->get(route('x-132.resolution-rate-confidence'))->assertOk()->assertSee('0.999');

        // X-138
        $this->get(route('x-138.attribution-row'))->assertOk()->assertSee('Demo·single');
        $this->get(route('x-138.roi-dashboard'))->assertOk()->assertSee('demo·Autumn Promo');

        // X-139
        $this->get(route('x-139.adaccount-connect-card'))->assertOk()->assertSee('demo·GoogleAds');
        $this->get(route('x-139.conversions-pushed-tile'))->assertOk()->assertSee('Uploaded Conversions: 2');
        $this->get(route('x-139.rejection-rate'))->assertOk()->assertSee('demo·Invalid format');

        // X-183
        $this->get(route('x-183.draft-review'))->assertOk()->assertSee('demo·Spring Maintenance Guide');
        $this->get(route('x-183.gate-rejection-reasons'))->assertOk()->assertSee('demo·Missing citation');

        // X-192
        $this->get(route('x-192.memberships-list'))->assertOk()->assertSee('demo·Yelp');
    }
}
