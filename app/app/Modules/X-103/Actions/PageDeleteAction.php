<?php

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use InvalidArgumentException;

class PageDeleteAction
{
    public function handle(int $businessId, int $pageId): void
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);

        if ($page->is_published || PageVersion::where('page_id', $pageId)->exists()) {
            throw new InvalidArgumentException('Unpublish this page first — it has been published.');
        }

        $page->delete();
    }
}
