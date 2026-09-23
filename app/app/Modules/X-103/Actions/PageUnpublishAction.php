<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Events\PageUnpublished;
use App\Modules\X103\Models\Page;
use InvalidArgumentException;

final class PageUnpublishAction
{
    public function handle(int $businessId, int $pageId): Page
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);

        if (! $page->is_published) {
            throw new InvalidArgumentException('That page is not published.');
        }

        $page->update(['is_published' => false]);

        event(new PageUnpublished($businessId, $page->id));

        return $page;
    }
}
