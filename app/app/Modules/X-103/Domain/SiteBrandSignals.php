<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

use DOMDocument;

/**
 * The brand a business's current website already has — its colours and fonts — read from the home page and up to two of
 * its own stylesheets during the crawl, so the AI designer can keep the new site recognisably theirs (the boss, 2026-10-02:
 * "extract the brand colours and fonts from the client's old site").
 *
 * Only real colours are kept: greys, white and black are left out, because every site has them and they say nothing about
 * the brand. Icon fonts and generic families (serif, sans-serif, system-ui…) are left out for the same reason. Nothing here
 * is loaded on the new site: our sites use their own font stacks, so the font names only tell the designer what to resemble.
 */
final class SiteBrandSignals
{
    public const MAX_COLOURS = 4;

    public const MAX_FONTS = 3;

    /** A colour whose strongest and weakest channels are closer than this is a grey (or white or black). */
    private const MIN_CHROMA = 40;

    private const GENERIC_FAMILIES = ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-sans-serif', 'ui-serif', 'ui-monospace', 'ui-rounded', '-apple-system', 'blinkmacsystemfont', 'inherit', 'initial', 'unset', 'revert', 'emoji', 'math'];

    private const ICON_FONT_WORDS = ['awesome', 'icon', 'dashicons', 'glyph', 'eicons', 'material symbols'];

    /**
     * @param  list<string>  $stylesheets  the text of the page's own stylesheets
     * @return array{theme_color?: string, colours?: list<string>, fonts?: list<string>} empty when nothing was found
     */
    public static function read(string $html, array $stylesheets = []): array
    {
        $dom = new DOMDocument;
        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors($internalErrors);

        $themeColor = null;
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (strtolower(trim($meta->getAttribute('name'))) === 'theme-color') {
                $themeColor = self::normalise($meta->getAttribute('content'));
                break;
            }
        }

        $css = $stylesheets;
        foreach ($dom->getElementsByTagName('style') as $style) {
            $css[] = $style->textContent;
        }
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//*[@style]') ?: [] as $node) {
            if ($node instanceof \DOMElement) {
                $css[] = $node->getAttribute('style');
            }
        }
        $css = implode("\n", $css);

        $counts = [];
        if (preg_match_all('/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b/', $css, $m)) {
            foreach ($m[0] as $hex) {
                $colour = self::normalise($hex);
                if ($colour !== null) {
                    $counts[$colour] = ($counts[$colour] ?? 0) + 1;
                }
            }
        }
        arsort($counts);
        $colours = array_keys($counts);
        if ($themeColor !== null) {
            $colours = array_values(array_unique([$themeColor, ...$colours]));
        }
        $colours = array_slice($colours, 0, self::MAX_COLOURS);

        // Google Fonts links name the brand's fonts outright; then the families the stylesheets ask for, most used first.
        $fonts = [];
        foreach ($dom->getElementsByTagName('link') as $link) {
            $href = html_entity_decode($link->getAttribute('href'));
            if (! str_contains($href, 'fonts.googleapis.com')) {
                continue;
            }
            $query = (string) parse_url($href, PHP_URL_QUERY);
            foreach (explode('&', $query) as $pair) {
                if (! str_starts_with($pair, 'family=')) {
                    continue;
                }
                foreach (explode('|', urldecode(str_replace('+', ' ', substr($pair, 7)))) as $family) {
                    $fonts[] = trim(explode(':', $family)[0]);
                }
            }
        }
        $familyCounts = [];
        if (preg_match_all('/font-family\s*:\s*([^;}{]+)/i', $css, $m)) {
            foreach ($m[1] as $declaration) {
                $first = trim(explode(',', $declaration)[0], " \t\n\r\"'");
                $familyCounts[$first] = ($familyCounts[$first] ?? 0) + 1;
            }
        }
        arsort($familyCounts);
        $fonts = array_merge($fonts, array_map('strval', array_keys($familyCounts)));
        $fonts = array_values(array_unique(array_filter($fonts, [self::class, 'isBrandFont'])));
        $fonts = array_slice($fonts, 0, self::MAX_FONTS);

        $brand = [];
        if ($themeColor !== null) {
            $brand['theme_color'] = $themeColor;
        }
        if ($colours !== []) {
            $brand['colours'] = $colours;
        }
        if ($fonts !== []) {
            $brand['fonts'] = $fonts;
        }

        return $brand;
    }

    /** '#ABC' or '#aabbcc' as '#aabbcc', or null for anything else and for greys, white and black. */
    private static function normalise(string $hex): ?string
    {
        $hex = strtolower(trim($hex));
        if (preg_match('/^#[0-9a-f]{3}$/', $hex)) {
            $hex = '#'.$hex[1].$hex[1].$hex[2].$hex[2].$hex[3].$hex[3];
        }
        if (! preg_match('/^#[0-9a-f]{6}$/', $hex)) {
            return null;
        }
        $rgb = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];

        return max($rgb) - min($rgb) < self::MIN_CHROMA ? null : $hex;
    }

    private static function isBrandFont(string $family): bool
    {
        $lower = strtolower($family);
        if ($lower === '' || str_starts_with($lower, 'var(') || mb_strlen($family) > 60 || in_array($lower, self::GENERIC_FAMILIES, true)) {
            return false;
        }
        foreach (self::ICON_FONT_WORDS as $word) {
            if (str_contains($lower, $word)) {
                return false;
            }
        }

        return true;
    }
}
