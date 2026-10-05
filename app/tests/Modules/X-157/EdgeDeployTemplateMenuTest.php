<?php

declare(strict_types=1);

namespace Tests\Modules\X157;

use App\Models\Business;
use App\Modules\X103\Domain\SiteTemplates;
use App\Modules\X103\Models\Page;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Actions\EdgeProvisionAction;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class EdgeDeployTemplateMenuTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_a_template_sites_menu_links_its_published_pages_with_the_current_one_marked(): void
    {
        // Fixture taken from EdgeDeployBlankNamesTest: a business, a provisioned zone and its pages.
        Storage::fake('local');
        $biz = Business::factory()->create();
        Business::whereKey($biz->id)->update(['site_tokens' => json_encode([
            'template' => 'trades-pro',
            'palette' => SiteTemplates::TEMPLATES['trades-pro']['palette'],
            'type_pairing' => SiteTemplates::TEMPLATES['trades-pro']['type_pairing'],
        ], JSON_THROW_ON_ERROR)]);
        $zone = (new EdgeProvisionAction)->handle($biz->id, 'menu-7721.com', true);
        $home = Page::create(['business_id' => $biz->id, 'title' => 'Home', 'slug' => 'home', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Services 7722', 'slug' => 'services', 'is_published' => true]);
        Page::create(['business_id' => $biz->id, 'title' => 'Draft 7723', 'slug' => 'draft', 'is_published' => false]);

        $result = app(EdgeDeployAction::class)->handle($biz->id, $zone->id, 100, 1500, $home->id, 'commit_menu_7724', 'Menu Biz 7725');

        $this->assertSame('deployed', $result['status']);
        $html = (string) Storage::disk('local')->get("sites/{$result['deploy_hash']}.html");
        $this->assertSame(2, preg_match_all('#<a href="[^"]*/home" aria-current="page">Home</a>#', $html));
        $this->assertSame(1, preg_match('#<details class="tp-menu">.*?<a href="[^"]*/services">Services 7722</a>.*?</details>#s', $html));
        $this->assertSame(1, preg_match('#<nav class="tp-nav__links" aria-label="Sections">.*?Services 7722.*?</nav>#s', $html));
        $this->assertStringNotContainsString('Draft 7723', $html);
    }
}
