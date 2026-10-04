<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Livewire\Site\Studio;
use App\Models\Business;
use App\Models\User;
use App\Modules\X103\Domain\SiteTemplates;
use App\Modules\X103\Models\Page;
use App\Support\Tenancy;
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
}
