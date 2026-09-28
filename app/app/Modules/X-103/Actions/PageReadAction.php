<?php

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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

    /** The tenant's Home page (slug `home`), draft or published, or null before the first draft. */
    public function homeFor(int $businessId): ?Page
    {
        return Page::where('business_id', $businessId)->where('slug', 'home')->first();
    }

    public function publishedForSlugs(int $businessId, array $normalisedSlugs): Collection
    {
        return Page::where('business_id', $businessId)
            ->where('is_published', true)
            ->whereIn(DB::raw("trim(both '/' from slug)"), $normalisedSlugs)
            ->get();
    }

    public function allFor(int $businessId): Collection
    {
        return Page::where('business_id', $businessId)
            ->orderBy('title')
            ->get();
    }
}
