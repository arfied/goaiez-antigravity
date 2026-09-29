<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\SiteAnsweredQuestion;

final class FaqPlaceAction
{
    public function handle(int $businessId, int $pageId): bool
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);
        $meta = $page->draft_meta ?? [];

        if (isset($meta['pending_faq'])) {
            $blocks = $page->draft_blocks ?? [];
            $blocks[] = [
                'type' => 'faq',
                'items' => $meta['pending_faq']['items'] ?? [],
                'source' => 'ai',
                'model' => $meta['pending_faq']['model'] ?? 'unknown',
            ];
            if (isset($meta['pending_faq']['source'])) {
                $src = $meta['pending_faq']['source'];
                SiteAnsweredQuestion::query()->updateOrCreate(
                    ['business_id' => $businessId, 'source_type' => $src['type'], 'source_id' => (int) $src['id']],
                    ['question' => $src['question'], 'page_id' => $page->id, 'answered_at' => now()]
                );
            }
            unset($meta['pending_faq']);
            $page->update([
                'draft_blocks' => $blocks,
                'draft_meta' => $meta,
            ]);

            return true;
        }

        return false;
    }
}
