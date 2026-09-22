<?php

declare(strict_types=1);

namespace Tests\Modules\X103\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X103\Actions\PageCreateAction;
use App\Modules\X103\Actions\SitePublishAction;
use App\Modules\X103\Events\PagePublished;
use App\Modules\X103\Events\SitePublished;
use App\Modules\X103\Ui\Pages;
use Illuminate\Support\Facades\Event;
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
}
