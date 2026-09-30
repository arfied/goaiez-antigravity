<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\Page;

final class SiteEditDiscardAction
{
    public function handle(int $businessId, int $pageId): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);
        $meta = $page->draft_meta ?? [];
        if (isset($meta['pending_edit'])) {
            unset($meta['pending_edit']);
            $page->update(['draft_meta' => $meta]);
        }

        return [
            'status' => 'discarded',
        ];
    }
}
