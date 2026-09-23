<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

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

            $authoredBlocks = collect($version->content_blocks)
                ->filter(fn ($block) => in_array($block['type'] ?? '', ['faq', 'video_embed'], true))
                ->values()
                ->toArray();

            $page = Page::where('business_id', $businessId)->findOrFail($pageId);
            $page->update(['draft_blocks' => $authoredBlocks]);

            $this->addressAction->handle($businessId);

            $result = $this->engine->publish($businessId, $pageId, $authoredBlocks);

            $result['restored_commit_id'] = $version->commit_id;

            return $result;
        });
    }
}
