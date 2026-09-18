<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X129\Models\RedirectMap;
use App\Modules\X218\Models\InfluencerProfile;
use App\Support\Tenancy;
use Tests\TestCase;

class DemoFillReviewsTest extends TestCase
{
    protected function tearDown(): void
    {
        Tenancy::forgetAll();
        parent::tearDown();
    }

    public function test_reviews_fillers_write_marked_rows_once(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-129,X-218'])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseHas('redirect_maps', ['business_id' => $biz->id, 'source_url' => 'demo·/old-home']);
        $this->assertDatabaseHas('redirect_maps', ['business_id' => $biz->id, 'source_url' => 'demo·/old-contact']);
        $this->assertDatabaseHas('redirect_maps', ['business_id' => $biz->id, 'source_url' => 'demo·/old-about']);
        $this->assertDatabaseHas('influencer_profiles', ['business_id' => $biz->id, 'handle' => 'demo·techguru']);

        $c1 = RedirectMap::count();
        $c2 = InfluencerProfile::count();
        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-129,X-218'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertEquals($c1, RedirectMap::count());
        $this->assertEquals($c2, InfluencerProfile::count());

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--purge' => true, '--only' => 'X-129,X-218'])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseMissing('redirect_maps', ['business_id' => $biz->id, 'source_url' => 'demo·/old-home']);
        $this->assertDatabaseMissing('influencer_profiles', ['business_id' => $biz->id, 'handle' => 'demo·techguru']);
    }

    public function test_the_reviews_screens_show_the_demo_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-129,X-218'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->actingAs($owner);

        $this->get(route('x-129.cutover-queue'))->assertOk()->assertSee('demo·/old-home');
        $this->get(route('x-129.migration-card'))->assertOk()->assertSee('2 of 3');

        $this->get(route('x-218.deal-tracker'))->assertOk()->assertSee('demo·techguru');
        $this->get(route('x-218.discovery-board'))->assertOk()->assertSee('demo·techguru');
    }
}
