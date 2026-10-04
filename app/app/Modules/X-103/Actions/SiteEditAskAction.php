<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Domain\BlockPatchApplier;
use App\Modules\X103\Models\Page;
use Illuminate\Support\Facades\Log;

final class SiteEditAskAction
{
    public function __construct(
        private readonly SiteEditProposeAction $action,
        private readonly BlockPatchApplier $applier
    ) {}

    public function handle(int $businessId, int $pageId, string $request, ?int $onlyBlock = null): array
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

        if ($onlyBlock !== null) {
            $request = "Only change block {$onlyBlock}. Change nothing in any other block, and change no colours or fonts. ".$request;
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
                'unsafe_patch' => 'The AI proposed a change it is not allowed to make, so nothing was proposed. Try asking again.',
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

        if ($onlyBlock !== null) {
            foreach ($res['patches'] as $patch) {
                if (! is_array($patch) || ($patch['block_index'] ?? null) !== $onlyBlock || ! in_array($patch['op'] ?? null, ['set_string', 'set_items'], true)) {
                    return [
                        'status' => 'refused',
                        'reason' => 'outside_section',
                        'message' => 'The AI tried to change more than this section, so nothing was proposed. Try again, or use the Ask box for the whole page.',
                        'edits' => 0,
                        'model' => null,
                        'images' => 0,
                    ];
                }
            }
            if ($res['style'] !== null) {
                return [
                    'status' => 'refused',
                    'reason' => 'outside_section',
                    'message' => 'The AI tried to change the site\'s colours or fonts, so nothing was proposed. Ask about colours in the Ask box for the whole page.',
                    'edits' => 0,
                    'model' => null,
                    'images' => 0,
                ];
            }
        }

        // A follow-up continues the open proposal: SiteEditProposeAction numbered the PENDING blocks, so the
        // patches must land on them — applying to draft_blocks dropped every earlier proposed edit.
        $base = $hasPending ? ($page->draft_meta['pending_edit']['blocks'] ?? []) : ($page->draft_blocks ?? []);
        // Each AI edit is applied on its own, in order, on the page as the edits before it left it. One edit that does not fit
        // (a section that is not there, a place past the end) is skipped rather than sinking every edit beside it — production,
        // 2026-10-04: "Make it look great" was refused three times running, each time by one stray move. After a skipped add or
        // remove the AI's section numbers no longer match the page, so the edits after it are skipped too rather than landing on
        // the wrong section. The applier itself stays all-or-nothing: the owner's own buttons rely on it.
        $blocks = $base;
        $applied = 0;
        $skipped = [];
        foreach (array_values($res['patches']) as $n => $patch) {
            $one = $this->applier->apply($blocks, [$patch]);
            if ($one['status'] === 'applied') {
                $blocks = $one['blocks'];
                $applied++;

                continue;
            }
            $skipped[] = 'edit '.$n.': '.preg_replace('/^patch 0: /', '', (string) $one['reason']);
            if (in_array($patch['op'] ?? null, ['add_block', 'remove'], true)) {
                $skipped[] = 'the '.(count($res['patches']) - $n - 1).' edits after it were not tried';
                break;
            }
        }
        // Refused only when the AI proposed edits and not one of them fitted, and it brought no colours either. An answer with no
        // edits at all (a picture request alone) still becomes a proposal, so its picture notes reach the owner.
        $refused = $skipped !== [] && $applied === 0 && $res['style'] === null;
        $applyResult = ['status' => $refused ? 'refused' : 'applied', 'blocks' => $blocks, 'reason' => implode('; ', $skipped)];

        if ($skipped !== []) {
            // The owner sees one plain sentence; the applier's exact reasons are kept here so a report can be diagnosed.
            Log::warning('an AI page edit could not be applied', [
                'business_id' => $businessId,
                'page_id' => $pageId,
                'reason' => $applyResult['reason'],
                'applied' => $applied,
            ]);
        }

        if ($applyResult['status'] === 'refused') {
            return [
                'status' => 'refused',
                'reason' => $applyResult['reason'],
                'message' => 'The AI proposed a change that could not be applied, so nothing was proposed. Try asking again in different words.',
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
            'edits' => $applied,
            'model' => $res['model'],
            'images' => $res['images'] ?? 0,
        ];
    }
}
