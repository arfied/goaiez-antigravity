<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\Page;

final class FaqDiscardAction
{
    public function handle(int $businessId, int $pageId): bool
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);
        $meta = $page->draft_meta ?? [];
        if (isset($meta['pending_faq'])) {
            unset($meta['pending_faq']);
            $page->update(['draft_meta' => $meta]);

            return true;
        }

        return false;
    }
}
