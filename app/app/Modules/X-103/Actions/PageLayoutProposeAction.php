<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Domain\PageLayouts;
use App\Modules\X103\Domain\SectionOrder;
use App\Modules\X103\Models\Page;
use App\Services\Industry\IndustryStartingPoints;

/**
 * Proposes a named layout for one page. The reordered blocks land in draft_meta.pending_edit — the same
 * place an AI edit lands — so the Studio previews it and the owner Applies (which saves an undo entry) or
 * Discards. Refuses while another proposal is pending: Apply replaces draft_blocks wholesale, so a second
 * proposal written over the first would silently lose it.
 */
final class PageLayoutProposeAction
{
    public function __construct(private readonly IndustryStartingPoints $startingPoints) {}

    /**
     * @return array{status: string, reason: ?string}
     */
    public function handle(int $businessId, int $pageId, string $layout): array
    {
        if (! isset(PageLayouts::LAYOUTS[$layout])) {
            return ['status' => 'refused', 'reason' => 'unknown_layout'];
        }

        $page = Page::where('business_id', $businessId)->findOrFail($pageId);
        $meta = $page->draft_meta ?? [];

        if (isset($meta['pending_edit'])) {
            return ['status' => 'refused', 'reason' => 'pending_edit'];
        }

        $blocks = $page->draft_blocks ?? [];
        if ($blocks === []) {
            return ['status' => 'refused', 'reason' => 'empty_page'];
        }

        $order = PageLayouts::LAYOUTS[$layout]['order'] ?? $this->startingPoints->forBusiness($businessId)['section_order'];
        $reordered = SectionOrder::apply($blocks, $order);

        if ($reordered === $blocks) {
            return ['status' => 'unchanged', 'reason' => null];
        }

        $now = now()->toIso8601String();
        $request = 'Use the '.PageLayouts::LAYOUTS[$layout]['label'].' layout';
        $explanation = PageLayouts::LAYOUTS[$layout]['explanation'];

        $meta['pending_edit'] = [
            'request' => $request,
            'blocks' => $reordered,
            'explanation' => $explanation,
            'layout' => $layout,
            'drafted_at' => $now,
            'thread' => [['request' => $request, 'explanation' => $explanation, 'at' => $now]],
        ];
        $page->draft_meta = $meta;
        $page->save();

        return ['status' => 'proposed', 'reason' => null];
    }
}
