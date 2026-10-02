<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Models\Business;
use App\Modules\X103\Models\Page;

/**
 * Pops the page's last undo entry back into its draft. Two entry shapes exist: {blocks, site_tokens}
 * (every write since the style editor — restores the business's colours too) and a bare block list
 * (older entries). Moved verbatim from Pages::undoEdit so the Studio and Pages share one implementation.
 */
final class PageUndoAction
{
    /**
     * @return array{status: string, reason: ?string}
     */
    public function handle(int $businessId, int $pageId): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);
        $meta = $page->draft_meta ?? [];
        $undo = $meta['undo'] ?? [];

        if (empty($undo)) {
            return ['status' => 'refused', 'reason' => 'Nothing to undo.'];
        }

        $entry = array_pop($undo);

        if (is_array($entry) && isset($entry['blocks']) && array_key_exists('site_tokens', $entry)) {
            $page->draft_blocks = $entry['blocks'];
            Business::whereKey($businessId)->update(['site_tokens' => $entry['site_tokens']]);
        } else {
            $page->draft_blocks = $entry;
        }

        $meta['undo'] = $undo;
        $page->draft_meta = $meta;
        $page->save();

        return ['status' => 'undone', 'reason' => null];
    }
}
