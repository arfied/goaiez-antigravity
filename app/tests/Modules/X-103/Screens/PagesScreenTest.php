<?php

declare(strict_types=1);

namespace Tests\Modules\X103\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X103\Actions\PageCreateAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Events\PagePublished;
use App\Modules\X103\Events\SitePublished;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X103\Ui\Pages;
use App\Modules\X157\Models\Deployment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PagesScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-103.pages'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No pages yet. Add one below.');

        Livewire::test(Pages::class)->assertOk();
    }

    public function test_owner_adds_a_page(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(Pages::class)
            ->set('newSlug', 'pricing-x7q')
            ->set('newTitle', 'Pricing')
            ->call('addPage')
            ->assertOk()
            ->assertSee('pricing-x7q');

        $this->assertDatabaseHas('pages', [
            'business_id' => $biz->id,
            'slug' => 'pricing-x7q',
        ]);
    }

    public function test_staff_cannot_open_pages(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Staff]);
        $this->provisionTenant(['owner_user_id' => $employee->id]);
        $this->actingAs($employee);

        $this->get(route('x-103.pages'))
            ->assertForbidden();

        Livewire::test(Pages::class)
            ->assertForbidden();
    }

    public function test_owner_publishes_a_draft_page(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'launch-q9z', 'Launch');

        Event::fake([PagePublished::class, SitePublished::class]);

        Livewire::test(Pages::class)
            ->call('publish', $page->id)
            ->assertOk();

        $this->assertTrue($page->fresh()->is_published);

        $this->assertDatabaseHas('page_versions', [
            'page_id' => $page->id,
            'pixel_installed' => true,
            'chat_installed' => true,
            'form_capture_installed' => true,
            'dni_installed' => true,
            'seo_tags_installed' => true,
            'schema_installed' => true,
        ]);

        Event::assertDispatched(PagePublished::class);

        $this->get(route('x-103.pages'))
            ->assertOk()
            ->assertSee('Published')
            ->assertDontSee('Draft');
    }

    public function test_publish_refuses_an_already_published_page(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'launch-q9z', 'Launch');

        app(SitePublishAction::class)->handle($biz->id, $page->id, []);

        Livewire::test(Pages::class)
            ->call('publish', $page->id)
            ->assertOk()
            ->assertSee('That page is already published.');

        $this->assertDatabaseCount('page_versions', 1);
    }

    public function test_staff_cannot_publish(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Staff]);
        $this->provisionTenant(['owner_user_id' => $employee->id]);
        $this->actingAs($employee);

        Livewire::test(Pages::class)
            ->assertForbidden();
    }

    public function test_publishing_deploys_to_the_platform_address(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Storage::fake('local');

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'launch-plat-1', 'Launch Platform');

        Livewire::test(Pages::class)
            ->call('publish', $page->id)
            ->assertOk();

        $this->assertDatabaseHas('edge_zones', [
            'business_id' => $biz->id,
            'provider' => 'platform',
        ]);

        $deployment = Deployment::where('business_id', $biz->id)
            ->where('page_id', $page->id)
            ->firstOrFail();

        $this->assertEquals('deployed', $deployment->status);

        Storage::disk('local')->assertExists("sites/{$deployment->deploy_hash}.html");

        $this->get("/sites/{$biz->id}/{$deployment->deploy_hash}")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('Launch Platform');

        $this->get(route('x-103.pages'))
            ->assertOk()
            ->assertSee($deployment->deploy_hash);
    }

    public function test_publishing_twice_reuses_the_platform_zone(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Storage::fake('local');

        $action = app(PageCreateAction::class);
        $page1 = $action->handle($biz->id, 'launch-plat-1', 'Launch Platform 1');
        $page2 = $action->handle($biz->id, 'launch-plat-2', 'Launch Platform 2');

        Livewire::test(Pages::class)->call('publish', $page1->id)->assertOk();
        Livewire::test(Pages::class)->call('publish', $page2->id)->assertOk();

        $this->assertDatabaseCount('edge_zones', 1);
        $this->assertDatabaseHas('edge_zones', [
            'business_id' => $biz->id,
            'provider' => 'platform',
        ]);
    }

    public function test_another_tenants_deploy_hash_is_not_served_under_this_tenant(): void
    {
        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        Storage::fake('local');

        $action = app(PageCreateAction::class);
        $pageB = $action->handle($bizB->id, 'launch-plat-b', 'Launch Platform B');

        $this->actingAs($ownerB);
        Livewire::test(Pages::class)->call('publish', $pageB->id)->assertOk();

        $deploymentB = Deployment::where('business_id', $bizB->id)->firstOrFail();

        $this->get("/sites/{$bizA->id}/{$deploymentB->deploy_hash}")
            ->assertNotFound();
    }

    public function test_owner_authors_a_faq_and_it_publishes_into_the_page(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'faq-test', 'FAQ');

        Livewire::test(Pages::class)
            ->set('faqQuestion', 'Do you serve Austin?')
            ->set('faqAnswer', 'Yes, all of Travis County.')
            ->call('addFaq', $page->id)
            ->assertOk();

        $page->refresh();
        $this->assertCount(1, $page->draft_blocks);
        $this->assertEquals('faq', $page->draft_blocks[0]['type']);

        Livewire::test(Pages::class)
            ->call('publish', $page->id)
            ->assertOk();

        $this->assertDatabaseHas('page_versions', [
            'page_id' => $page->id,
        ]);

        $version = PageVersion::where('page_id', $page->id)->latest('id')->first();
        $this->assertEquals('Yes, all of Travis County.', $version->content_blocks[0]['answer']);

        $deployment = Deployment::where('business_id', $biz->id)->where('page_id', $page->id)->firstOrFail();

        $this->get("/sites/{$biz->id}/{$deployment->deploy_hash}")
            ->assertOk()
            ->assertSee('Travis County');
    }

    public function test_owner_authors_a_video_and_it_publishes(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'vid-test', 'Video');

        Livewire::test(Pages::class)
            ->set('videoName', 'Roof walkthrough')
            ->set('videoUrl', 'https://example.com/video.mp4')
            ->set('videoDate', '2026-09-22')
            ->call('addVideo', $page->id)
            ->assertOk();

        $page->refresh();
        $this->assertCount(1, $page->draft_blocks);

        Livewire::test(Pages::class)
            ->call('publish', $page->id)
            ->assertOk();

        $version = PageVersion::where('page_id', $page->id)->latest('id')->first();
        $this->assertEquals('Roof walkthrough', $version->content_blocks[0]['name']);

        $deployment = Deployment::where('business_id', $biz->id)->where('page_id', $page->id)->firstOrFail();

        $this->get("/sites/{$biz->id}/{$deployment->deploy_hash}")
            ->assertOk()
            ->assertSee('Roof walkthrough');
    }

    public function test_a_non_https_video_url_is_refused(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'vid-test-2', 'Video 2');

        Livewire::test(Pages::class)
            ->set('videoName', 'Roof walkthrough')
            ->set('videoUrl', 'http://example.com/video.mp4')
            ->set('videoDate', '2026-09-22')
            ->call('addVideo', $page->id)
            ->assertSee('Video URL must start with https://');

        $page->refresh();
        $this->assertNull($page->draft_blocks);
    }

    public function test_removing_a_block_drops_it_from_the_next_publish(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'drop-test', 'Drop');

        Livewire::test(Pages::class)
            ->set('faqQuestion', 'Q1')
            ->set('faqAnswer', 'A1')
            ->call('addFaq', $page->id);

        $page->refresh();
        $this->assertCount(1, $page->draft_blocks);

        Livewire::test(Pages::class)
            ->call('removeBlock', $page->id, 0)
            ->assertOk();

        $page->refresh();
        $this->assertCount(0, $page->draft_blocks);

        Livewire::test(Pages::class)
            ->call('publish', $page->id)
            ->assertOk();

        $version = PageVersion::where('page_id', $page->id)->latest('id')->first();
        $faqs = array_filter($version->content_blocks, fn ($b) => ($b['type'] ?? '') === 'faq');
        $this->assertEmpty($faqs);
    }

    public function test_staff_cannot_author(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Manager]);
        $biz = $this->provisionTenant(['owner_user_id' => $employee->id]);
        $this->actingAs($employee);

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'staff-test', 'Staff');

        Livewire::test(Pages::class)
            ->call('addFaq', $page->id)
            ->assertForbidden();

        Livewire::test(Pages::class)
            ->call('addVideo', $page->id)
            ->assertForbidden();

        Livewire::test(Pages::class)
            ->call('removeBlock', $page->id, 0)
            ->assertForbidden();
    }

    public function test_owner_unpublishes_a_live_page(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'live-page', 'Live Page');

        Livewire::test(Pages::class)->call('publish', $page->id);

        $deployment = Deployment::where('business_id', $biz->id)->where('page_id', $page->id)->firstOrFail();
        $this->get("/sites/{$biz->id}/{$deployment->deploy_hash}")->assertOk();

        Livewire::test(Pages::class)
            ->call('unpublish', $page->id)
            ->assertOk();

        $this->get("/sites/{$biz->id}/{$deployment->deploy_hash}")->assertNotFound();

        $this->assertEquals('unpublished', $deployment->fresh()->status);

        $this->get(route('x-103.pages'))
            ->assertSee('Draft')
            ->assertDontSee('Live link');
    }

    public function test_republishing_serves_again(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'repub-page', 'Repub Page');

        Livewire::test(Pages::class)->call('publish', $page->id);
        Livewire::test(Pages::class)->call('unpublish', $page->id);
        Livewire::test(Pages::class)->call('publish', $page->id);

        $deployment = Deployment::where('business_id', $biz->id)->where('page_id', $page->id)->latest('id')->firstOrFail();
        $this->assertEquals('deployed', $deployment->status);

        $this->get("/sites/{$biz->id}/{$deployment->deploy_hash}")->assertOk();
    }

    public function test_unpublishing_a_draft_is_refused(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'draft-page', 'Draft Page');

        Livewire::test(Pages::class)
            ->call('unpublish', $page->id)
            ->assertSee('That page is not published.');

        $this->assertFalse($page->fresh()->is_published);
    }

    public function test_owner_renames_a_page(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'old-name', 'Old Title');

        Livewire::test(Pages::class)
            ->set('renameSlug.'.$page->id, 'about-k3p')
            ->set('renameTitle.'.$page->id, 'About')
            ->call('rename', $page->id)
            ->assertSee('Renamed.');

        Livewire::test(Pages::class)->call('publish', $page->id);

        $deployment = Deployment::where('business_id', $biz->id)->where('page_id', $page->id)->firstOrFail();
        $this->get("/sites/{$biz->id}/{$deployment->deploy_hash}")
            ->assertOk()
            ->assertSee('About');

        $this->assertEquals('about-k3p', $page->fresh()->slug);
    }

    public function test_rename_refuses_a_duplicate_slug(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $action = app(PageCreateAction::class);
        $page1 = $action->handle($biz->id, 'first-page', 'First');
        $page2 = $action->handle($biz->id, 'second-page', 'Second');

        Livewire::test(Pages::class)
            ->set('renameSlug.'.$page2->id, 'first-page')
            ->set('renameTitle.'.$page2->id, 'New Title')
            ->call('rename', $page2->id)
            ->assertSee('Another page already uses that address.');

        $this->assertEquals('second-page', $page2->fresh()->slug);
    }

    public function test_staff_cannot_unpublish_or_rename(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Manager]);
        $biz = $this->provisionTenant(['owner_user_id' => $staff->id]);
        $this->actingAs($staff);

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'staff-test-2', 'Staff 2');

        Livewire::test(Pages::class)
            ->call('unpublish', $page->id)
            ->assertForbidden();

        Livewire::test(Pages::class)
            ->call('rename', $page->id)
            ->assertForbidden();
    }
}
