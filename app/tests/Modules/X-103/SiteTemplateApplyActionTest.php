<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Models\Business;
use App\Modules\X103\Actions\SiteEditApplyAction;
use App\Modules\X103\Actions\SiteTemplateApplyAction;
use App\Modules\X103\Actions\SiteThemeApplyAction;
use App\Modules\X103\Domain\PagePreview;
use App\Modules\X103\Domain\SiteTemplates;
use App\Modules\X103\Models\Page;
use App\Services\Industry\IndustryStartingPoints;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class SiteTemplateApplyActionTest extends TestCase
{
    use RefreshesTenantDatabase;

    /** @return array{0: Business, 1: Page} */
    private function site(): array
    {
        $biz = $this->provisionTenant(['name' => 'Harbor Line Plumbing 6630']);
        $biz->update(['industry' => 'trades']);
        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Plumbing fixed right'],
                ['type' => 'contact', 'phone' => '(253) 555-0187'],
            ],
            'is_published' => false,
        ]);

        return [$biz, $page];
    }

    public function test_applying_a_template_puts_it_on_the_site_with_an_undo_and_an_unknown_one_is_refused(): void
    {
        [$biz, $page] = $this->site();

        $this->assertSame(['status' => 'refused', 'reason' => 'unknown_template'], app(SiteTemplateApplyAction::class)->handle($biz->id, $page->id, 'no-such-template'));
        $this->assertArrayNotHasKey('template', app(IndustryStartingPoints::class)->forBusiness($biz->id));

        $res = app(SiteTemplateApplyAction::class)->handle($biz->id, $page->id, 'trades-pro');
        $this->assertSame(['status' => 'applied', 'template' => 'trades-pro'], $res);

        $tokens = app(IndustryStartingPoints::class)->forBusiness($biz->id);
        $this->assertSame('trades-pro', $tokens['template']);
        foreach (SiteTemplates::TEMPLATES['trades-pro']['palette'] as $key => $hex) {
            $this->assertSame($hex, $tokens['palette'][$key], $key);
        }

        $page->refresh();
        $this->assertCount(1, $page->draft_meta['undo']);
        $this->assertTrue($page->draft_meta['look_changed']);

        $preview = app(PagePreview::class)->html($page, false);
        $this->assertStringContainsString('class="tp-hero tp-hero--plain"', $preview);
        $this->assertStringContainsString('<span>Harbor Line Plumbing 6630</span>', $preview);
        $this->assertStringNotContainsString('class="site-block hero', $preview);
    }

    public function test_a_theme_or_an_ai_design_takes_the_template_off_and_a_colour_edit_keeps_it(): void
    {
        [$biz, $page] = $this->site();
        app(SiteTemplateApplyAction::class)->handle($biz->id, $page->id, 'trades-pro');

        // A colour edit from the Ask box keeps the template.
        $page->refresh();
        $meta = $page->draft_meta;
        $meta['pending_edit'] = ['blocks' => $page->draft_blocks, 'style' => ['palette' => ['primary' => '#1f6feb']]];
        $page->draft_meta = $meta;
        $page->save();
        app(SiteEditApplyAction::class)->handle($biz->id, $page->id);
        $tokens = app(IndustryStartingPoints::class)->forBusiness($biz->id);
        $this->assertSame('trades-pro', $tokens['template']);
        $this->assertSame('#1f6feb', $tokens['palette']['primary']);

        // An AI design brings a theme, and a theme replaces the template.
        $page->refresh();
        $meta = $page->draft_meta;
        $meta['pending_edit'] = ['blocks' => $page->draft_blocks, 'style' => ['palette' => ['primary' => '#a23e2a']], 'theme' => 'warm-local'];
        $page->draft_meta = $meta;
        $page->save();
        app(SiteEditApplyAction::class)->handle($biz->id, $page->id);
        $tokens = app(IndustryStartingPoints::class)->forBusiness($biz->id);
        $this->assertArrayNotHasKey('template', $tokens);
        $this->assertSame('warm-local', $tokens['theme']);

        // And applying a theme directly does the same.
        app(SiteTemplateApplyAction::class)->handle($biz->id, $page->id, 'trades-pro');
        app(SiteThemeApplyAction::class)->handle($biz->id, $page->id, 'bold-trade');
        $this->assertArrayNotHasKey('template', app(IndustryStartingPoints::class)->forBusiness($biz->id));
        $this->assertStringContainsString('class="site-block hero', app(PagePreview::class)->html($page->refresh(), false));
    }
}
