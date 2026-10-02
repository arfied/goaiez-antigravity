<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Models\Business;
use App\Modules\X103\Domain\BlockPatchApplier;
use App\Modules\X103\Models\Page;
use Illuminate\Support\Facades\DB;

/**
 * Adds a section the owner wrote — below the selected one, or at the end — through the applier's add_block op,
 * so the same type and field rules hold as for the AI. Edits the open proposal when there is one; otherwise
 * the replaced draft goes on the undo stack.
 */
final class SiteBlockAddAction
{
    public function __construct(private readonly BlockPatchApplier $applier) {}

    /**
     * @param  array<string, string>  $fields
     * @return array{status: string, reason: ?string, index: ?int, target: ?string}
     */
    public function handle(int $businessId, int $pageId, ?int $afterIndex, string $type, array $fields): array
    {
        return DB::transaction(function () use ($businessId, $pageId, $afterIndex, $type, $fields) {
            $page = Page::where('business_id', $businessId)->findOrFail($pageId);

            $pending = $page->draft_meta['pending_edit']['blocks'] ?? null;
            $targetIsPending = is_array($pending);
            $blocks = $targetIsPending ? $pending : ($page->draft_blocks ?? []);

            $at = $afterIndex === null ? count($blocks) : $afterIndex + 1;
            $result = $this->applier->apply($blocks, [[
                'op' => 'add_block',
                'block_index' => $at,
                'type' => $type,
                'fields' => array_map(fn ($v) => trim((string) $v), $fields),
            ]]);
            if ($result['status'] === 'refused') {
                return ['status' => 'refused', 'reason' => $result['reason'], 'index' => null, 'target' => null];
            }

            $meta = $page->draft_meta ?? [];
            if ($targetIsPending) {
                $meta['pending_edit']['blocks'] = $result['blocks'];
            } else {
                $previousSiteTokens = Business::whereKey($businessId)->value('site_tokens');
                if (is_string($previousSiteTokens)) {
                    $previousSiteTokens = json_decode($previousSiteTokens, true);
                }
                $meta['undo'] = $meta['undo'] ?? [];
                $meta['undo'][] = ['blocks' => $page->draft_blocks ?? [], 'site_tokens' => $previousSiteTokens];
                if (count($meta['undo']) > 20) {
                    array_shift($meta['undo']);
                }
                $page->draft_blocks = $result['blocks'];
            }
            $page->draft_meta = $meta;
            $page->save();

            return ['status' => 'applied', 'reason' => null, 'index' => $at, 'target' => $targetIsPending ? 'pending' : 'draft'];
        });
    }
}
