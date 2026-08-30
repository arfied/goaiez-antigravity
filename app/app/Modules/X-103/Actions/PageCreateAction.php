<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\Page;

final class PageCreateAction
{
    public function handle(int $businessId, string $slug, string $title, bool $isTenantEdited = false): Page
    {
        return Page::create([
            'business_id' => $businessId,
            'slug' => $slug,
            'title' => $title,
            'is_tenant_edited' => $isTenantEdited,
            'is_published' => false,
        ]);
    }
}
