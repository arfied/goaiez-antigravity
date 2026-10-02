<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Models\Business;
use App\Modules\X103\Domain\SiteEngine;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X157\Actions\PlatformSiteAddressAction;
use Illuminate\Support\Facades\DB;

final class PageRestoreVersionAction
{
    public function __construct(
        private readonly SiteEngine $engine,
        private readonly PlatformSiteAddressAction $addressAction
    ) {}

    public function handle(int $businessId, int $pageId, int $versionId): array
    {
        return DB::transaction(function () use ($businessId, $pageId, $versionId) {
            $version = PageVersion::where('business_id', $businessId)
                ->where('page_id', $pageId)
                ->findOrFail($versionId);

            // Restore every block the page showed. Only the system blocks are dropped — publish() appends them
            // again — so a restored page is the old page, not just its FAQs and videos (it was, until MAIN-1042).
            $authoredBlocks = collect($version->content_blocks)
                ->filter(fn ($block) => is_array($block) && ! in_array($block['type'] ?? '', SiteEngine::REQUIRED_BLOCK_TYPES, true))
                ->values()
                ->toArray();

            $page = Page::where('business_id', $businessId)->findOrFail($pageId);

            // An open proposal would overwrite the restored draft on Apply, so refuse rather than lose either.
            if (isset($page->draft_meta['pending_edit'])) {
                return ['status' => 'refused', 'reason' => 'pending_edit'];
            }

            // The draft being replaced goes on the undo stack, like every other draft write.
            $meta = $page->draft_meta ?? [];
            $previousSiteTokens = Business::whereKey($businessId)->value('site_tokens');
            if (is_string($previousSiteTokens)) {
                $previousSiteTokens = json_decode($previousSiteTokens, true);
            }
            $meta['undo'] = $meta['undo'] ?? [];
            $meta['undo'][] = ['blocks' => $page->draft_blocks ?? [], 'site_tokens' => $previousSiteTokens];
            if (count($meta['undo']) > 20) {
                array_shift($meta['undo']);
            }

            $page->update(['draft_blocks' => $authoredBlocks, 'draft_meta' => $meta]);

            $this->addressAction->handle($businessId);

            $result = $this->engine->publish($businessId, $pageId, $authoredBlocks);

            $result['restored_commit_id'] = $version->commit_id;

            return $result;
        });
    }
}
