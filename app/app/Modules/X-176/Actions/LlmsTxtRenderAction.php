<?php

declare(strict_types=1);

namespace App\Modules\X176\Actions;

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
            ""
        ];

        foreach ($contentBlocks as $block) {
            if (isset($block['type']) && $block['type'] === 'text' && !empty($block['content'])) {
                $lines[] = $block['content'];
                $lines[] = "";
            } elseif (isset($block['text'])) {
                $lines[] = $block['text'];
                $lines[] = "";
            }
        }

        return trim(implode("\n", $lines)) . "\n";
    }
}
