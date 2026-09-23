<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;

class PageDuplicateAction
{
    public function handle(int $businessId, int $pageId): Page
    {
        $sourcePage = Page::where('business_id', $businessId)->findOrFail($pageId);

        $title = 'Copy of '.$sourcePage->title;
        $baseSlug = $sourcePage->slug.'-copy';
        $slug = $baseSlug;
        $counter = 2;
        while (Page::where('business_id', $businessId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        $draftBlocks = $sourcePage->draft_blocks;
        if ($draftBlocks === null && $sourcePage->current_version_id !== null) {
            $version = PageVersion::where('business_id', $businessId)->find($sourcePage->current_version_id);
            if ($version) {
                $draftBlocks = collect($version->content_blocks)
                    ->filter(fn ($block) => in_array($block['type'] ?? '', ['faq', 'video_embed'], true))
                    ->values()
                    ->toArray();
            } else {
                $draftBlocks = null;
            }
        }

        $duplicatePage = Page::create([
            'business_id' => $businessId,
            'title' => $title,
            'slug' => $slug,
            'draft_blocks' => $draftBlocks,
            'is_published' => false,
            'current_version_id' => null,
            'is_tenant_edited' => $sourcePage->is_tenant_edited,
        ]);

        return $duplicatePage;
    }
}
