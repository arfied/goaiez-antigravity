<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Models\Business;
use App\Models\User;
use App\Modules\X103\Actions\SiteBuildWholeAction;
use App\Modules\X103\Actions\SiteDesignGenerateAction;
use App\Modules\X103\Jobs\SiteDesignJob;
use App\Modules\X103\Models\Page;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class SiteBuildWholeActionTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['credentials.anthropic_api_key' => 'fake-key', 'credentials.openai_api_key' => 'fake-key']);
    }

    private function business(): int
    {
        $owner = User::factory()->create();
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        return (int) $biz->id;
    }

    private function fakeAnswer(array $design): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode($design)]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ], 200, ['content-type' => 'application/json'])]);
    }

    public function test_it_creates_the_missing_pages_and_designs_them_one_after_another(): void
    {
        Bus::fake();
        $businessId = $this->business();
        Page::create(['business_id' => $businessId, 'slug' => 'home', 'title' => 'Home', 'draft_blocks' => [['type' => 'hero', 'headline' => 'Mine']], 'is_published' => false]);

        $res = app(SiteBuildWholeAction::class)->handle($businessId);

        $this->assertSame('queued', $res['status']);
        $this->assertSame(['services', 'about', 'contact'], $res['created']);
        $this->assertEqualsCanonicalizing(['home', 'services', 'about', 'contact'], Page::where('business_id', $businessId)->pluck('slug')->all());
        foreach (Page::where('business_id', $businessId)->get() as $page) {
            $this->assertSame('running', $page->draft_meta['designs']['claude']['status'], $page->slug);
        }
        Bus::assertChained([SiteDesignJob::class, SiteDesignJob::class, SiteDesignJob::class, SiteDesignJob::class]);

        $this->assertSame('already_running', app(SiteBuildWholeAction::class)->handle($businessId)['reason']);
        $this->assertSame('unknown_engine', app(SiteBuildWholeAction::class)->handle($businessId, 'nope')['reason']);
    }

    public function test_an_empty_page_is_filled_with_its_design_and_takes_the_theme(): void
    {
        $businessId = $this->business();
        $page = Page::create(['business_id' => $businessId, 'slug' => 'services', 'title' => 'Services', 'is_published' => false]);
        $this->fakeAnswer(['theme' => 'bold-trade', 'blocks' => [['type' => 'hero', 'headline' => 'Filled 8101']], 'explanation' => 'x']);

        (new SiteDesignJob($businessId, (int) $page->id, 'claude', true, false))->handle(app(SiteDesignGenerateAction::class));

        $page->refresh();
        $this->assertSame('Filled 8101', $page->draft_blocks[0]['headline']);
        $this->assertArrayNotHasKey('pending_edit', $page->draft_meta);
        $this->assertSame('bold-trade', Business::find($businessId)->site_tokens['theme']);
        Http::assertSent(fn ($r) => str_contains($r->body(), 'This is the services page'));
    }

    public function test_a_page_that_already_has_sections_is_never_overwritten(): void
    {
        $businessId = $this->business();
        $page = Page::create(['business_id' => $businessId, 'slug' => 'about', 'title' => 'About', 'draft_blocks' => [['type' => 'about', 'text' => 'My own words 8102']], 'is_published' => false]);
        $this->fakeAnswer(['theme' => 'bold-trade', 'blocks' => [['type' => 'hero', 'headline' => 'AI 8103']]]);

        (new SiteDesignJob($businessId, (int) $page->id, 'claude', true, false))->handle(app(SiteDesignGenerateAction::class));

        $page->refresh();
        $this->assertEquals([['type' => 'about', 'text' => 'My own words 8102']], $page->draft_blocks);
        $this->assertArrayNotHasKey('pending_edit', $page->draft_meta);
        $this->assertSame('ready', $page->draft_meta['designs']['claude']['status']);
    }

    public function test_later_pages_keep_the_sites_theme_and_its_colours(): void
    {
        $businessId = $this->business();
        Business::whereKey($businessId)->update(['site_tokens' => json_encode(['theme' => 'warm-local', 'palette' => ['primary' => '#123456']])]);
        $page = Page::create(['business_id' => $businessId, 'slug' => 'about', 'title' => 'About', 'is_published' => false]);
        $this->fakeAnswer(['theme' => 'modern-dark', 'style' => ['palette' => ['primary' => '#ff0000']], 'blocks' => [['type' => 'about', 'text' => 'Kept theme 8104']]]);

        (new SiteDesignJob($businessId, (int) $page->id, 'claude', true, true))->handle(app(SiteDesignGenerateAction::class));

        $page->refresh();
        $design = $page->draft_meta['designs']['claude'];
        $this->assertSame('warm-local', $design['theme']);
        $this->assertNull($design['style']);
        $this->assertSame('Kept theme 8104', $page->draft_blocks[0]['text']);
        $tokens = Business::find($businessId)->site_tokens;
        $this->assertSame('warm-local', $tokens['theme']);
        $this->assertSame('#123456', $tokens['palette']['primary']);
        Http::assertSent(fn ($r) => str_contains($r->body(), 'This site already uses the theme')
            && str_contains($r->body(), 'warm-local')
            && str_contains($r->body(), 'This is the about page'));
    }
}
