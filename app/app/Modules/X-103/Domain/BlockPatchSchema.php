<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

/**
 * The JSON schema for a patch-shaped authoring response.
 *
 * ⚠️ EVERY value is ONE concrete type and every object sets
 * `additionalProperties: false`, because Anthropic's strict mode refuses
 * anything else. Measured against the live API 2026-09-30: a missing flag, a
 * `true` flag, an untyped property, a `['string','array','object']` union and
 * `oneOf` each return HTTP 400; `{'type':'string'}` and
 * `{'type':'array','items':{'type':'string'}}` return 200.
 *
 * ⛔ `oneOf` IS UNSUPPORTED, so the ops cannot each carry their own payload
 * shape. That is why this is one flat object whose per-op properties are chosen
 * by `op`, and why a whole-blocks response cannot be expressed here at all.
 */
final class BlockPatchSchema
{
    /** The only ops. There is deliberately no insert: see BlockPatchApplier. */
    public const OPS = ['set_string', 'set_string_list', 'remove', 'move'];

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
                            'op' => ['type' => 'string', 'enum' => self::OPS],
                            'block_index' => ['type' => 'integer'],
                            'field' => ['type' => 'string'],
                            'value' => ['type' => 'string'],
                            'values' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'to_index' => ['type' => 'integer'],
                        ],
                        'required' => ['op', 'block_index'],
                    ],
                ],
            ],
            'required' => ['explanation', 'patches'],
        ];
    }
}
