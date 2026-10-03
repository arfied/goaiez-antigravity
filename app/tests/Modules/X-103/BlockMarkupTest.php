<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Services\Industry\IndustryStartingPoints;
use Tests\TestCase;

class BlockMarkupTest extends TestCase
{
    private const CONTEXT = [
        'businessName' => '',
        'deployHash' => 'weight',
        'tenant_storage_url_prefix' => '/m/',
        'form_action_base' => '/f',
    ];

    private function renderBlock(array $block): string
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);

        return app(SiteBlockRenderer::class)->render([$block], ['tokens' => $tokens] + self::CONTEXT);
    }

    public function test_hero_full(): void
    {
        $html = $this->renderBlock([
            'type' => 'hero',
            'headline' => 'DISTINCT_HEADLINE',
            'subline' => 'DISTINCT_SUBLINE',
            'image_path' => 'DISTINCT_PATH.jpg',
            'image_alt' => 'DISTINCT_ALT',
            'image_width' => 800,
            'image_height' => 600,
        ]);

        $this->assertStringContainsString('DISTINCT_HEADLINE', $html);
        $this->assertStringContainsString('DISTINCT_SUBLINE', $html);
        $this->assertStringContainsString('DISTINCT_PATH.jpg', $html);
        $this->assertStringContainsString('DISTINCT_ALT', $html);
        $this->assertStringContainsString('800', $html);
        $this->assertStringContainsString('600', $html);

        $this->assertStringContainsString('site-block__inner', $html);
        $this->assertStringContainsString('hero__grid', $html);
        $this->assertStringContainsString('stack', $html);
        $this->assertStringContainsString('lede', $html);
        $this->assertStringContainsString('hero__media', $html);
        $this->assertStringContainsString('media', $html);
        $this->assertStringContainsString('media--wide', $html);
    }

    public function test_hero_minimum(): void
    {
        $html = $this->renderBlock([
            'type' => 'hero',
            'headline' => 'ONLY_HEADLINE',
        ]);

        $this->assertStringContainsString('ONLY_HEADLINE', $html);
        $this->assertStringNotContainsString('class="lede"', $html);
        $this->assertStringNotContainsString('class="hero__media"', $html);
        $this->assertStringNotContainsString('class="media media--wide"', $html);
    }

    public function test_about_full(): void
    {
        $html = $this->renderBlock([
            'type' => 'about',
            'heading' => 'DISTINCT_ABOUT_HEADING',
            'text' => 'DISTINCT_ABOUT_TEXT',
        ]);

        $this->assertStringContainsString('DISTINCT_ABOUT_HEADING', $html);
        $this->assertStringContainsString('DISTINCT_ABOUT_TEXT', $html);

        $this->assertStringContainsString('site-block__inner', $html);
    }

    public function test_about_minimum(): void
    {
        $html = $this->renderBlock([
            'type' => 'about',
            'text' => 'ONLY_TEXT',
        ]);

        $this->assertStringContainsString('ONLY_TEXT', $html);
        $this->assertStringNotContainsString('<h2>', $html);
    }

    public function test_services_full(): void
    {
        $html = $this->renderBlock([
            'type' => 'services',
            'heading' => 'DISTINCT_SERVICES_HEADING',
            'items' => [
                [
                    'name' => 'DISTINCT_SERVICE_NAME',
                    'price_text' => 'DISTINCT_SERVICE_PRICE',
                    'description' => 'DISTINCT_SERVICE_DESC',
                ],
            ],
        ]);

        $this->assertStringContainsString('DISTINCT_SERVICES_HEADING', $html);
        $this->assertStringContainsString('DISTINCT_SERVICE_NAME', $html);
        $this->assertStringContainsString('DISTINCT_SERVICE_PRICE', $html);
        $this->assertStringContainsString('DISTINCT_SERVICE_DESC', $html);

        $this->assertStringContainsString('site-block__inner', $html);
        $this->assertStringContainsString('card', $html);
    }

    public function test_services_minimum(): void
    {
        $html = $this->renderBlock([
            'type' => 'services',
            'items' => [
                [
                    'name' => 'ONLY_SERVICE_NAME',
                ],
            ],
        ]);

        $this->assertStringContainsString('ONLY_SERVICE_NAME', $html);
        $this->assertStringNotContainsString('<h2>', $html);
        $this->assertStringNotContainsString('<span>', $html);
        $this->assertStringNotContainsString('<p>', $html);
    }

    public function test_team_full(): void
    {
        $html = $this->renderBlock([
            'type' => 'team',
            'heading' => 'DISTINCT_TEAM_HEADING',
            'items' => [
                [
                    'name' => 'DISTINCT_TEAM_NAME',
                    'role' => 'DISTINCT_TEAM_ROLE',
                ],
            ],
        ]);

        $this->assertStringContainsString('DISTINCT_TEAM_HEADING', $html);
        $this->assertStringContainsString('DISTINCT_TEAM_NAME', $html);
        $this->assertStringContainsString('DISTINCT_TEAM_ROLE', $html);

        $this->assertStringContainsString('site-block__inner', $html);
        $this->assertStringContainsString('card', $html);
    }

    public function test_team_minimum(): void
    {
        $html = $this->renderBlock([
            'type' => 'team',
            'items' => [
                [
                    'name' => 'ONLY_TEAM_NAME',
                ],
            ],
        ]);

        $this->assertStringContainsString('ONLY_TEAM_NAME', $html);
        $this->assertStringNotContainsString('<h2>', $html);
        $this->assertStringNotContainsString('<span>', $html);
    }

    public function test_gallery_full(): void
    {
        $html = $this->renderBlock([
            'type' => 'gallery',
            'heading' => 'DISTINCT_GALLERY_HEADING',
            'items' => [
                [
                    'image_path' => 'DISTINCT_GALLERY_PATH.jpg',
                    'alt' => 'DISTINCT_GALLERY_ALT',
                    'width' => 800,
                    'height' => 600,
                ],
            ],
        ]);

        $this->assertStringContainsString('DISTINCT_GALLERY_HEADING', $html);
        $this->assertStringContainsString('DISTINCT_GALLERY_PATH.jpg', $html);
        $this->assertStringContainsString('DISTINCT_GALLERY_ALT', $html);
        $this->assertStringContainsString('800', $html);
        $this->assertStringContainsString('600', $html);

        $this->assertStringContainsString('media', $html);
        $this->assertStringContainsString('media--square', $html);
    }

    public function test_gallery_minimum(): void
    {
        $html = $this->renderBlock([
            'type' => 'gallery',
            'items' => [
                [
                    'image_path' => 'ONLY_GALLERY_PATH.jpg',
                ],
            ],
        ]);

        $this->assertStringContainsString('ONLY_GALLERY_PATH.jpg', $html);
        $this->assertStringNotContainsString('<h2>', $html);
        $this->assertStringNotContainsString('width=', $html);
        $this->assertStringNotContainsString('height=', $html);
    }

    public function test_reviews_strip_full(): void
    {
        $html = $this->renderBlock([
            'type' => 'reviews_strip',
            'heading' => 'DISTINCT_REVIEWS_HEADING',
            'items' => [
                [
                    'rating' => 'DISTINCT_RATING',
                    'text' => 'DISTINCT_REVIEW_TEXT',
                    'author' => 'DISTINCT_AUTHOR',
                    'source' => 'DISTINCT_SOURCE',
                ],
            ],
        ]);

        $this->assertStringContainsString('DISTINCT_REVIEWS_HEADING', $html);
        $this->assertStringContainsString('DISTINCT_RATING', $html);
        $this->assertStringContainsString('DISTINCT_REVIEW_TEXT', $html);
        $this->assertStringContainsString('DISTINCT_AUTHOR', $html);
        $this->assertStringContainsString('DISTINCT_SOURCE', $html);

        $this->assertStringContainsString('site-block__inner', $html);
        $this->assertStringContainsString('card', $html);
    }

    public function test_reviews_strip_minimum(): void
    {
        $html = $this->renderBlock([
            'type' => 'reviews_strip',
            'items' => [
                [
                    'rating' => 'ONLY_RATING',
                    'text' => 'ONLY_REVIEW_TEXT',
                ],
            ],
        ]);

        $this->assertStringContainsString('ONLY_RATING', $html);
        $this->assertStringContainsString('ONLY_REVIEW_TEXT', $html);
        $this->assertStringNotContainsString('<h2>', $html);
        $this->assertStringNotContainsString('by ', $html);
        $this->assertStringNotContainsString('on <', $html); // This avoids matching button CSS
    }

    public function test_booking_button_with_label_and_url(): void
    {
        $html = $this->renderBlock([
            'type' => 'booking_button',
            'label' => 'DISTINCT_BOOKING_LABEL',
            'url' => 'DISTINCT_BOOKING_URL',
        ]);

        $this->assertStringContainsString('DISTINCT_BOOKING_LABEL', $html);
        $this->assertStringContainsString('DISTINCT_BOOKING_URL', $html);
        $this->assertStringContainsString('<a ', $html);
    }

    public function test_booking_button_with_label_and_no_url(): void
    {
        $html = $this->renderBlock([
            'type' => 'booking_button',
            'label' => 'DISTINCT_BOOKING_LABEL_NO_URL',
        ]);

        $this->assertStringContainsString('DISTINCT_BOOKING_LABEL_NO_URL', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringContainsString('site-cta--off', $html);
        $this->assertStringNotContainsString('<a ', $html);
    }

    public function test_booking_button_with_no_label_renders_nothing(): void
    {
        $html = $this->renderBlock([
            'type' => 'booking_button',
            'url' => 'DISTINCT_BOOKING_URL_NO_LABEL',
        ]);

        $this->assertStringNotContainsString('DISTINCT_BOOKING_URL_NO_LABEL', $html);
        $this->assertStringNotContainsString('class="actions"', $html);
    }

    public function test_faq_items()
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);
        $block = [
            'type' => 'faq',
            'items' => [
                ['question' => 'Q1', 'answer' => 'A1'],
                ['question' => 'Q2', 'answer' => 'A2'],
            ],
        ];
        $html = app(SiteBlockRenderer::class)->render([$block], ['tokens' => $tokens] + self::CONTEXT);

        $this->assertStringContainsString('class="faq-item" data-question="Q1"', $html);
        $this->assertStringContainsString('<h3>Q1</h3>', $html);
        $this->assertStringContainsString('<p>A1</p>', $html);
        $this->assertStringContainsString('class="faq-item" data-question="Q2"', $html);
        $this->assertStringNotContainsString('Q1 - A1', $html);
        $this->assertStringNotContainsString('Q2 - A2', $html);

        $blockEmpty = [
            'type' => 'faq',
            'items' => [
                ['question' => 'Q3', 'answer' => ''],
            ],
        ];
        $htmlEmpty = app(SiteBlockRenderer::class)->render([$blockEmpty], ['tokens' => $tokens] + self::CONTEXT);
        $this->assertStringNotContainsString('Q3', $htmlEmpty);
    }

    public function test_bands_alternate()
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);
        $blocks = [
            ['type' => 'hero', 'headline' => 'H'],
            ['type' => 'about', 'text' => 'A'],
            ['type' => 'services', 'items' => [['name' => 'S']]],
            ['type' => 'team', 'items' => [['name' => 'T']]],
        ];
        $html = app(SiteBlockRenderer::class)->render($blocks, ['tokens' => $tokens] + self::CONTEXT);

        preg_match_all('/class="site-block ([a-z_]+(?: site-block--band)?)[^"]*"/', $html, $matches);
        $this->assertEquals(['hero', 'about', 'services site-block--band', 'team'], $matches[1]);
    }

    public function test_a_centered_hero_with_a_button(): void
    {
        $html = $this->renderBlock([
            'type' => 'hero',
            'headline' => 'CENTRED_HEADLINE_7100',
            'variant' => 'centered',
            'cta_label' => 'BOOK_NOW_7101',
            'cta_url' => 'tel:+15550107101',
        ]);

        $this->assertStringContainsString('class="hero-center"', $html);
        $this->assertStringContainsString('BOOK_NOW_7101', $html);
        $this->assertStringContainsString('href="tel:+15550107101"', $html);
        $this->assertStringNotContainsString('class="hero__grid"', $html);
    }

    public function test_a_hero_button_with_an_unsafe_link_is_not_rendered(): void
    {
        $html = $this->renderBlock([
            'type' => 'hero',
            'headline' => 'H',
            'cta_label' => 'BAD_LINK_7102',
            'cta_url' => 'javascript:alert(1)',
        ]);

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('BAD_LINK_7102', $html);
    }

    public function test_a_cover_hero_needs_a_picture(): void
    {
        $cover = $this->renderBlock(['type' => 'hero', 'headline' => 'H', 'variant' => 'cover', 'image_path' => 'COVER_PATH_7103.jpg']);
        $this->assertStringContainsString('class="hero-cover"', $cover);
        $this->assertStringContainsString('COVER_PATH_7103.jpg', $cover);
        $this->assertStringNotContainsString('class="hero__grid"', $cover);

        $noPicture = $this->renderBlock(['type' => 'hero', 'headline' => 'H', 'variant' => 'cover']);
        $this->assertStringContainsString('class="hero__grid"', $noPicture);
        $this->assertStringNotContainsString('class="hero-cover"', $noPicture);
    }

    public function test_a_call_to_action_band_and_a_numbers_row(): void
    {
        $band = $this->renderBlock(['type' => 'cta_band', 'heading' => 'CTA_HEADING_7104', 'text' => 'CTA_TEXT_7105', 'label' => 'CTA_LABEL_7106', 'url' => 'https://example.com/book7106']);
        $this->assertStringContainsString('class="site-block cta site-block--primary"', $band);
        $this->assertStringContainsString('CTA_HEADING_7104', $band);
        $this->assertStringContainsString('CTA_TEXT_7105', $band);
        $this->assertStringContainsString('href="https://example.com/book7106"', $band);

        $stats = $this->renderBlock(['type' => 'stats', 'heading' => 'STATS_HEADING_7107', 'items' => [['value' => 'STAT_VALUE_7108', 'label' => 'STAT_LABEL_7109']]]);
        $this->assertStringContainsString('class="stat-list"', $stats);
        $this->assertStringContainsString('STAT_VALUE_7108', $stats);
        $this->assertStringContainsString('STAT_LABEL_7109', $stats);

        $empty = $this->renderBlock(['type' => 'stats', 'heading' => 'H', 'items' => []]);
        $this->assertStringNotContainsString('data-block-type="stats"', $empty);
    }

    public function test_section_layouts_add_their_modifier_class_and_the_default_stays_the_same(): void
    {
        $list = $this->renderBlock(['type' => 'services', 'variant' => 'list', 'items' => [['name' => 'S1']]]);
        $this->assertStringContainsString('class="site-block services services--list"', $list);

        $default = $this->renderBlock(['type' => 'services', 'items' => [['name' => 'S1']]]);
        $this->assertStringContainsString('class="site-block services "', $default);
        $this->assertStringNotContainsString('class="site-block services services--', $default);

        $unknown = $this->renderBlock(['type' => 'services', 'variant' => 'cover', 'items' => [['name' => 'S1']]]);
        $this->assertStringNotContainsString('class="site-block services services--', $unknown);

        $faq = $this->renderBlock(['type' => 'faq', 'variant' => 'cards', 'question' => 'Q1', 'answer' => 'A1']);
        $this->assertStringContainsString('class="site-block faq faq--cards"', $faq);
        $this->assertStringContainsString('class="faq-item" data-question="Q1"', $faq);

        $contact = $this->renderBlock(['type' => 'contact', 'variant' => 'card', 'phone' => '0100']);
        $this->assertStringContainsString('class="site-block contact contact--card"', $contact);

        $reviews = $this->renderBlock(['type' => 'reviews_strip', 'variant' => 'quote', 'items' => [['author' => 'A', 'rating' => '5', 'text' => 'T']]]);
        $this->assertStringContainsString('class="site-block reviews reviews--quote"', $reviews);
    }

    public function test_an_about_and_a_call_to_action_can_carry_a_picture(): void
    {
        $about = $this->renderBlock(['type' => 'about', 'variant' => 'split', 'text' => 'T', 'image_path' => 'ABOUT_7901.jpg', 'image_alt' => 'A crew at work']);
        $this->assertStringContainsString('class="site-block about about--split"', $about);
        $this->assertStringContainsString('class="about-media"', $about);
        $this->assertStringContainsString('ABOUT_7901.jpg', $about);

        $plain = $this->renderBlock(['type' => 'about', 'text' => 'T']);
        $this->assertStringNotContainsString('class="about-media"', $plain);

        $cta = $this->renderBlock(['type' => 'cta_band', 'heading' => 'H', 'image_path' => 'CTA_7902.jpg']);
        $this->assertStringContainsString('class="site-block cta site-block--primary cta--photo"', $cta);
        $this->assertStringContainsString('class="cta-photo"', $cta);
        $this->assertStringContainsString('CTA_7902.jpg', $cta);
    }
}
