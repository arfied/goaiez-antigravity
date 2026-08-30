<?php

declare(strict_types=1);

namespace App\Modules\X151\Actions;

final class SitemapScanAction
{
    public function handle(int $businessId, string $domain, string $sitemapUrl): array
    {
        return [
            'domain' => $domain,
            'urls_discovered' => [
                "https://{$domain}/",
                "https://{$domain}/services",
                "https://{$domain}/about",
            ],
        ];
    }
}
