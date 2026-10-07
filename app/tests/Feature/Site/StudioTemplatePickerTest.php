<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Jobs\Webstudio\CreateWebstudioSiteFromTemplateJob;
use App\Livewire\Site\Studio;
use App\Models\Business;
use App\Models\User;
use App\Models\WebstudioSite;
use App\Models\WebstudioTemplateProject;
use App\Modules\X103\Domain\SiteTemplates;
use App\Modules\X103\Models\Page;
use App\Services\Webstudio\WebstudioSites;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class StudioTemplatePickerTest extends TestCase
{
    /** @return array{0: User, 1: Business, 2: Page} */
    private function site(string $industry): array
    {
        // Fixture taken from StudioInspectorTest::test_a_theme_changes_the_look_offers_publish_and_undo_brings_the_old_look_back.
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'Picker Owner 8812', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $business->update(['industry' => $industry]);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'home', 'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old headline 8813'],
                ['type' => 'about', 'text' => 'Unchanged'],
            ],
            'is_published' => false,
        ]);

        return [$owner, $business, $page];
    }

    public function test_the_studio_page_offers_every_template_with_the_ones_for_this_business_first(): void
    {
        [$owner] = $this->site('trades');

        $html = $this->actingAs($owner)->get(route('site.studio'))->assertOk()->getContent();
        foreach (SiteTemplates::TEMPLATES as $id => $template) {
            $this->assertStringContainsString("wire:click=\"applyTemplate('{$id}')\"", $html, $id);
        }
        $this->assertLessThan(strpos($html, "applyTemplate('calm-spa')"), strpos($html, "applyTemplate('trades-pro')"));

        // The templates made for personal care come first, the rest after, each group in list order — however many there are.
        $care = array_keys(array_filter(SiteTemplates::TEMPLATES, fn (array $t): bool => in_array('care', $t['families'], true)));
        $this->assertContains('calm-spa', $care);
        $this->assertNotContains('trades-pro', $care);
        $this->assertSame(array_merge($care, array_values(array_diff(array_keys(SiteTemplates::TEMPLATES), $care))), array_keys(SiteTemplates::forFamily('care')));
        $this->assertSame(array_keys(SiteTemplates::TEMPLATES), array_keys(SiteTemplates::forFamily(null)));
    }

    public function test_a_template_redraws_the_preview_and_undo_brings_the_old_look_back(): void
    {
        [, $business, $page] = $this->site('trades');
        $before = Business::find($business->id)->site_tokens;

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('applyTemplate', 'trades-pro')
            ->assertSet('error', null)
            ->assertViewHas('currentTemplate', 'trades-pro')
            ->assertViewHas('previewHtml', fn ($html) => str_contains($html, 'class="tp-hero tp-hero--plain"') && str_contains($html, '<span>Picker Owner 8812</span>'));

        $this->assertSame('trades-pro', Business::find($business->id)->site_tokens['template']);
        $page->refresh();
        $this->assertTrue($page->draft_meta['look_changed']);
        $this->assertEquals([['type' => 'hero', 'headline' => 'Old headline 8813'], ['type' => 'about', 'text' => 'Unchanged']], $page->draft_blocks);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('undo')
            ->assertSet('error', null);
        $this->assertEquals($before, Business::find($business->id)->site_tokens);

        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('applyTemplate', 'no-such-template')
            ->assertSet('error', 'There is no such template.');

        $manager = User::factory()->create(['role' => UserRole::Manager]);
        Livewire::actingAs($manager)->test(Studio::class)
            ->set('pageId', $page->id)
            ->call('applyTemplate', 'trades-pro')
            ->assertForbidden();
    }

    protected function tearDown(): void
    {
        WebstudioTemplateProject::query()->delete();
        parent::tearDown();
    }

    public function test_the_picker_shows_no_start_in_webstudio_when_webstudio_template_projects_is_empty(): void
    {
        [$owner, $business, $page] = $this->site('trades');

        $this->actingAs($owner)->get(route('site.studio'))
            ->assertOk()
            ->assertDontSee('Start in Webstudio');
    }

    public function test_the_picker_shows_start_in_webstudio_only_for_templates_with_a_webstudio_project(): void
    {
        [$owner, $business, $page] = $this->site('trades');

        WebstudioTemplateProject::create(['template_id' => 'trades-pro', 'project_id' => 'proj-tpl-1', 'label' => 'Trades Pro', 'builder_origin' => 'https://wstd.dev:5174', 'imported_at' => now()]);

        $html = $this->actingAs($owner)->get(route('site.studio'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Start in Webstudio'));
    }

    public function test_start_in_webstudio_creates_a_site_row_and_queues_the_clone_job(): void
    {
        Queue::fake();
        [$owner, $business, $page] = $this->site('trades');
        WebstudioTemplateProject::create(['template_id' => 'trades-pro', 'project_id' => 'proj-tpl-1', 'label' => 'Trades Pro', 'builder_origin' => 'https://wstd.dev:5174', 'imported_at' => now()]);

        Livewire::test(Studio::class)
            ->call('startInWebstudio', 'trades-pro')
            ->assertSee('Your site is being prepared');

        $site = WebstudioSite::where('business_id', $business->id)->first();
        $this->assertNotNull($site);
        $this->assertSame(WebstudioSite::CREATING, $site->creation_status);
        $this->assertSame(WebstudioSite::SOURCE_TEMPLATE, $site->source);
        $this->assertSame('trades-pro', $site->template_id);

        Queue::assertPushedOn('clone', CreateWebstudioSiteFromTemplateJob::class);
    }

    public function test_start_in_webstudio_is_refused_while_another_site_is_creating(): void
    {
        Queue::fake();
        [$owner, $business, $page] = $this->site('trades');
        WebstudioTemplateProject::create(['template_id' => 'trades-pro', 'project_id' => 'proj-tpl-1', 'label' => 'Trades Pro', 'builder_origin' => 'https://wstd.dev:5174', 'imported_at' => now()]);

        Livewire::test(Studio::class)
            ->call('startInWebstudio', 'trades-pro')
            ->assertSee('Your site is being prepared');

        Livewire::test(Studio::class)
            ->call('startInWebstudio', 'trades-pro')
            ->assertSee(WebstudioSites::REFUSALS['already_creating']);

        $this->assertSame(1, WebstudioSite::where('business_id', $business->id)->count());
    }
}
