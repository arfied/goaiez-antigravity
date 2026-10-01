<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

final class BlockPatchSchema
{
    /** What the MODEL may propose. It never emits set_image_list: an image path is server output. */
    public const MODEL_OPS = ['set_string', 'set_string_list', 'remove', 'move', 'add_block'];

    /** What the APPLIER can execute, including the ops the action synthesises after generating a picture. */
    public const APPLIER_OPS = ['set_string', 'set_string_list', 'set_image_list', 'remove', 'move', 'add_block'];

    /**
     * The block types the model may ADD, and the list is short on an honesty argument rather than an
     * arbitrary one.
     *
     * `SiteBlockRenderer::validateBlock()` already refuses an EMPTY block for exactly these three — a
     * `hero` needs a non-empty `headline`, an `about` a non-empty `text`, a `faq` a `question` and an
     * `answer`. For every other type it checks only that `items` IS AN ARRAY, so `items => []` passes and
     * the added section renders nothing: the placeholder illusion. And the types it would let through are
     * precisely the ones whose content cannot be written without inventing something — reviews, people,
     * images, prices, a phone number, a real URL — which the prompt forbids in the same breath.
     *
     * So: these three are addable because the existing validator is already a content guard for them, and
     * no new guard had to be invented to make that true.
     */
    public const ADDABLE_TYPES = ['hero', 'about', 'faq'];

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'explanation' => ['type' => 'string'],
                'patches' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'op' => ['type' => 'string', 'enum' => self::MODEL_OPS],
                            'block_index' => ['type' => 'integer'],
                            'field' => ['type' => 'string'],
                            'value' => ['type' => 'string'],
                            'values' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'to_index' => ['type' => 'integer'],
                            'type' => ['type' => 'string', 'enum' => self::ADDABLE_TYPES],
                            'fields' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'headline' => ['type' => 'string'],
                                    'subline' => ['type' => 'string'],
                                    'text' => ['type' => 'string'],
                                    'question' => ['type' => 'string'],
                                    'answer' => ['type' => 'string'],
                                ],
                            ],
                        ],
                        'required' => ['op', 'block_index'],
                    ],
                ],
                'images' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'block_index' => ['type' => 'integer'],
                            'description' => ['type' => 'string'],
                        ],
                        'required' => ['block_index', 'description'],
                    ],
                ],
                'style' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'palette' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'primary' => ['type' => 'string'],
                                'accent' => ['type' => 'string'],
                                'surface' => ['type' => 'string'],
                                'ink' => ['type' => 'string'],
                                'canvas' => ['type' => 'string'],
                                'band' => ['type' => 'string'],
                                'card' => ['type' => 'string'],
                            ],
                        ],
                        'type_pairing' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'heading' => ['type' => 'string'],
                                'body' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
            'required' => ['explanation', 'patches'],
        ];
    }
}
