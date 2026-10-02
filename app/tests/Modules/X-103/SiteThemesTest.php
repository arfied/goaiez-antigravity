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
}
