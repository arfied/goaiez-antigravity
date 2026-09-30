<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Models\Business;
use App\Modules\X103\Domain\BlockPatchApplier;
use App\Modules\X103\Models\Page;
use Illuminate\Support\Facades\DB;

final class SiteBlockFieldSetAction
{
    public function __construct(
        private readonly BlockPatchApplier $applier
    ) {}

    public function handle(int $businessId, int $pageId, int $blockIndex, string $field, string $value): array
    {
        return DB::transaction(function () use ($businessId, $pageId, $blockIndex, $field, $value) {
            $page = Page::where('business_id', $businessId)->findOrFail($pageId);

            $result = $this->applier->apply($page->draft_blocks ?? [], [[
                'op' => 'set_string',
                'block_index' => $blockIndex,
                'field' => $field,
                'value' => $value,
            ]]);

            if ($result['status'] === 'refused') {
                return ['status' => 'refused', 'reason' => $result['reason']];
            }

            $meta = $page->draft_meta ?? [];
            if (! isset($meta['undo'])) {
                $meta['undo'] = [];
            }
            $previousSiteTokens = Business::whereKey($businessId)->value('site_tokens');
            if (is_string($previousSiteTokens)) {
                $previousSiteTokens = json_decode($previousSiteTokens, true);
            }
            $meta['undo'][] = ['blocks' => $page->draft_blocks ?? [], 'site_tokens' => $previousSiteTokens];
            if (count($meta['undo']) > 20) {
                array_shift($meta['undo']);
            }

            $page->draft_blocks = $result['blocks'];
            $page->draft_meta = $meta;
            $page->save();

            return ['status' => 'applied', 'reason' => null];
        });
    }
}
