<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X206\Models\Credential;
use App\Support\Tenancy;
use Tests\TestCase;

class DemoFillUiTest extends TestCase
{
    protected function tearDown(): void
    {
        Tenancy::forgetAll();
        parent::tearDown();
    }

    public function test_ui_fillers_write_marked_rows_once(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', [
            'email' => $owner->email,
            '--only' => 'X-206,X-207,X-210,X-190,X-186,X-182',
        ])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseHas('credentials', ['business_id' => $biz->id, 'service_name' => 'demo·Auth']);
        $this->assertDatabaseHas('credential_reveals', ['business_id' => $biz->id, 'service_name' => 'demo·Auth']);
        $this->assertDatabaseHas('push_prompts', ['business_id' => $biz->id, 'prompt_title' => 'demo·Promo']);
        $this->assertDatabaseHas('device_tokens', ['business_id' => $biz->id, 'platform' => 'android', 'retirement_reason' => 'demo·old']);
        $this->assertDatabaseHas('push_deliveries', ['business_id' => $biz->id, 'status' => 'demo·delivered']);
        $this->assertDatabaseHas('promotions', ['business_id' => $biz->id, 'code' => 'demo·DEMO']);
        $this->assertDatabaseHas('promotion_scopes', ['business_id' => $biz->id, 'scope_value' => 'demo·VIP']);
        $this->assertDatabaseHas('promotion_redemptions', ['business_id' => $biz->id, 'order_id' => 'demo·ORD']);
        $this->assertDatabaseHas('partner_pool', ['business_id' => $biz->id, 'company_name' => 'demo·Acme']);
        $this->assertDatabaseHas('referral_slots', ['business_id' => $biz->id, 'category' => 'demo·Plumbing']);
        $this->assertDatabaseHas('campaign_runs', ['business_id' => $biz->id, 'campaign_id' => 'demo·Camp1']);
        $this->assertDatabaseHas('campaign_steps', ['business_id' => $biz->id, 'campaign_id' => 'demo·Camp1']);
        $this->assertDatabaseHas('people', ['business_id' => $biz->id, 'first_name' => 'demo·John']);
        $this->assertDatabaseHas('social_accounts', ['business_id' => $biz->id, 'account_handle' => 'demo·Handle']);
        $this->assertDatabaseHas('social_posts', ['business_id' => $biz->id, 'content_text' => 'demo·Text']);
        $this->assertDatabaseHas('comments', ['business_id' => $biz->id, 'author_name' => 'demo·Auth']);

        $c1 = Credential::count();

        Tenancy::forgetAll();
        $this->artisan('demo:fill', [
            'email' => $owner->email,
            '--only' => 'X-206,X-207,X-210,X-190,X-186,X-182',
        ])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertEquals($c1, Credential::count());

        Tenancy::forgetAll();
        $this->artisan('demo:fill', [
            'email' => $owner->email,
            '--purge' => true,
            '--only' => 'X-206,X-207,X-210,X-190,X-186,X-182',
        ])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseMissing('credentials', ['business_id' => $biz->id, 'service_name' => 'demo·Auth']);
        $this->assertDatabaseMissing('credential_reveals', ['business_id' => $biz->id, 'service_name' => 'demo·Auth']);
        $this->assertDatabaseMissing('push_prompts', ['business_id' => $biz->id, 'prompt_title' => 'demo·Promo']);
        $this->assertDatabaseMissing('device_tokens', ['business_id' => $biz->id, 'retirement_reason' => 'demo·old']);
        $this->assertDatabaseMissing('push_deliveries', ['business_id' => $biz->id, 'status' => 'demo·delivered']);
        $this->assertDatabaseMissing('promotions', ['business_id' => $biz->id, 'code' => 'demo·DEMO']);
        $this->assertDatabaseMissing('promotion_scopes', ['business_id' => $biz->id, 'scope_value' => 'demo·VIP']);
        $this->assertDatabaseMissing('promotion_redemptions', ['business_id' => $biz->id, 'order_id' => 'demo·ORD']);
        $this->assertDatabaseMissing('partner_pool', ['business_id' => $biz->id, 'company_name' => 'demo·Acme']);
        $this->assertDatabaseMissing('referral_slots', ['business_id' => $biz->id, 'category' => 'demo·Plumbing']);
        $this->assertDatabaseMissing('campaign_runs', ['business_id' => $biz->id, 'campaign_id' => 'demo·Camp1']);
        $this->assertDatabaseMissing('campaign_steps', ['business_id' => $biz->id, 'campaign_id' => 'demo·Camp1']);
        $this->assertDatabaseMissing('people', ['business_id' => $biz->id, 'first_name' => 'demo·John']);
        $this->assertDatabaseMissing('social_accounts', ['business_id' => $biz->id, 'account_handle' => 'demo·Handle']);
        $this->assertDatabaseMissing('social_posts', ['business_id' => $biz->id, 'content_text' => 'demo·Text']);
        $this->assertDatabaseMissing('comments', ['business_id' => $biz->id, 'author_name' => 'demo·Auth']);
    }

    public function test_the_ui_screens_show_the_demo_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', [
            'email' => $owner->email,
            '--only' => 'X-206,X-207,X-210,X-190,X-186,X-182',
        ])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->actingAs($owner);

        // X-206
        $this->get(route('x-206.connections'))->assertOk()->assertSee('demo·Auth');
        $this->get(route('x-206.reveal'))->assertOk()->assertSee('demo·Auth');
        $this->get(route('x-206.reveal-log'))->assertOk()->assertSee('demo·Auth');

        // X-207
        $this->get(route('x-207.one-confirmonce-toggle'))->assertOk()->assertSee('demo·Promo');
        $this->get(route('x-207.perplatform-delivery-health'))->assertOk()->assertSee('demo·delivered');
        $this->get(route('x-207.promptcopy-editor'))->assertOk()->assertSee('demo·Promo');
        $this->get(route('x-207.retirement-reasons'))->assertOk()->assertSee('demo·old');

        // X-210
        $this->get(route('x-210.active-promotions'))->assertOk()->assertSee('demo·DEMO');
        $this->get(route('x-210.earnedvsgiven-panel'))->assertOk()->assertSee('10.00'); // 1000 cents
        $this->get(route('x-210.promotion-builder'))->assertOk();
        $this->get(route('x-210.redemptions'))->assertOk()->assertSee('10.00');
        $this->get(route('x-210.targeting-preview'))->assertOk()->assertSee('demo·VIP');

        // X-190
        $this->get(route('x-190.network-map'))->assertOk()->assertSee('demo·Acme');
        $this->get(route('x-190.pool-depth-per'))->assertOk()->assertSee('2 partners');
        $this->get(route('x-190.slot-board'))->assertOk()->assertSee('demo·Plumbing');

        // X-186
        $this->get(route('x-186.audience-preview-count'))->assertOk()->assertSee('1 people');
        $this->get(route('x-186.live-run'))->assertOk()->assertSee('demo·Camp1');
        $this->get(route('x-186.sequence-builder'))->assertOk()->assertSee('demo·Camp1');
        $this->get(route('x-186.stop-log'))->assertOk()->assertSee('demo·Camp2');

        // X-182
        $this->get(route('x-182.connected-accounts'))->assertOk()->assertSee('demo·Handle');
        $this->get(route('x-182.social-queue'))->assertOk()->assertSee('demo·Text');
    }
}
