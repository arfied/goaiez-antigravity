<?php

declare(strict_types=1);

namespace Tests\Modules\X157;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X157\Actions\LatestDeploymentForPageAction;
use App\Modules\X157\Models\Deployment;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class PublicBookingRouteTest extends TestCase
{
    use RefreshesTenantDatabase;

    private $biz;

    private $deploy;

    private $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->biz = TestCase::provisionTenant(['name' => 'Edge Tenant', 'currency' => 'USD', 'owner_user_id' => $this->owner->id]);
        DB::statement("SET app.business_id = '{$this->biz->id}'");

        $zone = app(EdgeProvisionAction::class)->handle($this->biz->id, 'acme-hvac.com', true);

        $page = Page::create([
            'business_id' => $this->biz->id,
            'title' => 'Home',
            'slug' => 'home',
        ]);

        $commitId = 'commit_'.Str::random(16);
        PageVersion::create([
            'business_id' => $this->biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [
                ['type' => 'pixel_script'],
                ['type' => 'chat_widget'],
                ['type' => 'form_capture'],
                ['type' => 'dni_script'],
                ['type' => 'booking_form', 'heading' => 'Distinctive booking 4471', 'service' => 'Haircut'],
            ],
            'pixel_installed' => true,
        ]);

        $this->deploy = app(EdgeDeployAction::class)->handle(
            businessId: $this->biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commitId,
            businessName: $this->biz->name
        );
    }

    public function test_a_visitor_can_request_a_booking_and_the_owner_sees_it_on_the_waitlist(): void
    {
        Tenancy::forgetAll();

        $this->postJson("/sites/{$this->biz->id}/{$this->deploy['deploy_hash']}/book", [
            'name' => 'Distinctive Visitor 4471',
            'phone' => '+15125567731',
            'service' => 'Haircut',
            'preferred_date' => now()->addDays(2)->toDateString(),
        ])->assertStatus(201)->assertJson(['status' => 'requested']);

        Tenancy::set((int) $this->biz->id);
        $this->assertDatabaseHas('waitlists', ['business_id' => $this->biz->id, 'customer_name' => 'Distinctive Visitor 4471', 'service_name' => 'Haircut', 'status' => 'pending']);
        $this->assertDatabaseHas('people', ['business_id' => $this->biz->id, 'phone' => '+15125567731']);

        $this->actingAs($this->owner);
        $this->get(route('x-108.waitlist'))->assertOk()->assertSee('Distinctive Visitor 4471')->assertDontSee('Nobody is waiting');
    }

    public function test_a_booking_request_remembers_the_deployed_page_it_came_from(): void
    {
        Tenancy::forgetAll();

        $this->postJson("/sites/{$this->biz->id}/{$this->deploy['deploy_hash']}/book", [
            'name' => 'Distinctive Visitor 4551',
            'phone' => '+15125567731',
            'service' => 'Haircut',
            'preferred_date' => now()->addDays(2)->toDateString(),
        ])->assertStatus(201)->assertJson(['status' => 'requested']);

        Tenancy::set((int) $this->biz->id);
        $this->assertDatabaseHas('waitlists', ['customer_name' => 'Distinctive Visitor 4551', 'deploy_hash' => $this->deploy['deploy_hash']]);
    }

    public function test_an_under_18_visitor_is_refused_before_any_write(): void
    {
        Tenancy::forgetAll();

        $this->postJson("/sites/{$this->biz->id}/{$this->deploy['deploy_hash']}/book", [
            'name' => 'Distinctive Visitor 4471',
            'phone' => '+15125567731',
            'service' => 'Haircut',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'age' => 16,
        ])->assertStatus(422)->assertJson(['reason' => 'under_18']);

        Tenancy::set((int) $this->biz->id);
        $this->assertDatabaseMissing('waitlists', ['customer_name' => 'Distinctive Visitor 4471']);
        $this->assertDatabaseMissing('people', ['phone' => '+15125567731']);
    }

    public function test_a_booking_request_needs_a_future_date_and_a_phone(): void
    {
        Tenancy::forgetAll();

        $this->postJson("/sites/{$this->biz->id}/{$this->deploy['deploy_hash']}/book", [
            'name' => 'Distinctive Visitor 4471',
            'service' => 'Haircut',
            'preferred_date' => now()->addDays(2)->toDateString(),
        ])->assertStatus(422);

        $this->postJson("/sites/{$this->biz->id}/{$this->deploy['deploy_hash']}/book", [
            'name' => 'Distinctive Visitor 4471',
            'phone' => '+15125567731',
            'service' => 'Haircut',
            'preferred_date' => now()->subDay()->toDateString(),
        ])->assertStatus(422);

        Tenancy::set((int) $this->biz->id);
        $this->assertDatabaseMissing('waitlists', ['customer_name' => 'Distinctive Visitor 4471']);
        $this->assertDatabaseMissing('people', ['phone' => '+15125567731']);
    }

    public function test_an_unknown_deployment_is_404(): void
    {
        Tenancy::forgetAll();

        $this->postJson("/sites/{$this->biz->id}/deploy_nosuchhash4621/book", [
            'name' => 'Distinctive Visitor 4471',
            'phone' => '+15125567731',
            'service' => 'Haircut',
            'preferred_date' => now()->addDays(2)->toDateString(),
        ])->assertStatus(404);
    }

    public function test_the_deployed_booking_form_posts_to_a_route_that_accepts_it(): void
    {
        $html = Storage::disk('local')->get("sites/{$this->deploy['deploy_hash']}.html");
        preg_match('/action="([^"]+\/book)"/', $html, $m);

        Tenancy::forgetAll();

        $this->postJson($m[1], [
            'name' => 'Distinctive Visitor 4471',
            'phone' => '+15125567731',
            'service' => 'Haircut',
            'preferred_date' => now()->addDays(2)->toDateString(),
        ])->assertStatus(201);
    }

    public function test_a_variant_arm_deploys_beside_control_without_superseding_it(): void
    {
        $page = Page::first();
        $commitId = 'commit_'.Str::random(16);
        PageVersion::create([
            'business_id' => $this->biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [
                ['type' => 'hero', 'headline' => 'Distinctive variant headline 4561'],
            ],
            'pixel_installed' => true,
        ]);

        $controlDeployment = Deployment::where('deploy_hash', $this->deploy['deploy_hash'])->first();

        $variantDeploy = app(EdgeDeployAction::class)->handle(
            businessId: $this->biz->id,
            edgeZoneId: $controlDeployment->edge_zone_id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commitId,
            businessName: $this->biz->name,
            pageVariantId: 4562
        );

        $variantDeployment = Deployment::where('deploy_hash', $variantDeploy['deploy_hash'])->first();

        $this->assertEquals('deployed', $controlDeployment->fresh()->status);
        $this->assertEquals('deployed', $variantDeployment->fresh()->status);

        $this->assertTrue(Storage::disk('local')->exists("sites/{$variantDeploy['deploy_hash']}.html"));
        $variantHtml = Storage::disk('local')->get("sites/{$variantDeploy['deploy_hash']}.html");
        $controlHtml = Storage::disk('local')->get("sites/{$this->deploy['deploy_hash']}.html");

        $this->assertStringContainsString('Distinctive variant headline 4561', $variantHtml);
        $this->assertStringNotContainsString('Distinctive variant headline 4561', $controlHtml);

        $latestAction = app(LatestDeploymentForPageAction::class);
        $latest = $latestAction->handle($this->biz->id, $page->id);
        $this->assertEquals($this->deploy['deploy_hash'], $latest->deploy_hash);

        // Third deploy supersedes only the first variant row
        $variantDeploy2 = app(EdgeDeployAction::class)->handle(
            businessId: $this->biz->id,
            edgeZoneId: $controlDeployment->edge_zone_id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commitId,
            businessName: $this->biz->name,
            pageVariantId: 4562
        );

        $this->assertEquals('superseded', $variantDeployment->fresh()->status);
        $this->assertEquals('deployed', $controlDeployment->fresh()->status);

        $controlDeploy2 = app(EdgeDeployAction::class)->handle(
            businessId: $this->biz->id,
            edgeZoneId: $controlDeployment->edge_zone_id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commitId,
            businessName: $this->biz->name
        );
        $this->assertEquals('superseded', $controlDeployment->fresh()->status);
    }

    public function test_serving_a_page_counts_it_and_a_404_does_not(): void
    {
        Tenancy::forgetAll();

        $this->get("/sites/{$this->biz->id}/{$this->deploy['deploy_hash']}")->assertStatus(200);
        $this->get("/sites/{$this->biz->id}/{$this->deploy['deploy_hash']}")->assertStatus(200);

        Tenancy::set((int) $this->biz->id);
        $this->assertEquals(2, Deployment::where('deploy_hash', $this->deploy['deploy_hash'])->first()->served_count);

        Tenancy::forgetAll();
        $this->get("/sites/{$this->biz->id}/bogus_hash")->assertStatus(404);

        Tenancy::set((int) $this->biz->id);
        $this->assertEquals(2, Deployment::where('deploy_hash', $this->deploy['deploy_hash'])->first()->served_count);
    }
}
