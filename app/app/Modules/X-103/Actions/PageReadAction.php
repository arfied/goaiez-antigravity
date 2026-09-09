<?php

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\Page;
use Illuminate\Database\Eloquent\Collection;

class PageReadAction
{
    public function publishedFor(int $businessId): Collection
    {
        return Page::where('business_id', $businessId)
            ->where('is_published', true)
            ->orderBy('slug', 'asc')
            ->get();
    }

    public function findForBusiness(int $businessId, int $pageId): ?Page
    {
        return Page::where('business_id', $businessId)->find($pageId);
    }
}
