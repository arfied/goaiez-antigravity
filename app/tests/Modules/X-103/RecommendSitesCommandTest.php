<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Models\Location;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class RecommendSitesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    use RefreshesTenantDatabase;

    public function test_command_runs_action_for_businesses(): void
    {
        $owner = User::factory()->create();
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $location = Location::factory()->create(['business_id' => $biz->id]);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
        ]);
        $page->draft_blocks = [
            ['type' => 'contact', 'source' => 'inventory'],
        ];
        $page->save();

        Tenancy::forgetAll();

        $this->artisan('x103:recommend-sites')->assertSuccessful();

        Tenancy::set($biz->id);
        $this->assertDatabaseHas('site_recommendations', ['business_id' => $biz->id, 'code' => 'hours_missing']);
    }
}
