<?php

namespace Tests\Modules\X103;

use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X157\Actions\EdgeDeployAction;
use App\Modules\X157\Models\EdgeZone;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteBlockRendererTest extends TestCase
{
    public function test_renders_fields_escaped(): void
    {
        $renderer = new SiteBlockRenderer;
        $blocks = [
            ['type' => 'hero', 'headline' => 'Hero <script>', 'subline' => 'Sub <script>'],
            ['type' => 'about', 'heading' => 'About <script>', 'text' => 'Text <script>'],
            ['type' => 'services', 'heading' => 'Serv <script>', 'items' => [['name' => 'Item <script>', 'price_text' => '10 <script>', 'description' => 'Desc <script>']]],
            ['type' => 'reviews_strip', 'heading' => 'Rev <script>', 'items' => [['rating' => '5', 'text' => 'Good <script>', 'author' => 'Me <script>', 'source' => 'Yelp <script>']]],
            ['type' => 'booking_button', 'label' => 'Book <script>', 'url' => 'https://b.com/<script>'],
            ['type' => 'contact', 'address' => 'Addr <script>', 'phone' => '123 <script>', 'email' => 'e@e.com <script>', 'hours' => [['day' => 'Mon <script>', 'open' => '9 <script>', 'close' => '5 <script>']]],
            ['type' => 'faq', 'question' => 'Q <script>', 'answer' => 'A <script>'],
            ['type' => 'video_embed', 'name' => 'Vid <script>', 'contentUrl' => 'https://v.com/<script>', 'uploadDate' => '2023 <script>'],
        ];

        $html = $renderer->render($blocks, []);

        $this->assertStringContainsString('Hero &lt;script&gt;', $html);
        $this->assertStringContainsString('Sub &lt;script&gt;', $html);
        $this->assertStringContainsString('About &lt;script&gt;', $html);
        $this->assertStringContainsString('Text &lt;script&gt;', $html);
        $this->assertStringContainsString('Serv &lt;script&gt;', $html);
        $this->assertStringContainsString('Item &lt;script&gt;', $html);
        $this->assertStringContainsString('10 &lt;script&gt;', $html);
        $this->assertStringContainsString('Desc &lt;script&gt;', $html);
        $this->assertStringContainsString('Rev &lt;script&gt;', $html);
        $this->assertStringContainsString('Good &lt;script&gt;', $html);
        $this->assertStringContainsString('Me &lt;script&gt;', $html);
        $this->assertStringContainsString('Yelp &lt;script&gt;', $html);
        $this->assertStringContainsString('Book &lt;script&gt;', $html);
        $this->assertStringContainsString('https://b.com/&lt;script&gt;', $html);
        $this->assertStringContainsString('Addr &lt;script&gt;', $html);
        $this->assertStringContainsString('123 &lt;script&gt;', $html);
        $this->assertStringContainsString('e@e.com &lt;script&gt;', $html);
        $this->assertStringContainsString('Mon &lt;script&gt;', $html);
        $this->assertStringContainsString('9 &lt;script&gt;', $html);
        $this->assertStringContainsString('5 &lt;script&gt;', $html);
        $this->assertStringContainsString('Q &lt;script&gt;', $html);
        $this->assertStringContainsString('A &lt;script&gt;', $html);
        $this->assertStringContainsString('Vid &lt;script&gt;', $html);
        $this->assertStringContainsString('https://v.com/&lt;script&gt;', $html);

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_skips_missing_required_fields(): void
    {
        $renderer = new SiteBlockRenderer;
        $blocks = [
            ['type' => 'hero'],
            ['type' => 'about'],
            ['type' => 'services', 'heading' => 'bad'],
            ['type' => 'reviews_strip', 'heading' => 'bad'],
            ['type' => 'booking_button', 'label' => 'A'],
            ['type' => 'faq', 'question' => 'Q'],
            ['type' => 'video_embed', 'name' => 'V', 'contentUrl' => 'U'],
        ];

        $html = $renderer->render($blocks, []);
        $this->assertStringNotContainsString('class="site-block hero"', $html);
        $this->assertStringNotContainsString('class="site-block about"', $html);
        $this->assertStringNotContainsString('class="site-block services"', $html);
        $this->assertStringNotContainsString('class="site-block reviews"', $html);
        $this->assertStringNotContainsString('class="site-block booking"', $html);
        $this->assertStringNotContainsString('id="faq-x176"', $html);
        $this->assertStringNotContainsString('id="videos-x176"', $html);
    }

    public function test_skips_unknown_type(): void
    {
        $renderer = new SiteBlockRenderer;
        $html = $renderer->render([['type' => 'unknown_magic', 'foo' => 'bar']], []);
        $this->assertStringNotContainsString('unknown_magic', $html);
    }

    public function test_preserves_order(): void
    {
        $renderer = new SiteBlockRenderer;
        $html = $renderer->render([
            ['type' => 'about', 'text' => 'First'],
            ['type' => 'hero', 'headline' => 'Second'],
        ], []);

        $pos1 = strpos($html, 'First');
        $pos2 = strpos($html, 'Second');
        $this->assertTrue($pos1 < $pos2);
    }

    public function test_faq_video_byte_identical_to_fixture(): void
    {
        $renderer = new SiteBlockRenderer;
        $blocks = [
            ['type' => 'video_embed', 'name' => 'Test Video', 'contentUrl' => 'https://video.com', 'uploadDate' => '2024-01-01'],
            ['type' => 'faq', 'question' => 'Q1', 'answer' => 'A1'],
        ];
        $html = $renderer->render($blocks, []);

        $fixture = '<div id="videos-x176">
  <div class="video-item" data-name="Test Video" data-url="https://video.com">Test Video</div>
</div>
<div id="faq-x176">
  <div class="faq-item" data-question="Q1">Q1 - A1</div>
</div>
';
        $this->assertStringContainsString($fixture, $html);
    }

    public function test_deploy_serves_rendered_blocks(): void
    {
        Http::fake();
        Storage::fake('local');

        $b = self::provisionTenant(['name' => 'Block Tenant']);
        Tenancy::set((int) $b->id);

        $z = EdgeZone::create(['business_id' => $b->id, 'domain_name' => 'block.com', 'has_valid_ssl' => true, 'zone_id' => 'z_456']);
        $p = Page::create(['business_id' => $b->id, 'title' => 'Block Page', 'slug' => 'block']);

        PageVersion::create([
            'business_id' => $b->id,
            'page_id' => $p->id,
            'commit_id' => 'commit_block_1',
            'content_blocks' => [
                ['type' => 'hero', 'headline' => 'Super <Hero>'],
                ['type' => 'services', 'items' => [['name' => 'Clean <Service>']]],
                ['type' => 'booking_button', 'label' => 'Book <Now>', 'url' => '/book'],
            ],
        ]);

        $action = app(EdgeDeployAction::class);
        $res = $action->handle($b->id, $z->id, 100, 1500, $p->id, 'commit_block_1', 'Block Tenant');
        $hash = $res['deploy_hash'];

        $response = $this->get("/sites/{$b->id}/{$hash}");
        $response->assertStatus(200);

        $response->assertSee('Super &lt;Hero&gt;', false);
        $response->assertSee('Clean &lt;Service&gt;', false);
        $response->assertSee('/book', false);

        Http::assertNothingSent();
    }

    public function test_booking_form_block_renders_a_form_posting_to_the_sites_book_route(): void
    {
        $renderer = new SiteBlockRenderer;
        $html = $renderer->render(
            [['type' => 'booking_form', 'heading' => 'Distinctive booking 4471', 'service' => 'Haircut']],
            ['form_action_base' => 'https://site.example/sites/9/deploy_x']
        );

        $this->assertStringContainsString('action="https://site.example/sites/9/deploy_x/book"', $html);
        $this->assertStringContainsString('name="preferred_date"', $html);
        $this->assertStringContainsString('name="phone"', $html);
        $this->assertStringContainsString('value="Haircut"', $html);
        $this->assertStringContainsString('Distinctive booking 4471', $html);

        $htmlBad = $renderer->render([['type' => 'booking_form']], []);
        $this->assertStringNotContainsString('site-block-booking', $htmlBad);
    }

    public function test_renders_opening_hours_in_contact_block(): void
    {
        $renderer = new SiteBlockRenderer;
        $html = $renderer->render([
            ['type' => 'contact', 'hours' => [['day' => 'Monday', 'open' => '08:00', 'close' => '17:00']]],
        ], []);
        $this->assertStringContainsString('Monday: 08:00 - 17:00', $html);
    }

    public function test_contact_block_renders_stated_facts(): void
    {
        $html = (new SiteBlockRenderer)->render([[
            'type' => 'contact',
            'facts' => ['licence_number' => 'LIC-4473', 'years_in_business' => '12'],
        ]], []);
        $this->assertStringContainsString('Licence number: LIC-4473', $html);
        $this->assertStringContainsString('Years in business: 12', $html);

        $htmlNoFacts = (new SiteBlockRenderer)->render([[
            'type' => 'contact',
        ]], []);
        $this->assertStringNotContainsString('Licence number', $htmlNoFacts);
    }
}
