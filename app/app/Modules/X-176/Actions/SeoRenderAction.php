<?php

declare(strict_types=1);

namespace App\Modules\X176\Actions;

final class SeoRenderAction
{
    public function handle(int $businessId, int $pageId, string $businessName, string $commitId): array
    {
        return [
            'title' => "{$businessName} | Page {$pageId}",
            'description' => "Welcome to {$businessName}.",
            'canonical' => "https://example.com/pages/{$pageId}",
        ];
    }
}
