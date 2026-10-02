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
        // Validate the ENTIRE set before mutating anything.
        foreach ($patches as $i => $patch) {
            $op = $patch['op'] ?? null;
            if (! in_array($op, BlockPatchSchema::APPLIER_OPS, true)) {
                return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: unknown op"];
            }

            $blockIndex = $patch['block_index'] ?? null;
            // An add is an INSERT POSITION, so one past the last block is legal and means "at the end".
            $maxIndex = $op === 'add_block' ? count($blocks) : count($blocks) - 1;
            if (! is_int($blockIndex) || $blockIndex < 0 || $blockIndex > $maxIndex) {
                return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: block_index out of range"];
            }

            if (($patch['field'] ?? null) === 'type') {
                return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: field cannot be type"];
            }

            if ($op === 'add_block') {
                $addType = $patch['type'] ?? null;
                if (! is_string($addType) || ! in_array($addType, BlockPatchSchema::ADDABLE_TYPES, true)) {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: add_block cannot add a '".(is_string($addType) ? $addType : 'missing')."' section — only ".implode(', ', BlockPatchSchema::ADDABLE_TYPES).' can be written without inventing reviews, people, images, prices or a real link'];
                }
                if (! isset($patch['fields']) || ! is_array($patch['fields'])) {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: add_block requires fields"];
                }
            }

            if ($op === 'set_string') {
                if (! isset($patch['field']) || ! array_key_exists('value', $patch)) {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: set_string requires field and value"];
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
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: move requires to_index in range"];
                }
            }
        }

        // Apply sequentially
        $appliedBlocks = $blocks;
        foreach ($patches as $patch) {
            $op = $patch['op'];
            $blockIndex = $patch['block_index'];

            if ($op === 'add_block') {
                $newBlock = ['type' => $patch['type']];
                foreach ($patch['fields'] as $k => $v) {
                    if (is_string($k) && is_scalar($v)) {
                        $newBlock[$k] = (string) $v;
                    }
                }
                // array_splice clamps an index past the end, which is what makes the append case safe even
                // though the range was validated against the ORIGINAL block count while this pass is
                // sequential and an earlier patch may already have changed the length.
                array_splice($appliedBlocks, $blockIndex, 0, [$newBlock]);
            } elseif ($op === 'set_string') {
                $type = (string) ($appliedBlocks[$blockIndex]['type'] ?? '');
                if (! in_array($patch['field'], BlockPatchSchema::TEXT_FIELDS[$type] ?? [], true)) {
                    return ['status' => 'refused', 'blocks' => $blocks, 'applied' => 0, 'reason' => "patch $i: the {$patch['field']} of a {$type} section is not text that may be written here"];
                }
                $appliedBlocks[$blockIndex][$patch['field']] = (string) $patch['value'];
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
