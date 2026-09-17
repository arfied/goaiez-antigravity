<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X129\Models\RedirectMap;
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
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-129'])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseHas('redirect_maps', ['business_id' => $biz->id, 'source_url' => 'demo·/old-home']);
        $this->assertDatabaseHas('redirect_maps', ['business_id' => $biz->id, 'source_url' => 'demo·/old-contact']);
        $this->assertDatabaseHas('redirect_maps', ['business_id' => $biz->id, 'source_url' => 'demo·/old-about']);

        $c1 = RedirectMap::count();
        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-129'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertEquals($c1, RedirectMap::count());

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--purge' => true, '--only' => 'X-129'])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseMissing('redirect_maps', ['business_id' => $biz->id, 'source_url' => 'demo·/old-home']);
    }

    public function test_the_reviews_screens_show_the_demo_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'X-129'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->actingAs($owner);

        $this->get(route('x-129.cutover-queue'))->assertOk()->assertSee('demo·/old-home');
        $this->get(route('x-129.migration-card'))->assertOk()->assertSee('2 of 3');
    }
}
