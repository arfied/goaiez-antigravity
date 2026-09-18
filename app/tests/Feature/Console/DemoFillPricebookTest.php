<?php

namespace Tests\Feature\Console;

use App\Console\DemoFill\DemoFiller;
use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X163\Models\LocationBook;
use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X205\Models\Affiliate;
use App\Support\Tenancy;
use Tests\TestCase;

class DemoFillPricebookTest extends TestCase
{
    protected function tearDown(): void
    {
        Tenancy::forgetAll();
        parent::tearDown();
    }

    public function test_pricebook_fillers_write_marked_rows_once(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-205,X-162,X-171,X-172,X-201,X-01,X-119'])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseHas('affiliates', ['business_id' => $biz->id, 'partner_name' => 'demo·Alpha Partners']);
        $this->assertDatabaseHas('affiliate_attributions', ['business_id' => $biz->id, 'order_id' => 'demo·ord1']);
        $this->assertDatabaseHas('affiliate_payouts', ['business_id' => $biz->id, 'amount_cents' => 5000]);
        $this->assertDatabaseHas('dispatch_assignments', ['business_id' => $biz->id, 'job_id' => 100, 'is_sample' => true]);
        $this->assertDatabaseHas('device_sync_queue', ['business_id' => $biz->id, 'client_mutation_id' => 'demo·mut-2', 'status' => 'conflicted']);
        $this->assertDatabaseHas('device_sync_conflicts', ['business_id' => $biz->id, 'conflict_reason' => 'demo·Server note newer than device note']);
        $this->assertDatabaseHas('portal_links', ['business_id' => $biz->id, 'token' => 'demo-portal-'.$biz->id, 'is_sample' => true]);
        $this->assertDatabaseHas('portal_views', ['business_id' => $biz->id, 'user_agent' => 'demo·Safari on iPhone']);
        $this->assertDatabaseHas('disputes', ['business_id' => $biz->id, 'reason' => 'demo·Duplicate charge', 'status' => 'compiled']);
        $this->assertDatabaseHas('dispute_evidence', ['business_id' => $biz->id, 'evidence_type' => 'call_log']);
        $this->assertDatabaseHas('conversations', ['business_id' => $biz->id, 'subject' => 'demo·Water heater quote']);
        $this->assertDatabaseHas('contact_tags', ['business_id' => $biz->id, 'tag' => 'demo·repeat customer']);
        $this->assertDatabaseHas('facts', ['business_id' => $biz->id, 'key' => 'demo·hours.saturday']);

        $c1 = Affiliate::count();
        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-205,X-162,X-171,X-172,X-201,X-01,X-119'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertEquals($c1, Affiliate::count());

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--purge' => true, '--only' => 'X-205,X-162,X-171,X-172,X-201,X-01,X-119'])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseMissing('affiliates', ['business_id' => $biz->id, 'partner_name' => 'demo·Alpha Partners']);
        $this->assertDatabaseMissing('affiliate_attributions', ['business_id' => $biz->id, 'order_id' => 'demo·ord1']);
        $this->assertDatabaseMissing('affiliate_payouts', ['business_id' => $biz->id, 'amount_cents' => 5000]);
        $this->assertDatabaseMissing('dispatch_assignments', ['business_id' => $biz->id, 'job_id' => 100, 'is_sample' => true]);
        $this->assertDatabaseMissing('device_sync_queue', ['business_id' => $biz->id, 'client_mutation_id' => 'demo·mut-2', 'status' => 'conflicted']);
        $this->assertDatabaseMissing('device_sync_conflicts', ['business_id' => $biz->id, 'conflict_reason' => 'demo·Server note newer than device note']);
        $this->assertDatabaseMissing('portal_links', ['business_id' => $biz->id, 'token' => 'demo-portal-'.$biz->id, 'is_sample' => true]);
        $this->assertDatabaseMissing('portal_views', ['business_id' => $biz->id, 'user_agent' => 'demo·Safari on iPhone']);
        $this->assertDatabaseMissing('disputes', ['business_id' => $biz->id, 'reason' => 'demo·Duplicate charge', 'status' => 'compiled']);
        $this->assertDatabaseMissing('dispute_evidence', ['business_id' => $biz->id, 'evidence_type' => 'call_log']);
        $this->assertDatabaseMissing('conversations', ['business_id' => $biz->id, 'subject' => 'demo·Water heater quote']);
        $this->assertDatabaseMissing('contact_tags', ['business_id' => $biz->id, 'tag' => 'demo·repeat customer']);
        $this->assertDatabaseMissing('facts', ['business_id' => $biz->id, 'key' => 'demo·hours.saturday']);
    }

    public function test_the_pricebook_screens_show_the_demo_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-205,X-162,X-171,X-172,X-201,X-01,X-119'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->actingAs($owner);

        $this->get(route('x-205.portal'))->assertOk()->assertSee('demo·Alpha Partners');
        $this->get(route('x-205.earnings'))->assertOk()->assertSee('demo·ord1');
        $this->get(route('x-205.payout-run'))->assertOk()->assertSee('50.00')->assertSee('requested');
        $this->get(route('x-162.dispatch-board'))->assertOk()->assertSee('Job #100')->assertSee('Job #101');
        $this->get(route('x-172.customerfacing-portal', ['token' => 'demo-portal-'.$biz->id]))->assertOk()->assertSee('Work Order')->assertSee('Sample');
        $this->get(route('x-201.dispute-queue'))->assertOk()->assertSee('demo·Product not received')->assertSee('demo·Duplicate charge')->assertDontSee('demo·Fraudulent');
        $this->get(route('x-201.dispute-card'))->assertOk()->assertSee('demo·Fraudulent')->assertSee('demo·Duplicate charge')->assertSee('2 evidence items')->assertSee('Deadline:');
        $this->get(route('x-01.customers-list'))->assertOk()->assertSee('demo·Marcus');
        $this->get(route('x-01.thread'))->assertOk()->assertSee('demo·Water heater quote');
        $this->get(route('x-01.history'))->assertOk()->assertSee('demo·Is the water heater quote still good?');
        $this->get(route('x-01.person'))->assertOk();
        $this->get(route('x-119.fact-freshness-per'))->assertOk()->assertSee('demo·hours.saturday');
        $this->get(route('x-119.reviewwhatifound-screen'))->assertOk()->assertSee('demo·service.emergency_callout.price_cents');
    }

    public function test_x163_filler_is_idempotent(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-163,X-119'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertSame(5, PriceBookItem::where('business_id', $biz->id)->where('is_sample', true)->count());

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-163,X-119'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertSame(5, PriceBookItem::where('business_id', $biz->id)->where('is_sample', true)->count());
        $this->assertSame(1, LocationBook::where('business_id', $biz->id)->where('location_name', 'like', DemoFiller::MARKER.'%')->count());

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--purge' => true, '--only' => 'X-163,X-119'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertSame(0, PriceBookItem::where('business_id', $biz->id)->where('is_sample', true)->count());
    }
}
