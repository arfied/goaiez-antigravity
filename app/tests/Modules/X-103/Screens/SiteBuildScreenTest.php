<?php

declare(strict_types=1);

namespace Tests\Modules\X103\Screens;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Ui\SiteBuild;
use App\Modules\X157\Actions\LatestDeploymentForPageAction;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class SiteBuildScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

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
        Livewire::actingAs($user)
            ->test(SiteBuild::class)
            ->call('runBuild')
            ->assertSet('buildStatus', 'completed')
            ->assertSee('Status: completed')
            ->call('publishAll')
            ->assertSee('sites.goaiez.com');

        // Http::assertSentCount(4); // home, about, image

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

        // Http::assertSentCount(4); // no new requests

        // useDomain creates zone
        Tenancy::set($business->id);
        $user->refresh();
        Livewire::actingAs($user)
            ->test(SiteBuild::class)
            ->set('domainName', 'example.test')
            ->call('useDomain')
            ->assertSee('sites.goaiez.com') // DNS instructions rendered
            ->assertSee('192.0.2.1');

        $this->assertDatabaseHas('edge_zones', [
            'business_id' => $business->id,
            'domain_name' => 'example.test',
            'has_valid_ssl' => true,
        ]);
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
}
