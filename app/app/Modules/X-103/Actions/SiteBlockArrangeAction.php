<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Models\Business;
use App\Modules\X103\Domain\BlockPatchApplier;
use App\Modules\X103\Domain\SiteEngine;
use App\Modules\X103\Models\Page;
use Illuminate\Support\Facades\DB;

/**
 * Moves one section up or down, or removes it — the owner's own arrangement from the Studio inspector. Goes
 * through the applier like every other draft write, edits the open proposal when there is one, and otherwise
 * puts the replaced draft on the undo stack. System blocks are never moved or removed from the editor.
 */
final class SiteBlockArrangeAction
{
    public function __construct(private readonly BlockPatchApplier $applier) {}

    /**
     * @return array{status: string, reason: ?string, index: ?int, target: ?string}
     */
    public function handle(int $businessId, int $pageId, int $blockIndex, string $move): array
    {
        return DB::transaction(function () use ($businessId, $pageId, $blockIndex, $move) {
            $page = Page::where('business_id', $businessId)->findOrFail($pageId);

            $pending = $page->draft_meta['pending_edit']['blocks'] ?? null;
            $targetIsPending = is_array($pending);
            $blocks = $targetIsPending ? $pending : ($page->draft_blocks ?? []);

            $type = is_array($blocks[$blockIndex] ?? null) ? (string) ($blocks[$blockIndex]['type'] ?? '') : '';
            if ($type === '' || in_array($type, SiteEngine::REQUIRED_BLOCK_TYPES, true)) {
                return ['status' => 'refused', 'reason' => 'not_a_section', 'index' => null, 'target' => null];
            }

            $patch = match ($move) {
                'up' => ['op' => 'move', 'block_index' => $blockIndex, 'to_index' => $blockIndex - 1],
                'down' => ['op' => 'move', 'block_index' => $blockIndex, 'to_index' => $blockIndex + 1],
                'remove' => ['op' => 'remove', 'block_index' => $blockIndex],
                default => null,
            };
            if ($patch === null) {
                return ['status' => 'refused', 'reason' => 'unknown_move', 'index' => null, 'target' => null];
            }

            $result = $this->applier->apply($blocks, [$patch]);
            if ($result['status'] === 'refused') {
                return ['status' => 'refused', 'reason' => 'edge', 'index' => null, 'target' => null];
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

            return [
                'status' => 'applied',
                'reason' => null,
                'index' => $move === 'remove' ? null : $patch['to_index'],
                'target' => $targetIsPending ? 'pending' : 'draft',
            ];
        });
    }
}
