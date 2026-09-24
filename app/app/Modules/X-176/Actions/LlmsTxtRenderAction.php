<?php

declare(strict_types=1);

namespace App\Modules\X176\Actions;

use App\Services\Facts\BusinessFactKey;

final class LlmsTxtRenderAction
{
    /**
     * (R245) Render llms.txt content from page blocks.
     */
    public function handle(string $businessName, string $title, string $slug, array $contentBlocks): string
    {
        $lines = [
            "# {$businessName}",
            "## {$title}",
            "Path: /{$slug}",
            '',
        ];

        foreach ($contentBlocks as $block) {
            $type = $block['type'] ?? '';
            $added = false;

            if ($type === 'hero') {
                if (is_scalar($block['headline'] ?? '') && trim((string) ($block['headline'] ?? '')) !== '') {
                    $lines[] = $block['headline'];
                    $added = true;
                }
                if (is_scalar($block['subline'] ?? '') && trim((string) ($block['subline'] ?? '')) !== '') {
                    $lines[] = $block['subline'];
                    $added = true;
                }
            } elseif ($type === 'services') {
                $items = isset($block['items']) && is_array($block['items']) ? $block['items'] : [];
                foreach ($items as $item) {
                    if (! is_array($item) || ! is_scalar($item['name'] ?? '') || trim((string) ($item['name'] ?? '')) === '') {
                        continue;
                    }
                    $name = trim((string) $item['name']);
                    $price = isset($item['price_text']) && is_scalar($item['price_text']) ? trim((string) $item['price_text']) : '';
                    if ($price !== '') {
                        $lines[] = "- {$name} — {$price}";
                    } else {
                        $lines[] = "- {$name}";
                    }
                    $added = true;
                }
            } elseif ($type === 'faq') {
                $pairs = isset($block['items']) && is_array($block['items']) ? $block['items'] : [$block];
                foreach ($pairs as $pair) {
                    if (! is_array($pair) || ! is_scalar($pair['question'] ?? '') || ! is_scalar($pair['answer'] ?? '')
                        || trim((string) ($pair['question'] ?? '')) === '' || trim((string) ($pair['answer'] ?? '')) === '') {
                        continue;
                    }
                    $lines[] = 'Q: '.$pair['question'];
                    $lines[] = 'A: '.$pair['answer'];
                    $added = true;
                }
            } elseif ($type === 'contact') {
                $scalars = [
                    'address' => 'Address',
                    'phone' => 'Phone',
                    'email' => 'Email',
                    'hours' => 'Hours',
                ];
                foreach ($scalars as $k => $label) {
                    if (isset($block[$k]) && is_scalar($block[$k]) && trim((string) ($block[$k] ?? '')) !== '') {
                        $lines[] = "{$label}: {$block[$k]}";
                        $added = true;
                    }
                }
                if (isset($block['facts']) && is_array($block['facts'])) {
                    $factLabels = BusinessFactKey::all();
                    foreach ($block['facts'] as $k => $v) {
                        if (is_scalar($v) && trim((string) $v) !== '') {
                            $label = $factLabels[$k]['label'] ?? $k;
                            $lines[] = "{$label}: {$v}";
                            $added = true;
                        }
                    }
                }
                if (isset($block['industry_facts']) && is_array($block['industry_facts'])) {
                    foreach ($block['industry_facts'] as $f) {
                        if (is_array($f) && isset($f['label'], $f['value']) && is_scalar($f['value']) && trim((string) $f['value']) !== '') {
                            $lines[] = "{$f['label']}: {$f['value']}";
                            $added = true;
                        }
                    }
                }
            }

            if ($added) {
                $lines[] = '';

                continue;
            }

            if (isset($block['type']) && $block['type'] === 'text' && is_scalar($block['content'] ?? '') && trim((string) ($block['content'] ?? '')) !== '') {
                $lines[] = $block['content'];
                $lines[] = '';
            } elseif (is_scalar($block['text'] ?? '') && trim((string) ($block['text'] ?? '')) !== '') {
                $lines[] = $block['text'];
                $lines[] = '';
            }
        }

        return trim(implode("\n", $lines))."\n";
    }
}
