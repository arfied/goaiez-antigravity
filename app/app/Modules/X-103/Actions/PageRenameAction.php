<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\Page;
use InvalidArgumentException;

final class PageRenameAction
{
    public function handle(int $businessId, int $pageId, string $slug, string $title): Page
    {
        $slug = trim($slug);
        $title = trim($title);

        if ($slug === '' || $title === '') {
            throw new InvalidArgumentException('Slug and title are required.');
        }

        if (Page::where('business_id', $businessId)->where('slug', $slug)->where('id', '!=', $pageId)->exists()) {
            throw new InvalidArgumentException('Another page already uses that address.');
        }

        $page = Page::where('business_id', $businessId)->findOrFail($pageId);
        $page->update(['slug' => $slug, 'title' => $title]);

        return $page;
    }
}
