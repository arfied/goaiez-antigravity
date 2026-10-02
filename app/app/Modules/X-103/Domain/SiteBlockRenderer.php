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

        $html = '<style>
:root {
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
.hero__media { min-width: 0; }
.media { overflow: hidden; border-radius: 14px; }
.media img { width: 100%; height: 100%; object-fit: cover; display: block; }
.media--wide { aspect-ratio: 16 / 9; }
.media--square { aspect-ratio: 1 / 1; }
.media--portrait { aspect-ratio: 3 / 4; }
.site-block ul { list-style: none; margin: 0; padding: 0; }
.site-block.hero { padding-top: clamp(3rem, 8vw, 6rem); }
.site-block.hero p { font-size: 1.25rem; opacity: 0.85; }
.site-block.hero > img { margin-top: 1.5rem; width: 100%; max-height: 32rem; object-fit: cover; border-radius: 12px; }
.site-block.services ul, .site-block.team ul { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); }
.site-block.services li, .site-block.team li { display: grid; gap: 0.35rem; padding: 1.25rem; border-radius: 12px; background: var(--color-card); }
.site-block.services li span { color: var(--color-accent-text); font-weight: 600; }
.site-block.services li p { margin: 0; opacity: 0.85; }
.faq-item { padding: 1rem 0; border-top: 1px solid color-mix(in srgb, var(--color-ink) 12%, transparent); }
.faq-item h3 { margin: 0 0 0.35rem; }
.site-block.gallery { display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); }
.site-block.gallery h2 { grid-column: 1 / -1; }
.site-block.gallery > img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 10px; }
.review-list { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr)); }
.review-list blockquote { margin: 0; padding: 1.25rem; border-radius: 12px; background: var(--color-card); }
.review-list cite { font-style: normal; font-size: 0.9rem; opacity: 0.8; }
.site-block.booking a { display: inline-block; padding: 0.85rem 1.5rem; border-radius: 999px; background: var(--color-primary); color: var(--color-on-primary); font-weight: 600; text-decoration: none; }
.site-block.booking a:focus-visible { outline: 3px solid var(--color-accent); outline-offset: 3px; }
.site-block.contact ul { display: grid; gap: 0.25rem; margin-top: 0.5rem; }
#faq-x176 > div { padding: 0.75rem 0; border-bottom: 1px solid color-mix(in srgb, var(--color-ink) 12%, transparent); }
.site-block form { display: grid; gap: 0.75rem; max-width: 32rem; }
.site-block input, .site-block textarea, .site-block select { font: inherit; padding: 0.65rem 0.8rem; border-radius: 8px; border: 1px solid color-mix(in srgb, var(--color-ink) 25%, transparent); background: var(--color-card); color: var(--color-ink); }
.site-block button { font: inherit; font-weight: 600; padding: 0.75rem 1.25rem; border: 0; border-radius: 999px; background: var(--color-primary); color: var(--color-on-primary); cursor: pointer; }
</style>
';

        $renderedCount = 0;
        foreach ($contentBlocks as $blockIndex => $block) {
            $type = $block['type'] ?? '';
            if (! in_array($type, [
                'hero', 'about', 'services', 'reviews_strip',
                'booking_button', 'booking_form', 'contact', 'faq', 'video_embed',
                'gallery', 'team', 'form',
            ], true)) {
                continue;
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
        if ($type === 'booking_form') {
            return $this->hasScalar($block, 'heading');
        }

        return false;
    }

    private function hasScalar(array $block, string $field): bool
    {
        return isset($block[$field]) && is_scalar($block[$field]) && trim((string) $block[$field]) !== '';
    }
}
