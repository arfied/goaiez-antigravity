<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

final class BlockPatchApplier
{
    public function __construct(private readonly SiteBlockRenderer $renderer) {}

    /**
     * Applies a set of patches sequentially to a list of blocks, validating the entire set first.
     *
     * ⚠️ The `block_index` in these patches corresponds to the index in `draft_blocks`, which is
     * the same number the preview emits as `data-block-index`. That attribute comes from the
     * `foreach` key in `SiteBlockRenderer::render()` (SITE-372), NOT from `$renderedCount`,
     * which skips the `hero` and any dropped blocks. This distinction is critical because an
     * applier keyed to the wrong counter would edit the wrong block.
     *
     * @param  array<int, array<string, mixed>>  $blocks  The untouched input blocks.
     * @param  array<int, array<string, mixed>>  $patches  The patches to apply.
     * @return array{status: 'applied'|'refused', blocks: array<int, array<string, mixed>>, applied: int, reason: ?string}
     */
    public function apply(array $blocks, array $patches): array
    {
        // Validate the ENTIRE set before mutating anything. The patches are applied in order, so each is checked against the
        // page as the patches before it leave it: an added section can be moved or edited by a later patch (production,
        // 2026-10-04: "add a numbers row, then move it up" was refused as out of range and the whole proposal was lost).
        $length = count($blocks);
        foreach ($patches as $i => $patch) {
            $op = $patch['op'] ?? null;
            if (! in_array($op, BlockPatchSchema::APPLIER_OPS, true)) {
                return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: unknown op"];
            }

            $blockIndex = $patch['block_index'] ?? null;
            // An add is an INSERT POSITION, so one past the last block is legal and means "at the end".
            $maxIndex = $op === 'add_block' ? $length : $length - 1;
            if (! is_int($blockIndex) || $blockIndex < 0 || $blockIndex > $maxIndex) {
                return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: block_index out of range ({$op} at ".json_encode($blockIndex).", page has {$length} sections)"];
            }

            if (($patch['field'] ?? null) === 'type') {
                return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: field cannot be type"];
            }

            if ($op === 'add_block') {
                $addType = $patch['type'] ?? null;
                if (! is_string($addType) || ! in_array($addType, BlockPatchSchema::ADDABLE_TYPES, true)) {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: add_block cannot add a '".(is_string($addType) ? $addType : 'missing')."' section — only ".implode(', ', BlockPatchSchema::ADDABLE_TYPES).' can be added by the AI — a gallery needs your photos and a form needs your form settings'];
                }
                if (! isset($patch['fields']) || ! is_array($patch['fields'])) {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: add_block requires fields"];
                }
            }

            if ($op === 'set_string') {
                if (! isset($patch['field']) || ! array_key_exists('value', $patch)) {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: set_string requires field and value"];
                }
            } elseif ($op === 'set_items') {
                if (! isset($patch['items']) || ! is_array($patch['items'])) {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: set_items requires items"];
                }
            } elseif ($op === 'set_item_string') {
                if (! is_int($patch['item_index'] ?? null) || ! isset($patch['field']) || ! array_key_exists('value', $patch)) {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: set_item_string requires item_index, field and value"];
                }
            } elseif ($op === 'set_image') {
                if (($patch['field'] ?? null) !== 'image_path' || ! isset($patch['path']) || ! is_scalar($patch['path']) || trim((string) $patch['path']) === '') {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: set_image requires field image_path and a path"];
                }
            } elseif ($op === 'set_image_list') {
                if (! isset($patch['field']) || ! isset($patch['images']) || ! is_array($patch['images'])) {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: set_image_list requires field and images array"];
                }
                foreach ($patch['images'] as $imgIndex => $img) {
                    if (! isset($img['image_path']) || ! is_scalar($img['image_path']) || trim((string) $img['image_path']) === '') {
                        return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: set_image_list image $imgIndex missing or invalid image_path"];
                    }
                }
            } elseif ($op === 'move') {
                $toIndex = $patch['to_index'] ?? null;
                if (! is_int($toIndex) || $toIndex < 0 || $toIndex > $maxIndex) {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: move requires to_index in range (to ".json_encode($toIndex).", page has {$length} sections)"];
                }
            }

            if ($op === 'add_block') {
                $length++;
            } elseif ($op === 'remove') {
                $length--;
            }
        }

        // Apply sequentially
        $appliedBlocks = $blocks;
        foreach ($patches as $i => $patch) {
            $op = $patch['op'];
            $blockIndex = $patch['block_index'];

            if ($op === 'add_block') {
                $newBlock = BlockPatchSchema::modelBlock((string) $patch['type'], $patch['fields'], $patch['items'] ?? null);
                if (! BlockPatchSchema::hasContent($newBlock)) {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: add_block has nothing to show"];
                }
                // array_splice clamps an index past the end, which is what makes the append case safe even
                // though the range was validated against the ORIGINAL block count while this pass is
                // sequential and an earlier patch may already have changed the length.
                array_splice($appliedBlocks, $blockIndex, 0, [$newBlock]);
            } elseif ($op === 'set_string') {
                if (! BlockPatchSchema::modelMayWrite((string) $patch['field'], $patch['value'])) {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: {$patch['field']} cannot be written — never a file path, the section type or a list, and a link must start with https://, http://, tel: or mailto:"];
                }
                $appliedBlocks[$blockIndex][$patch['field']] = (string) $patch['value'];
            } elseif ($op === 'set_items') {
                $type = (string) ($appliedBlocks[$blockIndex]['type'] ?? '');
                $items = BlockPatchSchema::modelItems($patch['items']);
                if (! in_array($type, BlockPatchSchema::ITEM_TYPES, true) || $items === []) {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: a {$type} section has no list the AI may write, or the list was empty"];
                }
                $appliedBlocks[$blockIndex]['items'] = $items;
            } elseif ($op === 'set_item_string') {
                $type = (string) ($appliedBlocks[$blockIndex]['type'] ?? '');
                $itemIndex = $patch['item_index'];
                if (! in_array($patch['field'], BlockPatchSchema::ITEM_TEXT_FIELDS[$type] ?? [], true) || ! is_array($appliedBlocks[$blockIndex]['items'][$itemIndex] ?? null)) {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: the {$patch['field']} of item {$itemIndex} in a {$type} section is not text that may be written here"];
                }
                $appliedBlocks[$blockIndex]['items'][$itemIndex][$patch['field']] = (string) $patch['value'];
            } elseif ($op === 'set_image') {
                $appliedBlocks[$blockIndex]['image_path'] = (string) $patch['path'];
            } elseif ($op === 'set_image_list') {
                $appliedBlocks[$blockIndex][$patch['field']] = $patch['images'];
            } elseif ($op === 'remove') {
                unset($appliedBlocks[$blockIndex]);
                $appliedBlocks = array_values($appliedBlocks);
            } elseif ($op === 'move') {
                $toIndex = $patch['to_index'];
                $block = $appliedBlocks[$blockIndex];
                unset($appliedBlocks[$blockIndex]);
                $appliedBlocks = array_values($appliedBlocks);
                array_splice($appliedBlocks, $toIndex, 0, [$block]);
            }
        }

        // Validate the result
        foreach ($appliedBlocks as $i => $block) {
            if (! $this->renderer->isValidBlock($block)) {
                return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch set results in invalid block at index $i"];
            }
        }

        return ['status' => 'applied', 'blocks' => $appliedBlocks, 'applied' => count($patches), 'reason' => null];
    }
}
