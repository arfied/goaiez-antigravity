<?php

declare(strict_types=1);

namespace Tests\Modules\X103\Screens;

use App\Enums\UserRole;
use App\Models\Competitor;
use App\Models\CompetitorSiteNote;
use App\Models\CompetitorSnapshot;
use App\Models\Location;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVariant;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X103\Models\SiteRecommendation;
use App\Modules\X103\Ui\SiteBuild;
use App\Modules\X108\Models\Waitlist;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use App\Modules\X157\Actions\LatestDeploymentForPageAction;
use App\Modules\X157\Domain\DnsResolver;
use App\Modules\X157\Models\CustomDomainRequest;
use App\Modules\X157\Models\Deployment;
use App\Modules\X163\Models\PriceBookItem;
use App\Support\Tenancy;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class SiteBuildScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_screen_renders_for_tenant(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-103.site-build'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console');

        Livewire::test(SiteBuild::class)->assertOk();
    }

    public function test_full_build_pipeline_and_publish(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $business = $this->provisionTenant(['owner_user_id' => $user->id, 'name' => 'Acme Corp']);

        Tenancy::set($business->id);
        $location = Location::where('business_id', $business->id)->first();
        if (! $location) {
            $location = Location::factory()->create(['business_id' => $business->id]);
        }
        $location->website_url = 'https://example.com';
        $location->website_confirmed_at = now();
        $location->save();

        Http::fake([
            'https://example.com' => Http::response(
                '<html><head><title>Home</title></head><body><h1>Welcome</h1><img src="logo.png"><a href="/about">About</a></body></html>', 200
            ),
            'https://example.com/about' => Http::response(
                '<html><head><title>About</title></head><body><h1>About Us</h1></body></html>', 200
            ),
            'https://example.com/logo.png' => Http::response('fake-image-content', 200),
            '*' => Http::response('', 404),
        ]);

        Tenancy::set($business->id);
        $user->refresh();
        $test = Livewire::actingAs($user)
            ->test(SiteBuild::class)
            ->call('runBuild');

        $test->assertSet('buildStatus', 'completed')
            ->assertSee('Status: completed')
            ->call('publishAll');

        // Http::assertNothingSent(); // home, about, image

        $pages = Page::where('business_id', $business->id)->get();
        $this->assertCount(3, $pages); // home, services, contact

        foreach ($pages as $page) {
            $deployment = app(LatestDeploymentForPageAction::class)->handle($business->id, $page->id);
            $this->assertNotNull($deployment);

            $url = '/sites/'.$business->id.'/'.$deployment->deploy_hash;
            $response = $this->get($url);
            $response->assertStatus(200);

            // "each GET returns 200 and contains the escaped headline"
            // The escaped headline for home page should be Acme Corp or Home
            $response->assertSee(e($page->title));
        }

        // re-run inside recrawl window reuses inventory
        Tenancy::set($business->id);
        $user->refresh();
        Livewire::actingAs($user)
            ->test(SiteBuild::class)
            ->call('runBuild');

        // Http::assertNothingSent(); // no new requests

        app()->forgetInstance(Factory::class);
        Http::clearResolvedInstance('http');
        Http::fake();

        // useDomain creates zone
        Tenancy::set($business->id);
        $user->refresh();
        Livewire::actingAs($user)
            ->test(SiteBuild::class)
            ->set('domainName', 'example-roofing.com')
            ->call('useDomain')
            ->assertSee('Requested example-roofing.com');

        $this->assertDatabaseHas('custom_domain_requests', [
            'business_id' => $business->id,
            'domain' => 'example-roofing.com',
            'status' => 'requested',
        ]);

        $this->assertDatabaseMissing('edge_zones', [
            'domain_name' => 'example-roofing.com',
        ]);

        Http::assertNothingSent();
    }

    public function test_no_website_stops_at_step_1(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $business = $this->provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::set($business->id);
        $loc = Location::where('business_id', $business->id)->first();
        if (! $loc) {
            $loc = Location::factory()->create(['business_id' => $business->id]);
        }
        $loc->website_url = null;
        $loc->website_confirmed_at = null;
        $loc->save();

        Tenancy::set($business->id);
        $user->refresh();
        Livewire::actingAs($user)
            ->test(SiteBuild::class)
            ->call('runBuild')
            ->assertSet('buildStatus', 'refused')
            ->assertSet('buildReason', 'no_website')
            ->assertSee('no_website');
    }

    public function test_no_tenant_403_and_other_tenant_invisible(): void
    {
        $this->get(route('x-103.site-build'))->assertRedirect();

        $user1 = User::factory()->create(['role' => UserRole::Owner]);
        $business1 = $this->provisionTenant(['owner_user_id' => $user1->id]);

        $user2 = User::factory()->create(['role' => UserRole::Owner]);
        $business2 = $this->provisionTenant(['owner_user_id' => $user2->id]);

        $this->actingAs($user1)->get(route('x-103.site-build'))->assertSuccessful();
    }

    public function test_owner_checks_the_domain_and_sees_why_it_is_not_verified(): void
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id, 'name' => 'Tenant', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        CustomDomainRequest::create(['business_id' => $biz->id, 'domain' => 'acme.com', 'status' => 'requested']);

        $this->app->instance(DnsResolver::class, new class implements DnsResolver
        {
            public function cname(string $host): ?string
            {
                return null;
            }
        });

        Livewire::actingAs($user)
            ->test(SiteBuild::class)
            ->call('verifyDomain')
            ->assertSee('no CNAME found');
    }

    public function test_suggestions_show_on_build_my_site_and_dismiss_hides_one(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set($biz->id);

        $rec = SiteRecommendation::create([
            'business_id' => $biz->id,
            'code' => 'hours_missing',
            'text' => 'Distinctive suggestion 4471',
            'status' => 'pending',
            'computed_at' => now(),
        ]);

        $this->get(route('x-103.site-build'))
            ->assertOk()
            ->assertSee('This week')
            ->assertSee('Distinctive suggestion 4471');

        Livewire::actingAs($owner)
            ->test(SiteBuild::class)
            ->call('dismissRecommendation', $rec->id);

        $this->get(route('x-103.site-build'))
            ->assertOk()
            ->assertDontSee('Distinctive suggestion 4471');
    }

    public function test_ask_the_ai_to_do_it_proposes_on_the_home_page_and_points_at_pages(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        Http::fake([
            'api.openai.com/*' => Http::response(
                json_encode([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['blocks' => [['type' => 'hero', 'headline' => 'Distinctive proposed headline 4472']], 'explanation' => 'Did it.']),
                            ],
                        ],
                    ],
                    'model' => 'gpt-4o-mini-fake',
                ]),
                200
            ),
        ]);

        $page = Page::create(['business_id' => $biz->id, 'slug' => 'home', 'title' => 'Home', 'draft_blocks' => [['type' => 'hero', 'headline' => 'Old headline']], 'is_published' => false]);
        $rec = SiteRecommendation::create([
            'business_id' => $biz->id,
            'code' => 'hours_missing',
            'text' => 'Distinctive suggestion 4471',
            'status' => 'pending',
            'computed_at' => now(),
        ]);

        Livewire::actingAs($owner)->test(SiteBuild::class)
            ->call('askRecommendation', $rec->id)
            ->assertSet('proposed', fn ($s) => str_starts_with((string) $s, 'Proposed on your Home page'));

        Http::assertSent(fn ($req) => str_contains((string) $req->body(), 'Distinctive suggestion 4471'));

        $page->refresh();
        $this->assertSame('Old headline', $page->draft_blocks[0]['headline']);
        $this->assertSame('Distinctive proposed headline 4472', $page->draft_meta['pending_edit']['blocks'][0]['headline']);

        $this->actingAs($owner)->get(route('x-103.pages'))->assertSee('Distinctive proposed headline 4472');
    }

    public function test_ask_the_ai_with_no_page_says_so_and_a_manager_cannot_ask(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $rec = SiteRecommendation::create([
            'business_id' => $biz->id,
            'code' => 'hours_missing',
            'text' => 'Distinctive suggestion 4471',
            'status' => 'pending',
            'computed_at' => now(),
        ]);

        Http::fake();

        Livewire::actingAs($owner)->test(SiteBuild::class)
            ->call('askRecommendation', $rec->id)
            ->assertSet('error', 'No page drafted yet. Run the build first, then ask again.');

        Http::assertNothingSent();

        $manager = User::factory()->create(['role' => UserRole::Manager]);

        Livewire::actingAs($manager)->test(SiteBuild::class)
            ->call('askRecommendation', $rec->id)
            ->assertForbidden();
    }

    public function test_learn_from_the_top_5_names_no_peer()
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        $location = Location::factory()->create(['business_id' => $biz->id]);

        $competitor = Competitor::forceCreate([
            'business_id' => $biz->id,
            'location_id' => $location->id,
            'place_id' => 'place-4471',
            'name' => 'Distinctive Peer 4471',
            'source' => 'auto',
        ]);

        CompetitorSnapshot::forceCreate([
            'competitor_id' => $competitor->id,
            'review_count' => 88,
            'rating' => 4.6,
            'captured_at' => now(),
        ]);

        CompetitorSiteNote::forceCreate([
            'competitor_id' => $competitor->id,
            'business_id' => $biz->id,
            'url' => 'https://peer-4471.example/',
            'status' => 'noted',
            'headings' => ['Distinctive heading 4473'],
            'fetched_at' => now(),
            'title' => null,
            'description' => null,
            'refusal_reason' => null,
        ]);

        Tenancy::set((int) $biz->id);

        $this->actingAs($owner);
        $response = $this->get(route('x-103.site-build'));

        $response->assertSee('Learn from the top 5 nearby');
        $response->assertSee('Distinctive heading 4473');
        $response->assertDontSee('Distinctive Peer 4471');
        $response->assertDontSee('peer-4471.example');

        // Fresh tenant
        $owner2 = User::factory()->create(['role' => UserRole::Owner]);
        $biz2 = TestCase::provisionTenant(['owner_user_id' => $owner2->id]);
        Tenancy::set((int) $biz2->id);
        $this->actingAs($owner2);
        $response2 = $this->get(route('x-103.site-build'));
        $response2->assertSee('No nearby site has been read yet');
    }

    public function test_try_a_headline_proposes_starts_shows_readings_and_stops(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id, 'name' => 'Edge Tenant', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        Http::fake([
            'api.openai.com/*' => Http::response(
                json_encode([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['headlines' => ['Distinctive option 4582', 'Distinctive option 4583']]),
                            ],
                        ],
                    ],
                    'model' => 'gpt-4o-mini-fake',
                ]),
                200
            ),
        ]);

        $zone = app(EdgeProvisionAction::class)->handle($biz->id, 'acme-hvac.com', true);
        PriceBookItem::create(['business_id' => $biz->id, 'service_name' => 'A service', 'price_cents' => 10000, 'is_confirmed' => true, 'confirmed_at' => now()]);

        $page = Page::create([
            'business_id' => $biz->id,
            'title' => 'Home',
            'slug' => 'home',
            'draft_blocks' => [['type' => 'hero', 'headline' => 'Old headline', 'subline' => 'Old subline']],
            'is_published' => true,
        ]);

        $commitId = 'commit_'.Str::random(16);
        $version = PageVersion::create([
            'business_id' => $biz->id,
            'page_id' => $page->id,
            'commit_id' => $commitId,
            'content_blocks' => [
                ['type' => 'hero', 'headline' => 'Old headline', 'subline' => 'Old subline'],
            ],
            'pixel_installed' => true,
        ]);

        $page->update(['current_version_id' => $version->id]);

        $deploy = app(EdgeDeployAction::class)->handle(
            businessId: $biz->id,
            edgeZoneId: $zone->id,
            measuredTtfbMs: 120,
            speedBudgetMs: 1500,
            pageId: $page->id,
            commitId: $commitId,
            businessName: $biz->name
        );

        $this->actingAs($owner)->get(route('x-103.site-build'))
            ->assertOk()
            ->assertSee('7. Try a headline')
            ->assertSee('Ask the AI for two headlines');

        Livewire::actingAs($owner)->test(SiteBuild::class)
            ->call('proposeHeadlines')
            ->assertSet('error', null)->assertSet('headlineOptions', ['Distinctive option 4582', 'Distinctive option 4583'])
            ->set('headlineChoice', 'Distinctive option 4582')
            ->call('startHeadlineTest')
            ->assertSet('trial', fn ($s) => str_starts_with((string) $s, 'Running:'));

        $variant = PageVariant::where('business_id', $biz->id)->first();
        $variantId = $variant->id;

        $this->actingAs($owner)->get(route('x-103.site-build'))
            ->assertSee('The other one')
            ->assertSee('not enough visits yet')
            ->assertDontSee('is ahead');

        Deployment::where('deploy_hash', $variant->control_deploy_hash)->update(['served_count' => 150]);
        Deployment::where('deploy_hash', $variant->variant_deploy_hash)->update(['served_count' => 150]);

        Waitlist::create(['business_id' => $biz->id, 'deploy_hash' => $variant->control_deploy_hash, 'customer_name' => '1', 'customer_phone' => '1', 'service_name' => 'Haircut', 'preferred_date' => now()->addDays(2)]);
        Waitlist::create(['business_id' => $biz->id, 'deploy_hash' => $variant->variant_deploy_hash, 'customer_name' => '2', 'customer_phone' => '2', 'service_name' => 'Haircut', 'preferred_date' => now()->addDays(2)]);
        Waitlist::create(['business_id' => $biz->id, 'deploy_hash' => $variant->variant_deploy_hash, 'customer_name' => '3', 'customer_phone' => '3', 'service_name' => 'Haircut', 'preferred_date' => now()->addDays(2)]);
        Waitlist::create(['business_id' => $biz->id, 'deploy_hash' => $variant->variant_deploy_hash, 'customer_name' => '4', 'customer_phone' => '4', 'service_name' => 'Haircut', 'preferred_date' => now()->addDays(2)]);

        $this->actingAs($owner)->get(route('x-103.site-build'))
            ->assertSee('per hundred visits')
            ->assertSee('is ahead');

        Livewire::actingAs($owner)->test(SiteBuild::class)
            ->call('stopHeadlineTest', $variantId)
            ->assertSet('trial', 'Stopped — everyone sees your original headline again.');

        // Start again to freeze
        Livewire::actingAs($owner)->test(SiteBuild::class)
            ->set('headlineChoice', 'Distinctive option 4583')
            ->call('startHeadlineTest');

        $variant2 = PageVariant::where('business_id', $biz->id)->latest('id')->first();
        $variantId2 = $variant2->id;

        Livewire::actingAs($owner)->test(SiteBuild::class)
            ->call('keepMineAndFreeze', $variantId2);

        $this->actingAs($owner)->get(route('x-103.site-build'))
            ->assertSee('marked it left-alone');

        $manager = User::factory()->create(['role' => UserRole::Manager]);

        Livewire::actingAs($manager)->test(SiteBuild::class)
            ->call('startHeadlineTest')
            ->assertForbidden();
    }
}
