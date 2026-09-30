<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

final class BlockPatchSchema
{
    /** What the MODEL may propose. It never emits set_image_list: an image path is server output. */
    public const MODEL_OPS = ['set_string', 'set_string_list', 'remove', 'move'];

    /** What the APPLIER can execute, including the ops the action synthesises after generating a picture. */
    public const APPLIER_OPS = ['set_string', 'set_string_list', 'set_image_list', 'remove', 'move'];

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
