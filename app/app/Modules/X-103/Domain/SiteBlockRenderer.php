<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

use App\Services\Industry\SiteStyle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

final class SiteBlockRenderer
{
    public function render(array $contentBlocks, array $context): string
    {
        // A site template (SiteTemplates) owns the whole page: its own markup and stylesheet, never layered on this one.
        $template = SiteTemplates::get($context['tokens']['template'] ?? null);
        if ($template !== null) {
            return app(SiteTemplateRenderer::class)->render($template, $contentBlocks, $context);
        }

        $p = $context['tokens']['palette'] ?? [];
        $t = $context['tokens']['type_pairing'] ?? [];
        $surface = e($p['surface'] ?? '#16191c');
        $card = e($p['card'] ?? '#1d2125');
        $ink = e($p['ink'] ?? '#f2f2f0');
        $primary = e($p['primary'] ?? '#f2f2f0');
        $accent = e($p['accent'] ?? '#f2f2f0');
        $accentText = e(SiteStyle::readable((string) ($p['accent'] ?? '#f2f2f0'), [(string) ($p['surface'] ?? '#16191c'), (string) ($p['card'] ?? '#1d2125')], (string) ($p['ink'] ?? '#f2f2f0')));
        $onPrimary = e(SiteStyle::textOn((string) ($p['primary'] ?? '#f2f2f0'), [(string) ($p['surface'] ?? '#16191c'), (string) ($p['ink'] ?? '#f2f2f0'), '#ffffff', '#111111']));
        $fontHeading = e($t['heading'] ?? 'sans-serif');
        $fontBody = e($t['body'] ?? 'sans-serif');

        // The site's modern fonts (SiteFonts), inside the base stylesheet so the page carries no extra <style> element.
        $html = '<style>
'.SiteFonts::faceCss(is_array($t) ? $t : []).':root {
    --color-paper: '.$surface.';
    --color-canvas: '.$surface.';
    --color-card: '.$card.';
    --color-ink: '.$ink.';
    --color-primary: '.$primary.';
    --color-accent: '.$accent.';
    --color-accent-text: '.$accentText.';
    --color-on-primary: '.$onPrimary.';
    --font-heading: '.$fontHeading.';
    --font-body: '.$fontBody.';
    --color-band: color-mix(in srgb, var(--color-ink) 4%, var(--color-card));
}
*, *::before, *::after { box-sizing: border-box; }
html { -webkit-text-size-adjust: 100%; }
body { margin: 0 auto; max-width: 72rem; padding: 0 1.25rem 4rem; background: var(--color-canvas); color: var(--color-ink); font-family: var(--font-body); font-size: 1.0625rem; line-height: 1.6; }
h1, h2, h3 { font-family: var(--font-heading); line-height: 1.1; margin: 0 0 0.75rem; text-wrap: balance; letter-spacing: -0.015em; }
h1 { font-size: clamp(2.25rem, 5.5vw, 3.75rem); }
h2 { font-size: clamp(1.6rem, 3.4vw, 2.5rem); }
h3 { font-size: clamp(1.2rem, 2vw, 1.5rem); }
p { margin: 0 0 1rem; max-width: 65ch; }
img { max-width: 100%; height: auto; display: block; }
a { color: var(--color-accent-text); }
a:focus-visible, button:focus-visible, input:focus-visible, textarea:focus-visible, select:focus-visible { outline: 3px solid var(--color-accent); outline-offset: 3px; }
.site-block { margin: 0; padding: clamp(2.5rem, 6vw, 5rem) 0; }
.site-block--band { background: var(--color-band); margin-inline: calc(50% - 50vw); padding-inline: calc(50vw - 50%); }
.site-block__inner { max-width: 68rem; margin-inline: auto; }
.eyebrow { font-size: 0.72rem; letter-spacing: 0.09em; text-transform: uppercase; color: var(--color-accent-text); font-weight: 600; }
.lede { font-size: 1.2rem; opacity: 0.85; max-width: 60ch; }
.stack { display: grid; gap: 1rem; }
.actions { display: flex; flex-wrap: wrap; gap: 0.75rem; }
.site-cta { display: inline-block; padding: 0.85rem 1.5rem; border-radius: 999px; font-weight: 600; text-decoration: none; }
.site-cta--primary { background: var(--color-primary); color: var(--color-on-primary); }
.site-cta--ghost { background: transparent; border: 1px solid color-mix(in srgb, var(--color-ink) 25%, transparent); color: var(--color-ink); }
.site-cta--off { opacity: 0.55; cursor: not-allowed; }
.card { padding: 1.5rem; border-radius: 14px; background: var(--color-band); border: 1px solid color-mix(in srgb, var(--color-ink) 10%, transparent); box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 8px 24px rgba(0,0,0,0.06);
    @media (prefers-reduced-motion: no-preference) { &:hover { transform: translateY(-2px); box-shadow: 0 3px 6px rgba(0,0,0,0.06), 0 10px 30px rgba(0,0,0,0.08); } }
}
.grid { display: grid; gap: 1.25rem; }
.grid--2 { grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); }
.grid--3 { grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }
.hero__grid { display: grid; grid-template-columns: 1fr;
    @media (min-width: 52rem) { grid-template-columns: 1.1fr 1fr; gap: clamp(2rem, 5vw, 4rem); align-items: center;
        & > :only-child { grid-column: 1 / -1; }
    }
}
.hero__media { min-width: 0;
    & .media { aspect-ratio: 4 / 3; }
}
.media { overflow: hidden; border-radius: 14px; }
.media img { width: 100%; height: 100%; object-fit: cover; display: block; }
.media--wide { aspect-ratio: 16 / 9; }
.media--square { aspect-ratio: 1 / 1; }
.media--portrait { aspect-ratio: 3 / 4; }
.site-block ul { list-style: none; margin: 0; padding: 0; }
.site-block.hero { padding-top: clamp(3rem, 8vw, 6rem); }
.site-block.hero p { font-size: 1.25rem; opacity: 0.85; }
.site-block.hero > img { margin-top: 1.5rem; width: 100%; max-height: 32rem; object-fit: cover; border-radius: 12px; }
.site-block.services ul, .site-block.team ul { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }
.site-block.services li, .site-block.team li { display: grid; gap: 0.35rem; padding: 1.25rem; border-radius: 12px; background: var(--color-card); }
.site-block.services li span { color: var(--color-accent-text); font-weight: 600; }
.site-block.services li p { margin: 0; opacity: 0.85; }
.faq-item { padding: 1rem 0; border-top: 1px solid color-mix(in srgb, var(--color-ink) 12%, transparent); }
.faq-item h3 { margin: 0 0 0.35rem; }
.site-block.gallery { display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); }
.site-block.gallery h2 { grid-column: 1 / -1; }
.site-block.gallery > img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 10px; }
.review-list { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr)); }
.review-stars { color: var(--color-accent-text); letter-spacing: 0.12em; font-size: 1.05rem; line-height: 1; margin-bottom: 0.6rem; }
.review-list blockquote { margin: 0; padding: 1.25rem; border-radius: 12px; background: var(--color-card); }
.review-list cite { font-style: normal; font-size: 0.9rem; opacity: 0.8; }
.product-list { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr)); }
.product-list li { display: grid; align-content: start; gap: 0.35rem; }
.product-list img { width: 100%; aspect-ratio: 4 / 5; object-fit: cover; border-radius: 10px; margin-bottom: 0.4rem; }
.product-list p { margin: 0; opacity: 0.85; font-size: 0.95rem; }
.product-list span { font-weight: 700; }
.site-block.booking a { display: inline-block; padding: 0.85rem 1.5rem; border-radius: 999px; background: var(--color-primary); color: var(--color-on-primary); font-weight: 600; text-decoration: none; }
.site-block.booking a:focus-visible { outline: 3px solid var(--color-accent); outline-offset: 3px; }
.site-block.contact ul { display: grid; gap: 0.25rem; margin-top: 0.5rem; }
#faq-x176 > div { padding: 0.75rem 0; border-bottom: 1px solid color-mix(in srgb, var(--color-ink) 12%, transparent); }
.site-block form { display: grid; gap: 0.75rem; max-width: 32rem; }
.site-block input, .site-block textarea, .site-block select { font: inherit; padding: 0.65rem 0.8rem; border-radius: 8px; border: 1px solid color-mix(in srgb, var(--color-ink) 25%, transparent); background: var(--color-card); color: var(--color-ink); }
.site-block button { font: inherit; font-weight: 600; padding: 0.75rem 1.25rem; border: 0; border-radius: 999px; background: var(--color-primary); color: var(--color-on-primary); cursor: pointer; }
.site-header { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 0; border-bottom: 1px solid color-mix(in srgb, var(--color-ink) 12%, transparent); margin-bottom: 1rem; }
.site-header__name { margin: 0; font-family: var(--font-heading); font-weight: 700; font-size: 1.35rem; color: var(--color-ink); }
#internal-links-x176 { margin: 3rem 0 0; padding: 1.25rem 0; border-top: 1px solid color-mix(in srgb, var(--color-ink) 12%, transparent); }
#internal-links-x176 ul { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 0.5rem 1.5rem; }
#breadcrumb-x176 { font-size: 0.9rem; margin: 1.5rem 0 0; display: flex; gap: 0.75rem; }
.site-block-form { margin-top: 3rem; }
.site-footer { margin: 2rem 0 0; padding: 1.5rem 0; border-top: 1px solid color-mix(in srgb, var(--color-ink) 12%, transparent); font-size: 0.9rem; color: color-mix(in srgb, var(--color-ink) 70%, transparent); }
.hero-center { display: grid; justify-items: center; gap: 1.25rem; text-align: center; }
.hero-center p { margin-inline: auto; }
.hero-center img { width: 100%; height: 100%; object-fit: cover; }
.hero-cover { position: relative; overflow: hidden; border-radius: 18px; min-height: clamp(22rem, 60vh, 36rem); display: grid; align-items: end; padding: clamp(1.5rem, 5vw, 3.5rem); color: #fff; }
.hero-cover > img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.hero-cover::after { content: ""; position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.75), rgba(0,0,0,0.1)); }
.hero-cover > div { position: relative; z-index: 1; display: grid; gap: 1rem; max-width: 42rem; }
.hero-cover p { color: #fff; }
.site-block--primary { background: var(--color-primary); color: var(--color-on-primary); margin-inline: calc(50% - 50vw); padding-inline: calc(50vw - 50%); }
.cta-band { display: grid; justify-items: center; gap: 1rem; text-align: center; }
.cta-band p { margin-inline: auto; opacity: 0.9; }
.cta-band a { display: inline-block; padding: 0.9rem 1.75rem; border-radius: 999px; font-weight: 700; text-decoration: none; background: var(--color-on-primary); color: var(--color-primary); }
.stat-list { display: grid; gap: clamp(0.75rem, 3vw, 1.5rem); grid-template-columns: repeat(auto-fit, minmax(5.5rem, 1fr)); text-align: center; }
.stat-list strong { display: block; font-family: var(--font-heading); font-size: clamp(2.25rem, 4.5vw, 3.25rem); line-height: 1; color: var(--color-accent-text); }
.stat-list span { display: block; margin-top: 0.5rem; opacity: 0.8; }
.site-block.services.services--list ul { grid-template-columns: 1fr; gap: 0; }
.site-block.services.services--list li { grid-template-columns: minmax(0, 1fr) auto; column-gap: 1.5rem; align-items: baseline; padding: 1.1rem 0; background: transparent; border: 0; border-bottom: 1px solid color-mix(in srgb, var(--color-ink) 14%, transparent); border-radius: 0; box-shadow: none; text-align: left; }
.site-block.services.services--list li::before { display: none; }
.site-block.services.services--list li span { grid-column: 2; grid-row: 1; white-space: nowrap; }
.site-block.services.services--list li p { grid-column: 1; }
@media (min-width: 52rem) { .site-block.services.services--columns > div { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); gap: 3rem; align-items: start; } }
.site-block.reviews.reviews--quote .review-list { grid-template-columns: 1fr; max-width: 46rem; margin-inline: auto; text-align: center; }
.site-block.reviews.reviews--quote blockquote { background: transparent; border: 0; box-shadow: none; padding: 1.5rem 0; }
.site-block.reviews.reviews--quote blockquote::before { content: "\201C"; display: block; font-family: var(--font-heading); font-size: 4.5rem; line-height: 0.6; color: var(--color-accent-text); margin-bottom: 0.25rem; }
.site-block.reviews.reviews--quote blockquote p { font-family: var(--font-heading); font-size: clamp(1.3rem, 2.6vw, 1.75rem); line-height: 1.35; }
.site-block.reviews.reviews--row .review-list { grid-template-columns: none; grid-auto-flow: column; grid-auto-columns: minmax(16rem, 22rem); overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 0.5rem; }
.site-block.reviews.reviews--row blockquote { scroll-snap-align: start; }
.site-block.faq.faq--cards .faq-item { background: var(--color-card); border: 1px solid color-mix(in srgb, var(--color-ink) 10%, transparent); border-radius: 14px; padding: 1.1rem 1.4rem; margin-bottom: 0.75rem; }
@media (min-width: 46rem) { .site-block.faq.faq--columns > div { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 2.5rem; } }
.site-block.about.about--centered > div { max-width: 44rem; margin-inline: auto; text-align: center; }
.site-block.about.about--centered p { margin-inline: auto; }
@media (min-width: 52rem) { .site-block.about.about--split > div { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); gap: 3rem; align-items: start; } }
.site-block.about.about--split .about-media { grid-column: 1 / -1; }
@media (min-width: 52rem) { .site-block.about.about--split > div:has(.about-media) { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); grid-template-rows: 1fr auto auto 1fr; gap: 0 3rem; align-items: start; } .site-block.about.about--split > div:has(.about-media) > h2 { grid-column: 1; grid-row: 2; } .site-block.about.about--split > div:has(.about-media) > p { grid-column: 1; grid-row: 3; } .site-block.about.about--split > div:has(.about-media) .about-media { grid-column: 2; grid-row: 1 / -1; margin-top: 0; } }
.about-media { margin-top: 1.5rem; border-radius: 16px; overflow: hidden; max-width: 48rem; }
.about-media img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; display: block; }
@media (min-width: 40rem) { .site-block.contact.contact--columns > div { display: grid; grid-template-rows: auto auto; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); column-gap: 2rem; } }
.site-block.contact.contact--card > div { max-width: 52rem; margin-inline: auto; background: var(--color-card); border: 1px solid color-mix(in srgb, var(--color-ink) 10%, transparent); border-radius: 18px; padding: clamp(1.5rem, 4vw, 2.5rem); text-align: center; box-shadow: 0 12px 32px rgba(0,0,0,0.08); }
.site-block.contact a[href^="tel:"] { white-space: nowrap; }
.site-block.contact.contact--card a[href^="tel:"] { font-size: 1.6rem; font-weight: 700; text-decoration: none; }
.site-block.booking.booking--banner { text-align: center; }
.site-block.booking.booking--banner > div > div { justify-content: center; }
.site-block.stats.stats--cards li { background: var(--color-card); border-radius: 16px; padding: 1.5rem 1rem; box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
@media (min-width: 40rem) { .site-block.stats.stats--bar .stat-list { gap: 0; } .site-block.stats.stats--bar li + li { border-left: 1px solid color-mix(in srgb, var(--color-ink) 14%, transparent); } }
.site-block.booking.booking--card > div { max-width: 30rem; margin-inline: auto; background: var(--color-card); border: 1px solid color-mix(in srgb, var(--color-ink) 10%, transparent); border-radius: 18px; padding: clamp(1.5rem, 4vw, 2.25rem); text-align: center; box-shadow: 0 12px 32px rgba(0,0,0,0.08); }
.site-block.booking.booking--card > div > div { justify-content: center; }
.site-block.cta.cta--photo { position: relative; overflow: hidden; }
.cta-photo { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0.3; }
.site-block.cta.cta--photo > div { position: relative; }
</style>
';

        // A prebuilt theme (SiteThemes) styles the same markup: its stylesheet follows the base one.
        $themeId = $context['tokens']['theme'] ?? null;
        $theme = SiteThemes::get(is_string($themeId) ? $themeId : null);
        if ($theme !== null) {
            $html .= '<style>'.SiteThemes::css($theme['id']).'</style>'."\n";
        }
        // The owner's (or the AI's) corner style, on top of the theme.
        $corners = $context['tokens']['corners'] ?? null;
        $cornersCss = SiteThemes::cornersCss(is_string($corners) ? $corners : null);
        if ($cornersCss !== '') {
            $html .= '<style>'.$cornersCss.'</style>'."\n";
        }

        $renderedCount = 0;
        foreach ($contentBlocks as $blockIndex => $block) {
            $type = $block['type'] ?? '';
            if (! in_array($type, [
                'hero', 'about', 'services', 'reviews_strip',
                'booking_button', 'booking_form', 'contact', 'faq', 'video_embed',
                'gallery', 'team', 'form', 'cta_band', 'stats', 'products',
            ], true)) {
                continue;
            }

            // The theme picks each section's layout unless the owner (or the AI) chose one for this page.
            if ($theme !== null && ! isset($block['variant'])) {
                $themeLayout = $theme['layouts'][$type] ?? ($type === 'hero' ? $theme['hero'] : null);
                if (is_string($themeLayout)) {
                    $block['variant'] = $themeLayout;
                }
            }

            if (! $this->validateBlock($block)) {
                continue;
            }

            $band = '';
            if ($type !== 'hero') {
                $renderedCount++;
                if ($renderedCount % 2 === 0) {
                    $band = 'site-block--band';
                }
            }

            try {
                $html .= View::make("x-103::site.blocks.{$type}", [
                    'block' => $block,
                    'context' => $context,
                    'band' => $band,
                    'blockIndex' => $blockIndex,
                ])->render();
            } catch (\Throwable $e) {
                // The page still deploys without this block (owner's call whether it
                // should); the drop is no longer silent (wave 813).
                Log::warning('a site block failed to render and was left out of the page', [
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $html;
    }

    /**
     * How a video's address plays on a page: a YouTube address becomes YouTube's own player at its no-cookie address, a
     * Vimeo address Vimeo's player, a link to a video file (https) plays in the page, and any other http(s) address is a link
     * to the video. An empty or unsafe address gives nothing to play.
     *
     * @return array{kind: string, src: string}
     */
    public static function videoPlayer(mixed $url): array
    {
        $address = is_scalar($url) ? trim((string) $url) : '';
        if (preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:[^\#]*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])#i', $address, $m) === 1) {
            return ['kind' => 'embed', 'src' => 'https://www.youtube-nocookie.com/embed/'.$m[1]];
        }
        if (preg_match('#^https?://(?:www\.)?vimeo\.com/(\d+)(?:/([0-9a-f]{6,}))?(?![0-9])#i', $address, $m) === 1) {
            return ['kind' => 'embed', 'src' => 'https://player.vimeo.com/video/'.$m[1].(($m[2] ?? '') !== '' ? '?h='.$m[2] : '')];
        }
        if (preg_match('#^https://#i', $address) === 1 && preg_match('#\.(mp4|webm|ogg|mov)$#i', (string) parse_url($address, PHP_URL_PATH)) === 1) {
            return ['kind' => 'file', 'src' => $address];
        }
        if (preg_match('#^https?://[^\s]+$#i', $address) === 1) {
            return ['kind' => 'link', 'src' => $address];
        }

        return ['kind' => 'none', 'src' => ''];
    }

    public function isValidBlock(array $block): bool
    {
        return $this->validateBlock($block);
    }

    private function validateBlock(array $block): bool
    {
        $type = $block['type'];

        if ($type === 'hero') {
            return $this->hasScalar($block, 'headline');
        }
        if ($type === 'about') {
            return $this->hasScalar($block, 'text');
        }
        if ($type === 'services') {
            if (! isset($block['items']) || ! is_array($block['items'])) {
                return false;
            }

            return true;
        }
        if ($type === 'reviews_strip') {
            if (! isset($block['items']) || ! is_array($block['items'])) {
                return false;
            }

            return true;
        }
        if ($type === 'booking_button') {
            return $this->hasScalar($block, 'label');
        }
        if ($type === 'contact') {
            return true;
        }
        if ($type === 'faq') {
            if (isset($block['items']) && is_array($block['items']) && count($block['items']) > 0) {
                foreach ($block['items'] as $item) {
                    if (! is_array($item) || ! $this->hasScalar($item, 'question') || ! $this->hasScalar($item, 'answer')) {
                        return false;
                    }
                }

                return true;
            }

            return $this->hasScalar($block, 'question') && $this->hasScalar($block, 'answer');
        }
        if ($type === 'video_embed') {
            return $this->hasScalar($block, 'name') && $this->hasScalar($block, 'contentUrl') && $this->hasScalar($block, 'uploadDate');
        }
        if ($type === 'gallery') {
            return isset($block['items']) && is_array($block['items']);
        }
        if ($type === 'team') {
            return isset($block['items']) && is_array($block['items']);
        }
        if ($type === 'form') {
            return isset($block['fields']) && is_array($block['fields']);
        }
        if ($type === 'cta_band') {
            return $this->hasScalar($block, 'heading');
        }
        if ($type === 'stats') {
            if (! isset($block['items']) || ! is_array($block['items']) || $block['items'] === []) {
                return false;
            }
            foreach ($block['items'] as $item) {
                if (! is_array($item) || ! $this->hasScalar($item, 'value')) {
                    return false;
                }
            }

            return true;
        }
        if ($type === 'booking_form') {
            return $this->hasScalar($block, 'heading');
        }
        if ($type === 'products') {
            return isset($block['items']) && is_array($block['items']);
        }

        return false;
    }

    private function hasScalar(array $block, string $field): bool
    {
        return isset($block[$field]) && is_scalar($block[$field]) && trim((string) $block[$field]) !== '';
    }
}
