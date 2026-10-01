<?php

declare(strict_types=1);

namespace Tests\Modules\X103\Screens;

use App\Enums\UserRole;
use App\Models\Competitor;
use App\Models\CompetitorSiteNote;
use App\Models\Location;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X102\Models\ChatTurn;
use App\Modules\X103\Actions\PageCreateAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Events\PagePublished;
use App\Modules\X103\Events\SitePublished;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X103\Ui\Pages;
use App\Services\Facts\BusinessFacts;
use App\Services\Facts\BusinessFactKey;
use App\Modules\X157\Models\Deployment;
use App\Modules\X163\Models\PriceBookItem;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PagesScreenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-103.pages'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('Tell us what this site is for.');

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
            ->assertSee('Live')
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
            ->assertSee('That page is already published, and the draft has no changes.');

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

        $this->get(route('x-103.pages').'?edit='.$page->id)
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

    public function test_history_lists_versions_newest_first_and_marks_current(): void
    {
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
            ->call('openEditor', $page->id)
            ->call('toggleHistory', $page->id)
            ->assertSee($v2->commit_id)
            ->assertSee($v1->commit_id)
            ->assertSeeInOrder([$v2->commit_id, $v1->commit_id]) // newest first
            ->assertSee('Current version'); // marks current (v2)
    }

    public function test_restoring_a_previous_version_publishes_its_blocks_again(): void
    {
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
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Pages Test', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Hero headline', 'subline' => 'Hero text', 'source' => 'crawl'],
            ],
            'is_published' => false,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => 'Polished UI text']],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
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
        $this->assertEquals('Polished UI text', $page->draft_blocks[0]['subline']);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('restoreOriginal', $page->id);

        $page->refresh();
        $this->assertEquals('Hero text', $page->draft_blocks[0]['subline']);
    }

    public function test_restore_original_puts_the_hero_subline_back(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Pages Test Subline Restore', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Distinctive headline 4601', 'subline' => 'Distinctive subline 4602', 'source' => 'crawl'],
                ['type' => 'about', 'text' => 'About text'],
            ],
            'is_published' => false,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => 'Polished subline text']],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response([
                'id' => 'msg_eval',
                'choices' => [
                    ['message' => ['content' => 'Polished subline text']],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('polish', $page->id);

        $page->refresh();
        $this->assertEquals('Polished subline text', $page->draft_blocks[0]['subline']);
        $this->assertEquals('Distinctive subline 4602', $page->draft_blocks[0]['original_subline']);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('restoreOriginal', $page->id);

        $page->refresh();
        $this->assertEquals('Distinctive subline 4602', $page->draft_blocks[0]['subline']);
        $this->assertArrayNotHasKey('original_subline', $page->draft_blocks[0]);
    }

    public function test_the_faq_buttons_are_on_the_page_and_a_refusal_says_what_to_add_first(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Faq Controls Test', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'faq-controls',
            'title' => 'FAQ',
            'draft_blocks' => [],
            'is_published' => false,
        ]);

        $this->actingAs($owner)->get(route('x-103.pages').'?edit='.$page->id)
            ->assertOk()
            ->assertSee('Ask the AI for questions and answers');

        Livewire::actingAs($owner)->test(Pages::class)
            ->call('draftFaq', $page->id)
            ->assertSet('success', 'Nothing to write from yet — confirm a price or show a review on the website first.');

        Http::assertNothingSent();

        $page->update(['draft_meta' => ['pending_faq' => ['items' => [['question' => 'Distinctive question 4491?', 'answer' => 'Distinctive answer 4492.']], 'model' => 'openai-4o-mini']]]);

        $this->actingAs($owner)->get(route('x-103.pages').'?edit='.$page->id)
            ->assertSee('Proposed questions')
            ->assertSee('Distinctive question 4491?')
            ->assertSee('Place on this page');

        Livewire::actingAs($owner)->test(Pages::class)
            ->call('placeFaq', $page->id);

        $this->actingAs($owner)->get(route('x-103.pages').'?edit='.$page->id)
            ->assertDontSee('Proposed questions')
            ->assertSee('1 question — Distinctive question 4491?');

        $staff = User::factory()->create(['role' => UserRole::Manager]);
        TestCase::provisionTenant(['owner_user_id' => $staff->id]);
        $this->actingAs($staff);

        Livewire::test(Pages::class)
            ->call('draftFaq', $page->id)
            ->assertForbidden();
    }

    public function test_faq_controls(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Faq Controls Test', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'faq-controls',
            'title' => 'FAQ',
            'draft_blocks' => [],
            'is_published' => false,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => json_encode(['items' => [
                        ['question' => 'Q1', 'answer' => 'A1'],
                    ]])]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response([
                'id' => 'msg_faq',
                'choices' => [
                    ['message' => ['content' => json_encode(['items' => [
                        ['question' => 'Q1', 'answer' => 'A1'],
                    ]])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Service 1',
            'price_cents' => 1000,
            'is_confirmed' => true,
            'is_sample' => false,
            'confirmed_at' => now(),
        ]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('draftFaq', $page->id)
            ->assertSet('success', 'Drafted 1 questions with openai-4o-mini');

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('placeFaq', $page->id)
            ->assertSet('success', 'FAQ placed on page.');

        $page->update(['draft_meta' => ['pending_faq' => ['items' => []]]]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('discardFaq', $page->id)
            ->assertSet('success', 'Pending FAQ discarded.');
    }

    public function test_seo_draft_and_save_controls(): void
    {
        PlatformSetting::write('sites.seo.title_max_chars', 60, 'test');
        PlatformSetting::write('sites.seo.description_max_chars', 155, 'test');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'SEO Screen', 'owner_user_id' => $owner->id]);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [],
            'is_published' => false,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => json_encode(['title' => 'Drafted Title', 'description' => 'Drafted Desc'])]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response([
                'id' => 'msg_seo',
                'choices' => [
                    ['message' => ['content' => json_encode(['title' => 'Drafted Title', 'description' => 'Drafted Desc'])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
            ]),
        ]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('draftSeo', $page->id)
            ->assertSet('success', 'Drafted with openai-4o-mini');

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->set('seoTitle.'.$page->id, 'Saved Title')
            ->set('seoDescription.'.$page->id, 'Saved Desc')
            ->call('saveSeo', $page->id)
            ->assertSet('success', 'SEO saved.');

        $page->refresh();
        $this->assertEquals('Saved Title', $page->seo_title);
    }

    public function test_ask_edit_proposes_without_touching_the_draft_and_apply_puts_it_on_the_draft(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Pages Test Edit', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old headline'],
            ],
            'is_published' => false,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => json_encode(['patches' => [['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'Distinctive new headline 4471']], 'explanation' => 'Rewrote the headline and added an about block.'])]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode(['patches' => [['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'Distinctive new headline 4471']], 'explanation' => 'Rewrote the headline and added an about block.'])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->set('editRequest.'.$page->id, 'make the headline stronger')
            ->call('askEdit', $page->id)
            ->assertSet('success', fn ($s) => str_starts_with((string) $s, 'Proposed 1 edits'));

        $page->refresh();
        $this->assertSame('Old headline', $page->draft_blocks[0]['headline']);
        $this->assertSame('Distinctive new headline 4471', $page->draft_meta['pending_edit']['blocks'][0]['headline']);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('applyEdit', $page->id);

        $page->refresh();
        $this->assertSame('Distinctive new headline 4471', $page->draft_blocks[0]['headline']);
        $this->assertArrayNotHasKey('pending_edit', $page->draft_meta ?? []);
        $this->assertFalse((bool) $page->is_published);

        $this->actingAs($owner)->get(route('x-103.pages').'?edit='.$page->id)->assertSee('Distinctive new headline 4471');
    }

    public function test_discard_edit_drops_the_proposal(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Pages Test Edit Discard', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old headline'],
            ],
            'is_published' => false,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => json_encode(['patches' => [['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'Distinctive new headline 4471']], 'explanation' => 'Rewrote the headline and added an about block.'])]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode(['patches' => [['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'Distinctive new headline 4471']], 'explanation' => 'Rewrote the headline and added an about block.'])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->set('editRequest.'.$page->id, 'make the headline stronger')
            ->call('askEdit', $page->id);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('discardEdit', $page->id);

        $page->refresh();
        $this->assertSame('Old headline', $page->draft_blocks[0]['headline']);
        $this->assertArrayNotHasKey('pending_edit', $page->draft_meta ?? []);
    }

    public function test_an_invalid_block_from_the_ai_is_dropped_and_staff_cannot_ask(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Pages Test Edit Invalid', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old headline'],
            ],
            'is_published' => false,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => json_encode(['patches' => [], 'explanation' => 'x'])]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode(['patches' => [], 'explanation' => 'x'])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->set('editRequest.'.$page->id, 'make the headline stronger')
            ->call('askEdit', $page->id)
            ->assertSet('success', null)
            ->assertSet('error', 'The AI did not propose a change. Name the block and the exact words you want, for example: on the hero, set the headline to …');

        $page->refresh();
        $this->assertArrayNotHasKey('pending_edit', $page->draft_meta ?? []);

        $staff = User::factory()->create(['role' => UserRole::Manager]);
        TestCase::provisionTenant(['owner_user_id' => $staff->id]);
        $this->actingAs($staff);

        Livewire::test(Pages::class)
            ->call('askEdit', $page->id)
            ->assertForbidden();
    }

    public function test_the_conversation_continues_on_the_proposal_not_the_draft(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Pages Test Converse', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old headline'],
            ],
            'is_published' => false,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->pushResponse(Http::response(
                    json_encode([
                        'content' => [['type' => 'text', 'text' => json_encode(['patches' => [['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'Distinctive new headline 4471']], 'explanation' => 'Rewrote the headline.'])]],
                        'stop_reason' => 'end_turn',
                        'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                    ]),
                    200,
                    ['Content-Type' => 'application/json']
                ))
                ->pushResponse(Http::response(
                    json_encode([
                        'content' => [['type' => 'text', 'text' => json_encode(['patches' => [['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'Distinctive second headline 7731']], 'explanation' => 'Shortened it.'])]],
                        'stop_reason' => 'end_turn',
                        'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                    ]),
                    200,
                    ['Content-Type' => 'application/json']
                )),
            'api.openai.com/*' => Http::sequence()
                ->pushResponse(Http::response([
                    'id' => 'msg_edit',
                    'choices' => [
                        ['message' => ['content' => json_encode(['patches' => [['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'Distinctive new headline 4471']], 'explanation' => 'Rewrote the headline.'])]],
                    ],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
                ]))
                ->pushResponse(Http::response([
                    'id' => 'msg_edit_2',
                    'choices' => [
                        ['message' => ['content' => json_encode(['patches' => [['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'Distinctive second headline 7731']], 'explanation' => 'Shortened it.'])]],
                    ],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
                ])),
        ]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->set('editRequest.'.$page->id, 'make the headline stronger')
            ->call('askEdit', $page->id);

        $page->refresh();

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->set('editRequest.'.$page->id, 'shorter')
            ->call('askEdit', $page->id);

        $page->refresh();
        $this->assertSame('Distinctive second headline 7731', $page->draft_meta['pending_edit']['blocks'][0]['headline']);
        $this->assertCount(2, $page->draft_meta['pending_edit']['thread']);
        $this->assertSame('Old headline', $page->draft_blocks[0]['headline']);

        Http::assertSent(fn ($req) => str_contains((string) $req->body(), 'Distinctive new headline 4471'));
    }

    public function test_the_proposal_shows_side_by_side_with_the_draft(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Pages Test Side By Side', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old headline'],
            ],
            'is_published' => false,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => json_encode(['patches' => [['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'Distinctive new headline 4471']], 'explanation' => 'Rewrote the headline.'])]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode(['patches' => [['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'Distinctive new headline 4471']], 'explanation' => 'Rewrote the headline.'])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->set('editRequest.'.$page->id, 'make the headline stronger')
            ->call('askEdit', $page->id);

        $this->actingAs($owner)->get(route('x-103.pages').'?edit='.$page->id)
            ->assertSee('Old headline')
            ->assertSee('Distinctive new headline 4471')
            ->assertSee('Not quite?');
    }

    public function test_make_me_a_page_lands_an_unpublished_draft_page_the_owner_can_see(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Pages Test Edit', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => json_encode(['title' => 'Distinctive spring offer 4471', 'slug' => 'Spring Offer!', 'blocks' => [['type' => 'hero', 'headline' => 'Distinctive headline 4472', 'subline' => 'Book before the rain.'], ['type' => 'faq', 'items' => [['question' => 'When?', 'answer' => 'All spring.']]], ['type' => 'marquee', 'text' => 'x']], 'explanation' => 'A page for the spring offer.'])]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode(['title' => 'Distinctive spring offer 4471', 'slug' => 'Spring Offer!', 'blocks' => [['type' => 'hero', 'headline' => 'Distinctive headline 4472', 'subline' => 'Book before the rain.'], ['type' => 'faq', 'items' => [['question' => 'When?', 'answer' => 'All spring.']]], ['type' => 'marquee', 'text' => 'x']], 'explanation' => 'A page for the spring offer.'])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        Livewire::actingAs($owner)->test(Pages::class)->set('pageRequest', 'make a page for our spring gutter offer')->call('makePage')->assertSet('success', fn ($s) => str_starts_with((string) $s, 'Made a draft page "Distinctive spring offer 4471" at /spring-offer with 2 blocks'));

        $page = Page::where('business_id', $biz->id)->where('slug', 'spring-offer')->first();
        $this->assertNotNull($page);
        $this->assertFalse((bool) $page->is_published);
        $this->assertCount(2, $page->draft_blocks);
        $this->assertSame('ai', $page->draft_blocks[0]['source']);
        $this->assertSame('make a page for our spring gutter offer', $page->draft_meta['made_by_ai']['request']);

        $this->actingAs($owner)->get(route('x-103.pages'))->assertSee('Distinctive spring offer 4471')->assertSee('Make me a page');
    }

    public function test_make_me_a_page_never_overwrites_an_existing_slug(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Pages Test Edit', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        Page::create(['business_id' => $biz->id, 'slug' => 'spring-offer', 'title' => 'Existing', 'is_published' => false]);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => json_encode(['title' => 'Distinctive spring offer 4471', 'slug' => 'Spring Offer!', 'blocks' => [['type' => 'hero', 'headline' => 'Distinctive headline 4472', 'subline' => 'Book before the rain.'], ['type' => 'faq', 'items' => [['question' => 'When?', 'answer' => 'All spring.']]], ['type' => 'marquee', 'text' => 'x']], 'explanation' => 'A page for the spring offer.'])]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode(['title' => 'Distinctive spring offer 4471', 'slug' => 'Spring Offer!', 'blocks' => [['type' => 'hero', 'headline' => 'Distinctive headline 4472', 'subline' => 'Book before the rain.'], ['type' => 'faq', 'items' => [['question' => 'When?', 'answer' => 'All spring.']]], ['type' => 'marquee', 'text' => 'x']], 'explanation' => 'A page for the spring offer.'])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        Livewire::actingAs($owner)->test(Pages::class)->set('pageRequest', 'make a page for our spring gutter offer')->call('makePage')->assertSet('success', fn ($s) => str_starts_with((string) $s, 'Made a draft page "Distinctive spring offer 4471" at /spring-offer-2 with 2 blocks'));

        $newPage = Page::where('business_id', $biz->id)->where('slug', 'spring-offer-2')->first();
        $this->assertNotNull($newPage);
        $this->assertSame('Distinctive spring offer 4471', $newPage->title);

        $existing = Page::where('business_id', $biz->id)->where('slug', 'spring-offer')->first();
        $this->assertSame('Existing', $existing->title);
    }

    public function test_make_me_a_page_refuses_when_nothing_valid_comes_back_and_staff_cannot_ask(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Pages Test Edit', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => json_encode(['title' => 'x', 'slug' => 'x', 'blocks' => [['type' => 'hero'], ['type' => 'marquee', 'text' => 'x']], 'explanation' => 'x'])]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode(['title' => 'x', 'slug' => 'x', 'blocks' => [['type' => 'hero'], ['type' => 'marquee', 'text' => 'x']], 'explanation' => 'x'])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        Livewire::actingAs($owner)->test(Pages::class)->set('pageRequest', 'make a page')->call('makePage')->assertSet('success', 'no_valid_blocks');
        $this->assertSame(0, Page::where('business_id', $biz->id)->count());

        $staff = User::factory()->create(['role' => UserRole::Manager]);
        TestCase::provisionTenant(['owner_user_id' => $staff->id]);
        $this->actingAs($staff);

        Livewire::test(Pages::class)
            ->call('makePage')
            ->assertForbidden();
    }

    public function test_polish_renders_the_peer_count_on_the_page_and_restore_removes_it(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Pages Test Peer Polish', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Hero headline', 'subline' => 'Hero text', 'source' => 'crawl'],
            ],
            'is_published' => false,
        ]);

        $competitor = (new Competitor)->forceFill([
            'business_id' => $biz->id,
            'location_id' => Location::factory()->create(['business_id' => $biz->id])->id,
            'place_id' => 'place1',
            'name' => 'Peer name',
            'source' => 'auto',
        ]);
        $competitor->save();

        (new CompetitorSiteNote)->forceFill([
            'business_id' => $biz->id,
            'competitor_id' => $competitor->id,
            'status' => 'noted',
            'url' => 'http://example.com',
            'title' => 'Distinctive peer title 5512',
            'headings' => ['Distinctive peer heading 5513'],
            'fetched_at' => now(),
        ])->save();

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => 'Fresh copy 5599']],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response([
                'id' => 'msg_eval',
                'choices' => [
                    ['message' => ['content' => 'Fresh copy 5599']],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        Livewire::actingAs($owner)->test(Pages::class)
            ->call('polish', $page->id);

        $this->actingAs($owner)->get(route('x-103.pages').'?edit='.$page->id)
            ->assertSee('with 1 nearby business as reference');

        Livewire::actingAs($owner)->test(Pages::class)
            ->call('restoreOriginal', $page->id);

        $this->actingAs($owner)->get(route('x-103.pages').'?edit='.$page->id)
            ->assertDontSee('as reference');

        $page->refresh();
        $this->assertArrayNotHasKey('peers', $page->draft_blocks[0]);
    }

    public function test_the_questions_customers_asked_panel_lists_them_escaped(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'questions-page-4521',
            'title' => 'Questions',
            'draft_blocks' => [],
            'is_published' => false,
        ]);

        $this->get(route('x-103.pages').'?edit='.$page->id)
            ->assertOk()
            ->assertSee('Questions customers asked (0)')
            ->assertSee('Nothing waiting');

        Tenancy::setUser($owner->id);
        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => Str::random(10),
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        ChatTurn::create([
            'business_id' => $biz->id,
            'chat_session_id' => $session->id,
            'author_type' => 'visitor',
            'message' => '<b>Distinctive 4521</b>?',
        ]);
        Tenancy::forget();

        $this->get(route('x-103.pages').'?edit='.$page->id)
            ->assertOk()
            ->assertSee('Questions customers asked (1)')
            ->assertSee('&lt;b&gt;Distinctive 4521&lt;/b&gt;?', false)
            ->assertDontSee('<b>Distinctive 4521</b>', false)
            ->assertSee('From site chat');
    }

    public function test_customer_question_panel_draft_answer(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        PriceBookItem::create([
            'business_id' => $biz->id,

            'service_name' => 'A service',
            'price_cents' => 1000,
            'is_confirmed' => true,
            'confirmed_at' => now(),
        ]);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'test-page-4535',
            'title' => 'Test',

        ]);

        $chat = ChatSession::create(['business_id' => $biz->id, 'session_token' => Str::uuid()->toString()]);
        $turn = ChatTurn::create(['business_id' => $biz->id, 'chat_session_id' => $chat->id, 'author_type' => 'visitor', 'message' => 'Distinctive question 4535?', 'created_at' => now()]);

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->pushResponse(Http::response(
                    json_encode([
                        'content' => [['type' => 'text', 'text' => json_encode(['flags' => []])]],
                        'stop_reason' => 'end_turn',
                        'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                    ]),
                    200,
                    ['Content-Type' => 'application/json']
                ))
                ->pushResponse(Http::response(
                    json_encode([
                        'content' => [['type' => 'text', 'text' => json_encode(['question' => 'Distinctive question 4535?', 'answer' => 'Answer'])]],
                        'stop_reason' => 'end_turn',
                        'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                    ]),
                    200,
                    ['Content-Type' => 'application/json']
                )),
            'api.openai.com/*' => Http::sequence()
                ->push([
                    'id' => 'chatcmpl-mod',
                    'object' => 'chat.completion',
                    'created' => 12345,
                    'model' => 'gpt-4o-mini',
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => json_encode(['flags' => []]),
                            ],
                            'finish_reason' => 'stop',
                        ],
                    ],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
                ])
                ->push([
                    'id' => 'chatcmpl-1',
                    'object' => 'chat.completion',
                    'created' => 12345,
                    'model' => 'gpt-4o-mini',
                    'choices' => [
                        [
                            'message' => [
                                'role' => 'assistant',
                                'content' => json_encode(['question' => 'Distinctive question 4535?', 'answer' => 'Answer']),
                            ],
                            'finish_reason' => 'stop',
                        ],
                    ],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
                ]),
        ]);

        Livewire::test(Pages::class)
            ->call('openEditor', $page->id)
            ->assertSee('Draft an answer')
            ->assertSee('Choose a page')
            ->set('answerPage.chat:'.$turn->id, $page->id)
            ->call('draftAnswer', 'chat:'.$turn->id)
            ->assertSet('success', fn ($s) => str_starts_with($s, 'Drafted an answer with'));

        $manager = User::factory()->create(['role' => UserRole::Manager]);
        TestCase::provisionTenant(['owner_user_id' => $manager->id]);
        $this->actingAs($manager);
        Livewire::test(Pages::class)
            ->call('draftAnswer', 'chat:'.$turn->id)
            ->assertForbidden();
    }

    public function test_the_questions_list_does_not_deny_the_draft_button(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::setUser($owner->id);
        Tenancy::set($biz->id);

        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => Str::random(10),
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        ChatTurn::create([
            'business_id' => $biz->id,
            'chat_session_id' => $session->id,
            'author_type' => 'visitor',
            'message' => 'Chat question 1?',
            'created_at' => now()->subMinutes(10),
        ]);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'test-page-draft',
            'title' => 'Test Draft',
            'is_published' => false,
        ]);

        $this->actingAs($owner);

        Livewire::test(Pages::class)
            ->call('openEditor', $page->id)
            ->assertSee('Draft an answer')
            ->assertDontSee('arrives in the next update');
    }

    public function test_a_published_page_with_a_changed_draft_can_be_published_again_without_unpublishing(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'launch-q9z', 'Launch');

        app(SitePublishAction::class)->handle($biz->id, $page->id, []);

        $page->update(['draft_blocks' => [['type' => 'hero', 'headline' => 'Distinctive changed headline 4945']]]);

        Event::fake([SitePublished::class]);

        Livewire::test(Pages::class)
            ->call('publish', $page->id)
            ->assertOk()
            ->assertDontSee('already published');

        $this->assertEquals(2, PageVersion::where('page_id', $page->id)->count());
        $this->assertTrue($page->fresh()->is_published);
        Event::assertDispatched(SitePublished::class);
    }

    public function test_pages_offers_publish_changes_only_when_the_draft_differs(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $action = app(PageCreateAction::class);
        $page = $action->handle($biz->id, 'launch-q9z', 'Launch');

        app(SitePublishAction::class)->handle($biz->id, $page->id, []);

        $this->get(route('x-103.pages').'?edit='.$page->id)
            ->assertOk()
            ->assertDontSee('Publish changes')
            ->assertSee('Unpublish');

        $page->update(['draft_blocks' => [['type' => 'hero', 'headline' => 'Distinctive changed headline 4945']]]);

        $this->get(route('x-103.pages').'?edit='.$page->id)
            ->assertOk()
            ->assertSee('Publish changes')
            ->assertSee('Unpublish');
    }

    public function test_add_hero_writes_one_hero_block_from_the_business_name_and_the_saved_tagline(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['name' => 'Faq Controls Test', 'owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'add-hero-x7q',
            'title' => 'Blank',
            'draft_blocks' => [],
            'is_published' => false,
        ]);

        app(BusinessFacts::class)->set($biz->id, BusinessFactKey::TAGLINE, 'Soft serve and milk tea, made to order');

        Livewire::test(Pages::class)->call('addHero', $page->id);

        $page->refresh();
        $this->assertCount(1, $page->draft_blocks);
        $this->assertSame('hero', $page->draft_blocks[0]['type']);
        $this->assertSame('Faq Controls Test', $page->draft_blocks[0]['headline']);
        $this->assertSame('Soft serve and milk tea, made to order', $page->draft_blocks[0]['subline']);
    }
}
