<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Domain\SiteDesignEngines;
use App\Modules\X103\Domain\SiteThemes;
use App\Modules\X103\Models\Page;

/**
 * Turns one AI's design into the page's proposal: its sections, its theme, and the theme's colours and fonts with the
 * AI's own adjustments on top. Nothing changes until the owner presses Apply (SiteEditApplyAction), and Undo brings the
 * old page and look back — the same loop as every other AI change.
 */
final class SiteDesignUseAction
{
    /**
     * @return array{status: string, reason?: string}
     */
    public function handle(int $businessId, int $pageId, string $engine): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);
        $meta = $page->draft_meta ?? [];

        if (isset($meta['pending_edit'])) {
            return ['status' => 'refused', 'reason' => 'pending_edit'];
        }
        $design = $meta['designs'][$engine] ?? null;
        if (! is_array($design) || ($design['status'] ?? null) !== 'ready' || ! is_array($design['blocks'] ?? null) || $design['blocks'] === []) {
            return ['status' => 'refused', 'reason' => 'not_ready'];
        }

        $theme = is_string($design['theme'] ?? null) ? SiteThemes::get($design['theme']) : null;
        $style = is_array($design['style'] ?? null) ? $design['style'] : [];
        if ($theme !== null) {
            $style = array_replace_recursive(['palette' => $theme['palette'], 'type_pairing' => $theme['type_pairing']], $style);
        }

        $now = now()->toIso8601String();
        $request = 'Design by '.SiteDesignEngines::label($engine);
        $explanation = (string) ($design['explanation'] ?? '');
        $pending = [
            'request' => $request,
            'blocks' => $design['blocks'],
            'explanation' => $explanation,
            'model' => (string) ($design['model'] ?? ''),
            'drafted_at' => $now,
            'thread' => [['request' => $request, 'explanation' => $explanation, 'at' => $now]],
        ];
        if ($style !== []) {
            $pending['style'] = $style;
        }
        if ($theme !== null) {
            $pending['theme'] = $theme['id'];
        }

        $meta['pending_edit'] = $pending;
        $page->draft_meta = $meta;
        $page->save();

        return ['status' => 'proposed'];
    }
}
