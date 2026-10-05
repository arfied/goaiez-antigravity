<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Enums\IndustryFamily;
use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Modules\X103\Domain\SiteFonts;
use App\Modules\X103\Domain\SiteTemplates;
use App\Services\Industry\IndustryStartingPoints;
use App\Services\Industry\SiteStyle;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class SiteTemplatesTest extends TestCase
{
    private const CONTEXT = [
        'businessName' => 'Harbor Line Plumbing',
        'deployHash' => 'template',
        'tenant_storage_url_prefix' => '/m/',
        'form_action_base' => '/f',
    ];

    /** A whole plumber's page, as the AI fills one. */
    private function blocks(): array
    {
        return [
            ['type' => 'hero', 'headline' => 'Plumbing fixed right, the first time', 'subline' => 'Licensed plumbers across Tacoma.', 'cta_label' => 'Get a free quote', 'cta_url' => '#contact', 'image_path' => 'hero.jpg', 'image_alt' => 'A plumber at work'],
            ['type' => 'stats', 'items' => [['value' => '18', 'label' => 'Years in Tacoma'], ['value' => '24/7', 'label' => 'Emergency line']]],
            ['type' => 'services', 'heading' => 'Repairs we handle', 'items' => [['name' => 'Leak repair', 'description' => 'Hidden leaks found and fixed.', 'price_text' => 'from $129'], ['name' => 'Water heaters', 'description' => 'Repair or same-day replacement.']]],
            ['type' => 'about', 'heading' => 'A family crew', 'text' => 'We show up on time and agree the price first.', 'image_path' => 'about.jpg'],
            ['type' => 'gallery', 'heading' => 'Recent work', 'items' => [['image_path' => 'one.jpg'], ['image_path' => 'two.jpg'], ['image_path' => 'three.jpg'], ['image_path' => 'four.jpg']]],
            ['type' => 'reviews_strip', 'heading' => 'What homeowners say', 'items' => [['author' => 'Maria G.', 'rating' => '5', 'text' => 'Fixed the same day.', 'source' => 'Google']]],
            ['type' => 'booking_button', 'label' => 'Book online', 'url' => 'https://book.example.com/harbor'],
            ['type' => 'faq', 'heading' => 'Common questions', 'items' => [['question' => 'Do you charge for estimates?', 'answer' => 'No, estimates are free.']]],
            ['type' => 'cta_band', 'heading' => 'Leak or clog?', 'text' => 'Talk to a plumber now.', 'label' => 'Book a visit', 'url' => '#contact'],
            ['type' => 'contact', 'phone' => '(253) 555-0187', 'email' => 'office@example.com', 'address' => '2215 Pacific Ave, Tacoma',
                'hours' => [['day' => 'Mon–Fri', 'open' => '7:00', 'close' => '18:00']], 'facts' => ['insurance' => 'Licensed and insured', 'service_area' => 'Tacoma and Lakewood']],
            ['type' => 'form', 'source' => 'forms', 'definition_id' => 7701, 'fields' => [['name' => 'name', 'label' => 'Your name 7702', 'type' => 'text'], ['name' => 'email', 'label' => 'Email', 'type' => 'email'], ['name' => 'message', 'label' => 'How can we help? 7703', 'type' => 'textarea']], 'required' => ['name', 'email'], 'honeypot' => 'website_7704'],
            ['type' => 'products', 'heading' => 'Parts we stock', 'items' => [['name' => 'Shut-off valve', 'price_text' => '$24', 'description' => 'Quarter-turn brass valve.', 'image_path' => 'valve.jpg', 'url' => 'https://shop.example.com/valve']]],
        ];
    }

    private function render(array $blocks, ?string $template, bool $editable = false): string
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);
        if ($template !== null) {
            // As SiteTemplateApplyAction leaves them: the template, its colours and its fonts.
            $tokens['template'] = $template;
            $tokens['palette'] = SiteTemplates::TEMPLATES[$template]['palette'];
            $tokens['type_pairing'] = SiteTemplates::TEMPLATES[$template]['type_pairing'];
        }

        return app(SiteBlockRenderer::class)->render($blocks, ['tokens' => $tokens, 'editable' => $editable] + self::CONTEXT);
    }

    public function test_every_template_is_complete_readable_and_self_contained(): void
    {
        $this->assertNotEmpty(SiteTemplates::TEMPLATES);

        foreach (SiteTemplates::TEMPLATES as $id => $template) {
            $p = $template['palette'];
            $this->assertSame(SiteStyle::PALETTE_KEYS, array_keys($p), $id);
            foreach (['heading', 'body'] as $role) {
                $stack = $template['type_pairing'][$role];
                $this->assertContains($stack, SiteStyle::FONT_STACKS, $id);
                $this->assertArrayHasKey(trim(explode(',', $stack)[0]), SiteFonts::FAMILIES, "$id: $role font is one we serve");
            }
            $this->assertGreaterThanOrEqual(4.5, SiteStyle::contrast($p['ink'], $p['surface']), $id);
            $this->assertGreaterThanOrEqual(4.5, SiteStyle::contrast($p['ink'], $p['card']), $id);
            $onPrimary = SiteStyle::textOn($p['primary'], [$p['surface'], $p['ink'], '#ffffff', '#111111']);
            $this->assertGreaterThanOrEqual(4.5, SiteStyle::contrast($onPrimary, $p['primary']), "$id: button text");
            $accentText = SiteStyle::readable($p['accent'], [$p['surface'], $p['card']], $p['ink']);
            $this->assertGreaterThanOrEqual(4.5, SiteStyle::contrast($accentText, $p['surface']), "$id: links on the page");
            $this->assertGreaterThanOrEqual(4.5, SiteStyle::contrast($accentText, $p['card']), "$id: links on a card");

            $this->assertTrue(View::exists(SiteTemplates::view($id)), $id);
            $css = SiteTemplates::css($id);
            $this->assertGreaterThan(2000, strlen($css), $id);
            foreach (['url(', '@import', 'http', '<'] as $needle) {
                $this->assertStringNotContainsString($needle, $css, "$id: $needle");
            }
        }

        $this->assertNull(SiteTemplates::get('../themes/bold-trade'));
        $this->assertNull(SiteTemplates::get(null));
    }

    public function test_a_template_draws_the_whole_page_with_its_own_stylesheet_and_no_script(): void
    {
        $plain = $this->render($this->blocks(), null);
        $this->assertStringContainsString('.site-block__inner {', $plain);

        $html = $this->render($this->blocks(), 'trades-pro');
        $this->assertStringNotContainsString('.site-block__inner {', $html);
        $this->assertStringNotContainsString('class="site-block', $html);
        $this->assertStringContainsString('class="tp-hero"', $html);
        $this->assertStringContainsString('<span>Harbor Line Plumbing</span>', $html);
        $this->assertStringContainsString('<details class="tp-menu">', $html);
        $this->assertStringContainsString('href="tel:2535550187"', $html);
        $this->assertStringContainsString('</svg>Licensed and insured</span>', $html);
        $this->assertStringContainsString('Serving Tacoma and Lakewood', $html);
        $this->assertStringContainsString('font-family: "Montserrat"', $html);
        $this->assertSame(0, substr_count($html, '<script'));
        $this->assertSame(1, substr_count($html, 'id="faq-x176"'));
    }

    public function test_the_ai_changing_every_word_cannot_change_the_layout(): void
    {
        // Every tag and class, with the words and the other attribute values taken out.
        $skeleton = fn (string $html): string => preg_replace(['/>[^<]*</', '/\s(?!class=)[a-z-]+="[^"]*"/'], ['><', ''], $html);

        $rewritten = $this->blocks();
        foreach ($rewritten as $i => $block) {
            foreach (['headline', 'subline', 'cta_label', 'heading', 'text', 'label'] as $field) {
                if (isset($block[$field])) {
                    $rewritten[$i][$field] = 'REWRITTEN_'.$field.'_'.$i;
                }
            }
            // A service's icon follows its name by design, so the names stay; every other word changes.
            foreach ($block['items'] ?? [] as $j => $item) {
                foreach (['description', 'text', 'question', 'answer', 'label', 'author'] as $field) {
                    if (isset($item[$field])) {
                        $rewritten[$i]['items'][$j][$field] = 'REWRITTEN_ITEM_'.$i.'_'.$j;
                    }
                }
            }
            // A layout the AI asks for is ignored: the template's layout is frozen.
            $rewritten[$i]['variant'] = $block['type'] === 'hero' ? 'cover' : 'list';
        }

        foreach (array_keys(SiteTemplates::TEMPLATES) as $id) {
            $before = $this->render($this->blocks(), $id);
            $after = $this->render($rewritten, $id);
            $this->assertStringContainsString('REWRITTEN_headline_0', $after, $id);
            $this->assertStringNotContainsString('REWRITTEN_headline_0', $before, $id);
            $this->assertSame($skeleton($before), $skeleton($after), $id);
        }
    }

    public function test_the_studio_can_find_every_section_and_its_words(): void
    {
        foreach (SiteTemplates::TEMPLATES as $id => $template) {
            $html = $this->render($this->blocks(), $id, editable: true);
            foreach ($this->blocks() as $i => $block) {
                if (in_array($block['type'], $template['sections'], true)) {
                    $this->assertSame(1, substr_count($html, 'data-block-index="'.$i.'" data-block-type="'.$block['type'].'"'), "$id: {$block['type']}");
                }
            }
            $this->assertStringContainsString('<h1 data-field="headline">', $html, $id);
            $this->assertStringContainsString('data-field="items.0.question"', $html, $id);

            $this->assertStringNotContainsString('data-field=', $this->render($this->blocks(), $id), $id);
        }
    }

    public function test_unsafe_links_and_markup_never_reach_the_page_and_unlisted_sections_are_not_drawn(): void
    {
        $blocks = $this->blocks();
        $blocks[0]['headline'] = '<b>BOLD_7731</b>';
        $blocks[0]['cta_url'] = 'javascript:alert(1)';
        $blocks[8]['label'] = 'Book a visit 4412';
        $blocks[] = ['type' => 'team', 'heading' => 'TEAM_HEADING_5521', 'items' => [['name' => 'Dan']]];
        unset($blocks[1]);

        $html = $this->render(array_values($blocks), 'trades-pro');
        $this->assertStringContainsString('&lt;b&gt;BOLD_7731&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>BOLD_7731', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('>Get a free quote</a>', $html);
        $this->assertStringContainsString('href="#contact">Book a visit 4412</a>', $html);
        $this->assertStringNotContainsString('TEAM_HEADING_5521', $html);
        $this->assertStringNotContainsString('class="tp-stats"', $html);
    }

    public function test_every_template_draws_a_whole_page_with_no_script_and_one_faq(): void
    {
        foreach (SiteTemplates::TEMPLATES as $id => $template) {
            $html = $this->render($this->blocks(), $id);
            $this->assertStringNotContainsString('.site-block__inner {', $html, $id);
            $this->assertStringNotContainsString('class="site-block', $html, $id);
            $this->assertStringContainsString('>Harbor Line Plumbing<', $html, $id);
            $this->assertStringContainsString('<summary aria-label="Menu">', $html, $id);
            $this->assertStringContainsString('href="tel:2535550187"', $html, $id);
            $this->assertStringContainsString('font-family: "'.trim(explode(',', $template['type_pairing']['heading'])[0]).'"', $html, $id);
            $this->assertSame(0, substr_count($html, '<script'), $id);
            $this->assertSame(1, substr_count($html, 'id="faq-x176"'), $id);
            foreach ($template['families'] as $family) {
                $this->assertNotNull(IndustryFamily::tryFrom($family), "$id: $family");
            }
        }
    }

    public function test_every_template_refuses_unsafe_links_and_escapes_words(): void
    {
        foreach (array_keys(SiteTemplates::TEMPLATES) as $id) {
            $this->assertStringContainsString('href="#contact">Get a free quote</a>', $this->render($this->blocks(), $id), $id);

            $blocks = $this->blocks();
            $blocks[0]['headline'] = '<b>BOLD_7731</b>';
            $blocks[0]['cta_url'] = 'javascript:alert(1)';
            $blocks[] = ['type' => 'video_embed', 'name' => 'VIDEO_NAME_5521', 'contentUrl' => 'https://video.example.com/a.mp4', 'uploadDate' => '2026-01-01'];
            $html = $this->render($blocks, $id);
            $this->assertStringContainsString('&lt;b&gt;BOLD_7731&lt;/b&gt;', $html, $id);
            $this->assertStringNotContainsString('<b>BOLD_7731', $html, $id);
            $this->assertStringNotContainsString('javascript:', $html, $id);
            $this->assertStringNotContainsString('>Get a free quote</a>', $html, $id);
            $this->assertStringNotContainsString('VIDEO_NAME_5521', $html, $id);
        }
    }

    public function test_a_template_with_a_team_section_draws_only_the_owners_people(): void
    {
        $withTeam = 0;
        foreach (SiteTemplates::TEMPLATES as $id => $template) {
            $this->assertStringNotContainsString('id="team"', $this->render($this->blocks(), $id), "$id: no team section without the owner's people");

            $blocks = $this->blocks();
            $blocks[] = ['type' => 'team', 'heading' => 'Our people 6611', 'items' => [['name' => 'Maria Lopez 6612', 'role' => 'Lead therapist 6613'], ['name' => 'demo·Sample 6614']]];
            $html = $this->render($blocks, $id);
            if (in_array('team', $template['sections'], true)) {
                $withTeam++;
                $this->assertStringContainsString('Maria Lopez 6612', $html, $id);
                $this->assertStringContainsString('Lead therapist 6613', $html, $id);
                $this->assertStringContainsString('Our people 6611', $html, $id);
                $this->assertStringNotContainsString('demo·Sample 6614', $html, $id);
            } else {
                $this->assertStringNotContainsString('Maria Lopez 6612', $html, $id);
            }
        }
        $this->assertGreaterThan(0, $withTeam);
    }

    public function test_every_template_keeps_its_top_link_without_a_banner_and_names_its_star_ratings(): void
    {
        $named = 0;
        foreach (array_keys(SiteTemplates::TEMPLATES) as $id) {
            $noHero = array_values(array_filter($this->blocks(), static fn (array $b): bool => $b['type'] !== 'hero'));
            $html = $this->render($noHero, $id);
            $this->assertStringContainsString('href="#top"', $html, $id);
            $this->assertSame(1, substr_count($html, 'id="top"'), $id);

            $html = $this->render($this->blocks(), $id);
            $this->assertSame(1, substr_count($html, 'id="top"'), $id);
            $stars = substr_count($html, 'aria-label="5 out of 5 stars"');
            $this->assertSame($stars, substr_count($html, 'role="img" aria-label="5 out of 5 stars"'), $id);
            $named += $stars;
        }
        $this->assertGreaterThan(0, $named);
    }

    public function test_no_template_says_book_without_a_booking_link(): void
    {
        $blocks = array_values(array_filter($this->blocks(), static fn (array $b): bool => $b['type'] !== 'booking_button'));
        unset($blocks[0]['cta_label'], $blocks[0]['cta_url']);
        foreach (array_keys(SiteTemplates::TEMPLATES) as $id) {
            $html = $this->render($blocks, $id);
            foreach (['>Book<', '>Book now<', '>Book a treatment<'] as $needle) {
                $this->assertStringNotContainsString($needle, $html, "$id: $needle");
            }
            $this->assertStringContainsString('href="tel:2535550187"', $html, $id);
        }

        // The positive control: with a booking link, the templates that say "Book" do say it.
        $booked = 0;
        foreach (array_keys(SiteTemplates::TEMPLATES) as $id) {
            $booked += substr_count($this->render($this->blocks(), $id), '>Book<');
        }
        $this->assertGreaterThan(0, $booked);
    }

    public function test_every_template_draws_the_businesss_own_contact_form_posting_to_the_form_endpoint(): void
    {
        foreach (array_keys(SiteTemplates::TEMPLATES) as $id) {
            $html = $this->render($this->blocks(), $id);
            $this->assertSame(1, substr_count($html, '<form '), $id);
            $this->assertStringContainsString('action="/f/forms/7701"', $html, $id);
            $this->assertStringContainsString('Your name 7702', $html, $id);
            $this->assertStringContainsString('How can we help? 7703', $html, $id);
            $this->assertStringContainsString('name="website_7704"', $html, $id);
            $this->assertMatchesRegularExpression('/name="name" type="text" required/', $html, $id);
            $this->assertMatchesRegularExpression('/<textarea id="form-7701-message" name="message" rows="4"><\/textarea>/', $html, $id);

            $noForm = array_values(array_filter($this->blocks(), static fn (array $b): bool => $b['type'] !== 'form'));
            $this->assertSame(0, substr_count($this->render($noForm, $id), '<form '), "$id: no form without the business's own");
        }
    }

    public function test_a_site_of_several_pages_gets_page_links_in_both_menus_of_every_template(): void
    {
        $pages = [
            ['label' => 'Home', 'href' => '/home', 'current' => true],
            ['label' => 'Services 7711', 'href' => '/services', 'current' => false],
            ['label' => 'Bad 7712', 'href' => 'javascript:alert(1)', 'current' => false],
        ];
        foreach (SiteTemplates::TEMPLATES as $id => $template) {
            $tokens = app(IndustryStartingPoints::class)->for(null);
            $tokens['template'] = $id;
            $tokens['palette'] = $template['palette'];
            $tokens['type_pairing'] = $template['type_pairing'];

            $html = app(SiteBlockRenderer::class)->render($this->blocks(), ['tokens' => $tokens, 'editable' => false, 'site_pages' => $pages] + self::CONTEXT);
            $this->assertSame(2, substr_count($html, '<a href="/home" aria-current="page">Home</a>'), $id);
            $this->assertSame(2, substr_count($html, '<a href="/services">Services 7711</a>'), $id);
            $this->assertStringNotContainsString('Bad 7712', $html, $id);

            $onePage = app(SiteBlockRenderer::class)->render($this->blocks(), ['tokens' => $tokens, 'editable' => false, 'site_pages' => [$pages[0]]] + self::CONTEXT);
            // The stylesheet names the attribute in a selector, so the needle is the attribute as a link carries it.
            $this->assertStringNotContainsString('aria-current="page">', $onePage, "$id: a one-page site keeps its section links");
        }
    }
}
