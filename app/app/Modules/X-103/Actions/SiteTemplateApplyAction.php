<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Models\Business;
use App\Modules\X103\Domain\SiteTemplates;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\SiteInventoryPage;
use App\Services\Industry\SiteStyle;
use Illuminate\Support\Facades\DB;

/**
 * Puts a site template on the business's site (SiteTemplates). The template's colours and fonts become the starting point,
 * and the template replaces any theme: the two are different looks over the same sections, never layered. The old look goes
 * on the page's undo stack in the same {blocks, site_tokens} shape as every other Studio change, and the page is marked
 * look_changed so the Studio offers Publish even though no section changed. Applying a theme afterwards (SiteThemeApplyAction)
 * writes site_tokens whole, so it takes the template off again.
 *
 * The business's own brand colour, read from its old website by the crawl (SiteBrandSignals), becomes the template's accent
 * (the boss's brief: "One accent color from the scrape logo, written into the CSS variables at render. Everything else stays on
 * the theme."). A brand colour is used only when the template stays readable with it — on its own surfaces, in the faint accent
 * tints some templates lay under text, and under button text where a template blends the accent into its primary colour;
 * otherwise the next brand colour is tried, and with none that fits the template keeps its own accent.
 */
final class SiteTemplateApplyAction
{
    /**
     * @return array{status: string, reason?: string, template?: string}
     */
    public function handle(int $businessId, int $pageId, string $templateId): array
    {
        $template = SiteTemplates::get($templateId);
        if ($template === null) {
            return ['status' => 'refused', 'reason' => 'unknown_template'];
        }

        return DB::transaction(function () use ($businessId, $pageId, $template) {
            $page = Page::where('business_id', $businessId)->findOrFail($pageId);

            $previousSiteTokens = Business::whereKey($businessId)->value('site_tokens');
            if (is_string($previousSiteTokens)) {
                $previousSiteTokens = json_decode($previousSiteTokens, true);
            }

            $meta = $page->draft_meta ?? [];
            $meta['undo'] = $meta['undo'] ?? [];
            $meta['undo'][] = ['blocks' => $page->draft_blocks ?? [], 'site_tokens' => $previousSiteTokens];
            if (count($meta['undo']) > 20) {
                array_shift($meta['undo']);
            }
            $meta['look_changed'] = true;
            $page->draft_meta = $meta;
            $page->save();

            $palette = $template['palette'];
            $brandAccent = self::brandAccent($businessId, $palette, SiteTemplates::css($template['id']));
            if ($brandAccent !== null) {
                $palette['accent'] = $brandAccent;
            }

            Business::whereKey($businessId)->update(['site_tokens' => json_encode([
                'template' => $template['id'],
                'palette' => $palette,
                'type_pairing' => $template['type_pairing'],
            ], JSON_THROW_ON_ERROR)]);

            return ['status' => 'applied', 'template' => $template['id']];
        });
    }

    /**
     * The first brand colour from the business's old website that keeps this template readable, or null.
     *
     * The extra checks run only where the template's own stylesheet needs them: a faint accent tint under text, and the accent
     * blended into the primary colour under button text.
     *
     * @param  array<string, string>  $palette
     */
    private static function brandAccent(int $businessId, array $palette, string $css): ?string
    {
        $brand = SiteInventoryPage::where('business_id', $businessId)->whereNotNull('brand')->orderBy('id')->first()?->brand;
        if (! is_array($brand)) {
            return null;
        }
        $candidates = array_merge(is_string($brand['theme_color'] ?? null) ? [$brand['theme_color']] : [], is_array($brand['colours'] ?? null) ? $brand['colours'] : []);
        $tinted = str_contains($css, 'color-mix(in srgb, var(--color-accent)');
        $blended = str_contains($css, 'var(--color-accent) 45%');
        $onPrimary = SiteStyle::textOn($palette['primary'], [$palette['surface'], $palette['ink'], '#ffffff', '#111111']);
        foreach ($candidates as $colour) {
            if (! is_string($colour) || preg_match('/^#[0-9a-f]{6}$/i', $colour) !== 1) {
                continue;
            }
            $colour = strtolower($colour);
            $accentText = SiteStyle::readable($colour, [$palette['surface'], $palette['card']], $palette['ink']);
            $tint = self::mix($colour, $palette['surface'], 0.15);
            $blend = self::mix($colour, $palette['primary'], 0.45);
            if (SiteStyle::contrast($accentText, $palette['surface']) >= 4.5 && SiteStyle::contrast($accentText, $palette['card']) >= 4.5
                && (! $tinted || (SiteStyle::contrast($palette['ink'], $tint) >= 4.5 && SiteStyle::contrast($accentText, $tint) >= 4.5))
                && (! $blended || SiteStyle::contrast($onPrimary, $blend) >= 4.5)) {
                return $colour;
            }
        }

        return null;
    }

    /** $a mixed into $b: $share of $a, the rest $b (both #rrggbb). */
    private static function mix(string $a, string $b, float $share): string
    {
        $out = '#';
        foreach ([1, 3, 5] as $i) {
            $out .= sprintf('%02x', (int) round(hexdec(substr($a, $i, 2)) * $share + hexdec(substr($b, $i, 2)) * (1 - $share)));
        }

        return $out;
    }
}
