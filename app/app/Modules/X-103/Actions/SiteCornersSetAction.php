<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Models\Business;
use App\Modules\X103\Domain\SiteThemes;
use App\Modules\X103\Models\Page;
use Illuminate\Support\Facades\DB;

/**
 * Sets the site's corner style (square, soft, round) on top of its theme. The old look goes on the page's undo stack in the
 * same {blocks, site_tokens} shape as every Studio change, and the page is marked look_changed so Publish is offered.
 */
final class SiteCornersSetAction
{
    /**
     * @return array{status: string, reason?: string}
     */
    public function handle(int $businessId, int $pageId, string $corners): array
    {
        if (! isset(SiteThemes::CORNERS[$corners])) {
            return ['status' => 'refused', 'reason' => 'unknown_corners'];
        }

        return DB::transaction(function () use ($businessId, $pageId, $corners) {
            $page = Page::where('business_id', $businessId)->findOrFail($pageId);

            $previousSiteTokens = Business::whereKey($businessId)->value('site_tokens');
            if (is_string($previousSiteTokens)) {
                $previousSiteTokens = json_decode($previousSiteTokens, true);
            }

            $meta = $page->draft_meta ?? [];
            $meta['undo'] = $meta['undo'] ?? [];
            $meta['undo'][] = ['blocks' => $page->draft_blocks ?? [], 'site_tokens' => $previousSiteTokens];
            if (count($meta['undo']) > 20) {
                array_shift($meta['undo']);
            }
            $meta['look_changed'] = true;
            $page->draft_meta = $meta;
            $page->save();

            $tokens = is_array($previousSiteTokens) ? $previousSiteTokens : [];
            $tokens['corners'] = $corners;
            Business::whereKey($businessId)->update(['site_tokens' => json_encode($tokens, JSON_THROW_ON_ERROR)]);

            return ['status' => 'applied'];
        });
    }
}
