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
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-205'])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseHas('affiliates', ['business_id' => $biz->id, 'partner_name' => 'demo·Alpha Partners']);
        $this->assertDatabaseHas('affiliate_attributions', ['business_id' => $biz->id, 'order_id' => 'demo·ord1']);
        $this->assertDatabaseHas('affiliate_payouts', ['business_id' => $biz->id, 'amount_cents' => 5000]);

        $c1 = Affiliate::count();
        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-205'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertEquals($c1, Affiliate::count());

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--purge' => true, '--only' => 'X-205'])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseMissing('affiliates', ['business_id' => $biz->id, 'partner_name' => 'demo·Alpha Partners']);
        $this->assertDatabaseMissing('affiliate_attributions', ['business_id' => $biz->id, 'order_id' => 'demo·ord1']);
        $this->assertDatabaseMissing('affiliate_payouts', ['business_id' => $biz->id, 'amount_cents' => 5000]);
    }

    public function test_the_pricebook_screens_show_the_demo_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-205'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->actingAs($owner);

        $this->get(route('x-205.portal'))->assertOk()->assertSee('demo·Alpha Partners');
        $this->get(route('x-205.earnings'))->assertOk()->assertSee('demo·ord1');
        $this->get(route('x-205.payout-run'))->assertOk();
    }

    public function test_x163_filler_is_idempotent(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-163'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertSame(5, PriceBookItem::where('business_id', $biz->id)->where('is_sample', true)->count());

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-163'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertSame(5, PriceBookItem::where('business_id', $biz->id)->where('is_sample', true)->count());
        $this->assertSame(1, LocationBook::where('business_id', $biz->id)->where('location_name', 'like', DemoFiller::MARKER.'%')->count());

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--purge' => true, '--only' => 'X-163'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertSame(0, PriceBookItem::where('business_id', $biz->id)->where('is_sample', true)->count());
    }
}
