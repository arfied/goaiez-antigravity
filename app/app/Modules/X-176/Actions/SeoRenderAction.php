<?php

declare(strict_types=1);

namespace App\Modules\X176\Actions;

use App\Modules\X103\Actions\PageReadAction;

final class SeoRenderAction
{
    public function handle(int $businessId, int $pageId, string $businessName, string $commitId, string $domainName): array
    {
        $page = app(PageReadAction::class)->findForBusiness($businessId, $pageId);

        $slug = $page && $page->slug ? $page->slug : "pages/{$pageId}";
        $title = $page && $page->seo_title ? $page->seo_title : ($page && $page->title ? $page->title : $businessName);
        $description = $page && $page->seo_description ? $page->seo_description : $businessName;

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => "https://{$domainName}/{$slug}",
        ];
    }
}
