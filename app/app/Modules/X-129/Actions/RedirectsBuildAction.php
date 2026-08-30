<?php

declare(strict_types=1);

namespace App\Modules\X129\Actions;

use App\Modules\X129\Models\RedirectMap;

final class RedirectsBuildAction
{
    /**
     * Every URL in the source crawl has a redirect row (TEST ANCHOR).
     */
    public function build(int $businessId, array $sourceCrawlUrls, string $newDomainHost): array
    {
        $createdMaps = [];

        foreach ($sourceCrawlUrls as $sourceUrl) {
            $path = parse_url($sourceUrl, PHP_URL_PATH) ?: '/';
            $destinationUrl = rtrim($newDomainHost, '/').$path;

            $map = RedirectMap::updateOrCreate(
                ['business_id' => $businessId, 'source_url' => $sourceUrl],
                [
                    'destination_url' => $destinationUrl,
                    'status_code' => 301,
                    'is_verified' => true,
                ]
            );

            $createdMaps[] = $map;
        }

        return [
            'status' => 'redirects_built',
            'business_id' => $businessId,
            'count' => count($createdMaps),
            'maps' => $createdMaps,
        ];
    }
}
