<?php

declare(strict_types=1);

namespace Tests\Modules\X103\Screens;

use App\Enums\UserRole;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\X103\Actions\PageCreateAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Events\PagePublished;
use App\Modules\X103\Events\SitePublished;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X103\Ui\Pages;
use App\Modules\X157\Models\Deployment;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PagesScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
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

    public function test_history_lists_versions_newest_first_and_marks_current(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'history-test', 'History Test');

        Livewire::test(Pages::class)
            ->set('faqQuestion', 'Q1')
            ->set('faqAnswer', 'Answer one k1')
            ->call('addFaq', $page->id)
            ->call('publish', $page->id);

        $v1 = PageVersion::where('page_id', $page->id)->latest('id')->first();

        Livewire::test(Pages::class)
            ->call('unpublish', $page->id)
            ->call('removeBlock', $page->id, 0)
            ->set('faqQuestion', 'Q2')
            ->set('faqAnswer', 'Answer two k2')
            ->call('addFaq', $page->id)
            ->call('publish', $page->id);

        $v2 = PageVersion::where('page_id', $page->id)->latest('id')->first();

        Livewire::test(Pages::class)
            ->call('toggleHistory', $page->id)
            ->assertSee($v2->commit_id)
            ->assertSee($v1->commit_id)
            ->assertSeeInOrder([$v2->commit_id, $v1->commit_id]) // newest first
            ->assertSee('(Current)'); // marks current (v2)
    }

    public function test_restoring_a_previous_version_publishes_its_blocks_again(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'restore-test', 'Restore Test');

        Livewire::test(Pages::class)
            ->set('faqQuestion', 'Q1')
            ->set('faqAnswer', 'Answer one k1')
            ->call('addFaq', $page->id)
            ->call('publish', $page->id);

        $v1 = PageVersion::where('page_id', $page->id)->latest('id')->first();

        Livewire::test(Pages::class)
            ->call('unpublish', $page->id)
            ->call('removeBlock', $page->id, 0)
            ->set('faqQuestion', 'Q2')
            ->set('faqAnswer', 'Answer two k2')
            ->call('addFaq', $page->id)
            ->call('publish', $page->id);

        Livewire::test(Pages::class)
            ->call('restore', $page->id, $v1->id)
            ->assertSee('Restored version');

        $v3 = PageVersion::where('page_id', $page->id)->latest('id')->first();
        $this->assertNotEquals($v1->id, $v3->id);

        $hasK1 = collect($v3->content_blocks)->pluck('answer')->contains('Answer one k1');
        $this->assertTrue($hasK1);

        $page->refresh();
        $hasK1Draft = collect($page->draft_blocks)->pluck('answer')->contains('Answer one k1');
        $this->assertTrue($hasK1Draft);

        $deployment = Deployment::where('business_id', $biz->id)->where('page_id', $page->id)->latest('id')->firstOrFail();
        $this->assertEquals('deployed', $deployment->status);

        $this->get("/sites/{$biz->id}/{$deployment->deploy_hash}")
            ->assertOk()
            ->assertSee('Answer one k1')
            ->assertDontSee('Answer two k2');
    }

    public function test_restore_refuses_another_tenants_version(): void
    {
        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        Storage::fake('local');

        $action = app(PageCreateAction::class);

        $this->actingAs($ownerA);
        Tenancy::set($bizA->id);
        $pageA = $action->handle($bizA->id, 'page-a', 'Page A');

        Livewire::test(Pages::class)
            ->set('faqQuestion', 'QA')
            ->set('faqAnswer', 'AA')
            ->call('addFaq', $pageA->id)
            ->call('publish', $pageA->id);

        $vA = PageVersion::where('page_id', $pageA->id)->latest('id')->first();

        $this->actingAs($ownerB);
        Tenancy::set($bizB->id);
        $pageB = $action->handle($bizB->id, 'page-b', 'Page B');

        Livewire::test(Pages::class)
            ->call('restore', $pageB->id, $vA->id)
            ->assertNotFound();
    }

    public function test_restore_of_the_current_version_is_refused(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'restore-current', 'Restore Current');

        Livewire::test(Pages::class)
            ->set('faqQuestion', 'Q1')
            ->set('faqAnswer', 'A1')
            ->call('addFaq', $page->id)
            ->call('publish', $page->id);

        $v1 = PageVersion::where('page_id', $page->id)->latest('id')->first();

        Livewire::test(Pages::class)
            ->call('restore', $page->id, $v1->id)
            ->assertSee('Cannot restore the current version.');

        $this->assertDatabaseCount('page_versions', 1);
    }

    public function test_staff_cannot_restore(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $staff = User::factory()->create(['role' => UserRole::Manager]);
        $this->provisionTenant(['owner_user_id' => $staff->id]);
        // Put staff in same tenant
        $staff->update(['tenant_id' => $biz->id]); // wait, provisionTenant creates a new one
        // actually just using manager role
        $this->actingAs($staff);

        Livewire::test(Pages::class)
            ->call('restore', 1, 1)
            ->assertForbidden();
    }

    public function test_owner_deletes_a_never_published_page(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'scratch-z4q', 'Scratch');

        Livewire::test(Pages::class)
            ->call('deletePage', $page->id)
            ->assertOk()
            ->assertSee('Page deleted.');

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);

        $this->get(route('x-103.pages'))
            ->assertOk()
            ->assertDontSee('scratch-z4q');
    }

    public function test_deleting_a_published_page_is_refused(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'pub-page', 'Pub Page');

        Livewire::test(Pages::class)->call('publish', $page->id);

        Livewire::test(Pages::class)
            ->call('deletePage', $page->id)
            ->assertOk()
            ->assertSee('Unpublish this page first — it has been published.');

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
        $this->assertDatabaseHas('page_versions', ['page_id' => $page->id]);
        $this->assertDatabaseHas('edge_zones', ['business_id' => $biz->id]);
    }

    public function test_deleting_an_unpublished_page_with_history_is_refused(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'hist-page', 'Hist Page');

        Livewire::test(Pages::class)->call('publish', $page->id);
        Livewire::test(Pages::class)->call('unpublish', $page->id);

        Livewire::test(Pages::class)
            ->call('deletePage', $page->id)
            ->assertOk()
            ->assertSee('Unpublish this page first — it has been published.');

        $this->assertDatabaseHas('pages', ['id' => $page->id]);
    }

    public function test_owner_duplicates_a_page(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Storage::fake('local');

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'src-page', 'Source Page');

        Livewire::test(Pages::class)
            ->set('faqQuestion', 'Q1')
            ->set('faqAnswer', 'A1')
            ->call('addFaq', $page->id);

        $page->refresh();

        Livewire::test(Pages::class)
            ->call('duplicatePage', $page->id)
            ->assertOk()
            ->assertSee('Page duplicated.');

        $this->assertDatabaseHas('pages', [
            'business_id' => $biz->id,
            'slug' => 'src-page-copy',
            'title' => 'Copy of Source Page',
            'is_published' => false,
        ]);

        $copy1 = Page::where('slug', 'src-page-copy')->first();
        $this->assertEquals($page->draft_blocks, $copy1->draft_blocks);

        Livewire::test(Pages::class)
            ->call('duplicatePage', $page->id)
            ->assertOk();

        $this->assertDatabaseHas('pages', [
            'business_id' => $biz->id,
            'slug' => 'src-page-copy-2',
        ]);
    }

    public function test_duplicate_of_another_tenants_page_is_refused(): void
    {
        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        $action = app(PageCreateAction::class);
        $this->actingAs($ownerA);
        Tenancy::set($bizA->id);
        $pageA = $action->handle($bizA->id, 'page-a', 'Page A');

        $this->actingAs($ownerB);
        Tenancy::set($bizB->id);
        Livewire::test(Pages::class)
            ->call('duplicatePage', $pageA->id)
            ->assertNotFound();
    }

    public function test_staff_cannot_delete_or_duplicate(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Manager]);
        $biz = $this->provisionTenant(['owner_user_id' => $employee->id]);
        $this->actingAs($employee);

        Livewire::test(Pages::class)
            ->call('deletePage', 1)
            ->assertForbidden();

        Livewire::test(Pages::class)
            ->call('duplicatePage', 1)
            ->assertForbidden();
    }

    public function test_polish_copy_and_restore_original_controls(): void
    {
        PlatformSetting::write('ai.monthly_cap_per_tenant', 500000, 'test');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Pages Test', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'text' => 'Hero text'],
            ],
            'is_published' => false,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'msg_eval',
                'choices' => [
                    ['message' => ['content' => 'Polished UI text']],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('polish', $page->id)->assertSet('success', 'Polished 1 blocks with openai-4o-mini');

        $page->refresh();
        $this->assertEquals('Polished UI text', $page->draft_blocks[0]['text']);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('restoreOriginal', $page->id);

        $page->refresh();
        $this->assertEquals('Hero text', $page->draft_blocks[0]['text']);
    }
}
