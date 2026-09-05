<?php

declare(strict_types=1);

namespace App\Modules\X176\Actions;

use App\Modules\X103\Models\Page;

final class SeoRenderAction
{
    public function handle(int $businessId, int $pageId, string $businessName, string $commitId, string $domainName): array
    {
        $page = Page::where('business_id', $businessId)->find($pageId);

        $slug = $page && $page->slug ? $page->slug : "pages/{$pageId}";
        $title = $page && $page->title ? $page->title : $businessName;
        $description = $businessName;

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => "https://{$domainName}/{$slug}",
        ];
    }
}
