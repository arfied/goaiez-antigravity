<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\SiteInventoryPage;
use App\Services\Config\DefaultsRegistry;

final class SiteBuildRunAction
{
    public function __construct(
        private readonly SiteCrawlAction $crawl,
        private readonly SiteImagesCopyAction $images,
        private readonly SiteDraftAction $draft,
        private readonly DefaultsRegistry $registry
    ) {}

    /**
     * @return array{status: string, reason?: string, crawl?: array, images?: array, draft?: array}
     */
    public function handle(int $businessId, int $locationId): array
    {
        $hours = $this->registry->int('sites.build.recrawl_after_hours');
        $cutoff = now()->subHours($hours);

        $hasRecentInventory = SiteInventoryPage::where('business_id', $businessId)
            ->where('location_id', $locationId)
            ->where('fetched_at', '>=', $cutoff)
            ->exists();

        $crawlResult = null;
        if (! $hasRecentInventory) {
            $crawlResult = $this->crawl->handle($businessId, $locationId);
            if (isset($crawlResult['status']) && $crawlResult['status'] === 'refused') {
                return [
                    'status' => 'refused',
                    'reason' => $crawlResult['reason'] ?? 'refused',
                    'crawl' => $crawlResult,
                ];
            }
        } else {
            $crawlResult = [
                'status' => 'reused',
                'pages' => SiteInventoryPage::where('business_id', $businessId)->where('location_id', $locationId)->count(),
                'refused' => 0,
            ];
        }

        $imagesResult = $this->images->handle($businessId, $locationId);
        $draftResult = $this->draft->handle($businessId, $locationId);

        return [
            'status' => 'completed',
            'crawl' => $crawlResult,
            'images' => $imagesResult,
            'draft' => $draftResult,
        ];
    }
}
