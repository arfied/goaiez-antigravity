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

        $this->assertStringContainsString('site-block--band', $html);
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

        $this->assertStringContainsString('site-block--band', $html);
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

        $this->assertStringContainsString('site-block--band', $html);
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
}
