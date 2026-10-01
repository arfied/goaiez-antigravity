<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Domain\BlockPatchApplier;
use App\Modules\X103\Models\Page;

final class SiteEditAskAction
{
    public function __construct(
        private readonly SiteEditProposeAction $action,
        private readonly BlockPatchApplier $applier
    ) {}

    public function handle(int $businessId, int $pageId, string $request): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);

        $hasPending = isset($page->draft_meta['pending_edit']);
        if (! $hasPending && empty($page->draft_blocks)) {
            return [
                'status' => 'refused',
                'reason' => 'no_blocks',
                'message' => 'This page has no blocks yet. Add a hero first.',
                'edits' => 0,
                'model' => null,
                'images' => 0,
            ];
        }

        $res = $this->action->handle(
            businessId: $businessId,
            pageId: $pageId,
            request: $request,
            continue: isset($page->draft_meta['pending_edit'])
        );

        if ($res['status'] === 'refused') {
            $coaching = [
                'no_valid_blocks' => 'The AI did not propose a change. Name the block and the exact words you want, for example: on the hero, set the headline to …',
                'empty_request' => 'Type what you want changed first.',
            ][$res['reason']] ?? null;

            // ⛔ NO fallback value, deliberately. Both Ask screens read
            // `$res['message'] ?? $res['reason']`, so a reason this map does not carry shows its raw
            // code rather than a generic sentence. A `default` arm here would silently absorb the next
            // refusal reason added to SiteEditProposeAction — the owner would see something plausible
            // and nobody would learn the new code existed. That is what `boundary` flagged.
            //
            // The model's own words, and ONLY for the one reason that has any: `no_valid_blocks` is the
            // single branch reached after a successful call, so it is the only one where `explanation`
            // exists. Every other reason — including the next one somebody adds — keeps the map-or-raw-code
            // behaviour the comment above is about. Widening this to the FIELD rather than to the CASE
            // would repeal that guard (N390).
            $explanation = trim((string) ($res['explanation'] ?? ''));
            $message = ($res['reason'] === 'no_valid_blocks' && $explanation !== '' && $coaching !== null)
                ? $explanation.' '.$coaching
                : $coaching;

            return [
                'status' => 'refused',
                'reason' => $res['reason'],
                'message' => $message,
                'edits' => 0,
                'model' => null,
                'images' => 0,
            ];
        }

        $applyResult = $this->applier->apply($page->draft_blocks ?? [], $res['patches']);

        if ($applyResult['status'] === 'refused') {
            return [
                'status' => 'refused',
                'reason' => $applyResult['reason'],
                'edits' => 0,
                'model' => null,
                'images' => 0,
            ];
        }

        $meta = $page->draft_meta ?? [];
        $thread = [];

        if (isset($meta['pending_edit'])) {
            $thread = $meta['pending_edit']['thread'] ?? [];
            if (empty($thread)) {
                $thread[] = [
                    'request' => $meta['pending_edit']['request'] ?? '',
                    'explanation' => $meta['pending_edit']['explanation'] ?? '',
                    'at' => $meta['pending_edit']['drafted_at'] ?? '',
                ];
            }
        }
        $now = now()->toIso8601String();
        $thread[] = [
            'request' => $request,
            'explanation' => $res['explanation'],
            'at' => $now,
        ];

        $pendingEdit = [
            'request' => $request,
            'blocks' => $applyResult['blocks'],
            'explanation' => $res['explanation'],
            'model' => $res['model'],
            'drafted_at' => $now,
            'thread' => $thread,
        ];

        if (! empty($res['image_notes'])) {
            $pendingEdit['image_notes'] = $res['image_notes'];
        }
        if ($res['style'] !== null) {
            $pendingEdit['style'] = $res['style'];
        }
        if ($res['style_refused'] !== null) {
            $pendingEdit['style_refused'] = $res['style_refused'];
        }

        $meta['pending_edit'] = $pendingEdit;
        $page->draft_meta = $meta;
        $page->save();

        return [
            'status' => 'proposed',
            'reason' => null,
            'edits' => count($res['patches']),
            'model' => $res['model'],
            'images' => $res['images'] ?? 0,
        ];
    }
}
