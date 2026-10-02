<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Models\Location;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\SiteInventoryPage;
use App\Services\Config\DefaultsRegistry;
use App\Services\Facts\BusinessFactKey;
use App\Services\Facts\BusinessFacts;
use Throwable;

final class SiteBuildRunAction
{
    public function __construct(
        private readonly SiteCrawlAction $crawl,
        private readonly SiteImagesCopyAction $images,
        private readonly SiteDraftAction $draft,
        private readonly DefaultsRegistry $registry,
        private readonly SiteCopyPolishAction $polish,
        private readonly SiteImageGenerateAction $picture,
        private readonly BusinessFacts $facts
    ) {}

    /**
     * @return array{status: string, reason?: string, crawl?: array, images?: array, draft?: array, polish?: array|null, picture?: array|null}
     */
    public function handle(int $businessId, int $locationId): array
    {
        $hours = $this->registry->int('sites.build.recrawl_after_hours');
        $cutoff = now()->subHours($hours);

        $hasRecentInventory = SiteInventoryPage::where('business_id', $businessId)
            ->where('location_id', $locationId)
            ->where('status', 'fetched')
            ->where('fetched_at', '>=', $cutoff)
            ->exists();

        $crawlResult = null;
        $draftedWithoutCrawl = false;
        if (! $hasRecentInventory) {
            $crawlResult = $this->crawl->handle($businessId, $locationId);
            if (isset($crawlResult['status']) && $crawlResult['status'] === 'refused') {
                $reason = $crawlResult['reason'] ?? 'refused';
                if ($reason === 'no_website' && Location::where('id', $locationId)->exists()) {
                    $draftedWithoutCrawl = true;
                } else {
                    return [
                        'status' => 'refused',
                        'reason' => $reason,
                        'crawl' => $crawlResult,
                    ];
                }
            }
        } else {
            $crawlResult = [
                'status' => 'reused',
                'pages' => SiteInventoryPage::where('business_id', $businessId)->where('location_id', $locationId)->where('status', 'fetched')->count(),
                'refused' => 0,
            ];
        }

        $imagesResult = $this->images->handle($businessId, $locationId);
        $draftResult = $this->draft->handle($businessId, $locationId);

        // A freshly drafted home page's crawled words are rewritten once by the AI (plain text, the owner's facts only).
        // Words the owner typed are never touched, and a polish that cannot run never fails the build.
        $polishResult = null;
        $home = null;
        if (! in_array('home', $draftResult['skipped'] ?? [], true)) {
            $home = Page::where('business_id', $businessId)->where('slug', 'home')->first();
            if ($home !== null) {
                try {
                    $polishResult = $this->polish->handle($businessId, (int) $home->id, crawledOnly: true);
                } catch (Throwable) {
                    $polishResult = ['status' => 'refused', 'reason' => 'polish_failed'];
                }
            }
        }

        // No picture on the fresh hero (nothing usable was crawled): make one from the owner's own description of the
        // business — their tagline, else the hero subline. Never over an existing image; a failure never fails the build.
        $pictureResult = null;
        if ($home !== null) {
            $home->refresh();
            $blocks = $home->draft_blocks ?? [];
            foreach ($blocks as $i => $block) {
                if (! is_array($block) || ($block['type'] ?? '') !== 'hero') {
                    continue;
                }
                $description = trim((string) ($this->facts->get($businessId, BusinessFactKey::TAGLINE) ?? ($block['subline'] ?? '')));
                if (! empty($block['image_path']) || $description === '') {
                    break;
                }
                try {
                    $pictureResult = $this->picture->handle($businessId, $description);
                } catch (Throwable) {
                    $pictureResult = ['status' => 'refused', 'reason' => 'picture_failed'];
                }
                if (($pictureResult['status'] ?? null) === 'generated') {
                    $blocks[$i]['image_path'] = $pictureResult['path'];
                    $blocks[$i]['image_alt'] = mb_substr($description, 0, 120);
                    $home->draft_blocks = $blocks;
                    $home->save();
                }
                break;
            }
        }

        $result = [
            'status' => 'completed',
            'crawl' => $crawlResult,
            'images' => $imagesResult,
            'draft' => $draftResult,
            'polish' => $polishResult,
            'picture' => $pictureResult,
        ];
        if ($draftedWithoutCrawl) {
            $result['drafted_without_crawl'] = true;
        }

        return $result;
    }
}
