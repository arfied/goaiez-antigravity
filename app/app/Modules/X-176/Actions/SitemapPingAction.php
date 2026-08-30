<?php

declare(strict_types=1);

namespace App\Modules\X176\Actions;

final class SitemapPingAction
{
    public function handle(int $businessId, string $sitemapUrl): array
    {
        return [
            'status' => 'pinged',
            'sitemap_url' => $sitemapUrl,
            'engines' => ['google', 'bing'],
        ];
    }
}
