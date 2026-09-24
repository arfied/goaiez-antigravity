<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

use Illuminate\Support\Facades\View;

final class SiteBlockRenderer
{
    public function render(array $contentBlocks, array $context): string
    {
        $html = '<style>
:root {
    --color-paper: #16191c;
    --color-canvas: #16191c;
    --color-card: #1d2125;
    --color-ink: #f2f2f0;
}
body { background: var(--color-canvas); color: var(--color-ink); font-family: sans-serif; }
.site-block { margin-bottom: 2rem; }
</style>
';

        foreach ($contentBlocks as $block) {
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

            try {
                $html .= View::make("x-103::site.blocks.{$type}", [
                    'block' => $block,
                    'context' => $context,
                ])->render();
            } catch (\Throwable $e) {
                // Ignore rendering errors
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
            return $this->hasScalar($block, 'label') && $this->hasScalar($block, 'url');
        }
        if ($type === 'contact') {
            return true;
        }
        if ($type === 'faq') {
            if (isset($block['items']) && is_array($block['items']) && count($block['items']) > 0) {
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
