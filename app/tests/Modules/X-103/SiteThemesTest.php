<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Modules\X103\Domain\BlockPatchSchema;
use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Modules\X103\Domain\SiteThemes;
use App\Services\Industry\IndustryStartingPoints;
use App\Services\Industry\SiteStyle;
use Tests\TestCase;

class SiteThemesTest extends TestCase
{
    private const CONTEXT = [
        'businessName' => '',
        'deployHash' => 'theme',
        'tenant_storage_url_prefix' => '/m/',
        'form_action_base' => '/f',
    ];

    private function render(array $blocks, ?string $theme): string
    {
        $tokens = app(IndustryStartingPoints::class)->for(null);
        if ($theme !== null) {
            $tokens['theme'] = $theme;
        }

        return app(SiteBlockRenderer::class)->render($blocks, ['tokens' => $tokens] + self::CONTEXT);
    }

    public function test_every_theme_is_complete_readable_and_self_contained(): void
    {
        $this->assertCount(6, SiteThemes::THEMES);

        foreach (SiteThemes::THEMES as $id => $theme) {
            $this->assertSame(SiteStyle::PALETTE_KEYS, array_keys($theme['palette']), $id);
            $this->assertContains($theme['type_pairing']['heading'], SiteStyle::FONT_STACKS, $id);
            $this->assertContains($theme['type_pairing']['body'], SiteStyle::FONT_STACKS, $id);
            $this->assertContains($theme['hero'], BlockPatchSchema::HERO_VARIANTS, $id);
            $this->assertGreaterThanOrEqual(4.5, SiteStyle::contrast($theme['palette']['ink'], $theme['palette']['surface']), $id);
            $this->assertGreaterThanOrEqual(4.5, SiteStyle::contrast($theme['palette']['ink'], $theme['palette']['card']), $id);

            $css = SiteThemes::css($id);
            $this->assertGreaterThan(500, strlen($css), $id);
            foreach (['url(', '@import', 'http', '<'] as $needle) {
                $this->assertStringNotContainsString($needle, $css, "$id: $needle");
            }
        }
    }

    public function test_a_theme_adds_its_stylesheet_and_sets_the_top_banner_layout(): void
    {
        $hero = [['type' => 'hero', 'headline' => 'THEMED_HEADLINE_7601']];

        $plain = $this->render($hero, null);
        $this->assertSame(1, substr_count($plain, '<style>'));
        $this->assertStringContainsString('class="hero__grid"', $plain);
        $this->assertStringNotContainsString('.hero-center { max-width: 46rem; margin-inline: auto; }', $plain);

        $themed = $this->render($hero, 'clean-clinic');
        $this->assertSame(2, substr_count($themed, '<style>'));
        $this->assertStringContainsString('.hero-center { max-width: 46rem; margin-inline: auto; }', $themed);
        $this->assertStringContainsString('class="hero-center"', $themed);
        $this->assertStringContainsString('THEMED_HEADLINE_7601', $themed);
    }

    public function test_the_owners_own_banner_layout_beats_the_theme_and_an_unknown_theme_is_ignored(): void
    {
        $own = $this->render([['type' => 'hero', 'headline' => 'H', 'variant' => 'split']], 'clean-clinic');
        $this->assertStringContainsString('class="hero__grid"', $own);
        $this->assertStringNotContainsString('class="hero-center"', $own);

        $unknown = $this->render([['type' => 'hero', 'headline' => 'H']], '../../etc/passwd');
        $this->assertSame(1, substr_count($unknown, '<style>'));
        $this->assertSame('', SiteThemes::css('../../etc/passwd'));
    }

    public function test_every_theme_layout_is_a_real_layout_for_its_section_and_the_renderer_uses_it(): void
    {
        foreach (SiteThemes::THEMES as $id => $theme) {
            foreach ($theme['layouts'] as $type => $layout) {
                $this->assertContains($layout, BlockPatchSchema::VARIANTS[$type] ?? [], "$id: $type");
            }
        }

        $themed = $this->render([['type' => 'services', 'items' => [['name' => 'S1']]]], 'warm-local');
        $this->assertStringContainsString('class="site-block services services--list"', $themed);

        $own = $this->render([['type' => 'services', 'variant' => 'columns', 'items' => [['name' => 'S1']]]], 'warm-local');
        $this->assertStringContainsString('class="site-block services services--columns"', $own);
    }

    public function test_a_corner_style_adds_its_stylesheet_only_when_it_is_set(): void
    {
        $hero = [['type' => 'hero', 'headline' => 'CORNERS_8203']];
        $tokens = app(IndustryStartingPoints::class)->for(null);

        $round = app(SiteBlockRenderer::class)->render($hero, ['tokens' => ['corners' => 'round'] + $tokens] + self::CONTEXT);
        $this->assertStringContainsString('/* corners: round */', $round);
        $this->assertSame(2, substr_count($round, '<style>'));
        $this->assertStringContainsString('CORNERS_8203', $round);

        $plain = app(SiteBlockRenderer::class)->render($hero, ['tokens' => $tokens] + self::CONTEXT);
        $this->assertStringNotContainsString('/* corners:', $plain);
        $this->assertSame(1, substr_count($plain, '<style>'));

        $unknown = app(SiteBlockRenderer::class)->render($hero, ['tokens' => ['corners' => '../x'] + $tokens] + self::CONTEXT);
        $this->assertStringNotContainsString('/* corners:', $unknown);
        $this->assertSame('', SiteThemes::cornersCss('blob'));
        $this->assertSame('', SiteThemes::cornersCss(null));
    }

    public function test_the_corner_styles_agree_and_are_validated_like_colours(): void
    {
        $this->assertSame(SiteStyle::CORNERS, array_keys(SiteThemes::CORNERS));

        $base = ['palette' => SiteThemes::THEMES['bold-trade']['palette'], 'type_pairing' => SiteThemes::THEMES['bold-trade']['type_pairing']];
        $ok = SiteStyle::validate(['corners' => 'soft'], $base);
        $this->assertTrue($ok['ok']);
        $this->assertSame('soft', $ok['style']['corners']);

        $bad = SiteStyle::validate(['corners' => 'blob', 'palette' => ['primary' => '#123456']], $base);
        $this->assertArrayNotHasKey('corners', $bad['style'] ?? []);
    }
}
