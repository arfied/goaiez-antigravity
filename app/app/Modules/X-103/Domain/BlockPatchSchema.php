<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

final class BlockPatchSchema
{
    /**
     * What the MODEL may propose, enforced by SiteEditProposeAction as well as by the schema. Never an image
     * op — an image path is server output — and never set_string_list — lists go through set_items, which never accepts a file path.
     */
    public const MODEL_OPS = ['set_string', 'set_items', 'remove', 'move', 'add_block'];

    /** What the APPLIER can execute: the model's ops, the owner's set_item_string, and the two image ops the server synthesises. */
    public const APPLIER_OPS = ['set_string', 'set_items', 'set_item_string', 'set_image', 'set_image_list', 'remove', 'move', 'add_block'];

    /**
     * Every block field the AI may write (owner ruling 2026-10-02: the AI may write anything on a page). Never a file
     * path or image size, the section type, a form's internals, or a list — lists go through set_items.
     */
    public const MODEL_FIELDS = ['headline', 'subline', 'heading', 'text', 'question', 'answer', 'label', 'url', 'phone', 'email', 'address', 'name', 'service', 'contentUrl', 'uploadDate', 'image_alt', 'variant', 'cta_label', 'cta_url'];

    /** Link fields: each must start with https://, http://, tel: or mailto: — a javascript: URL in an href is script injection. */
    public const URL_FIELDS = ['url', 'contentUrl', 'cta_url'];

    /** Section types whose `items` list the AI may write, and the item fields it may write. Never a gallery: its items are image files. */
    public const ITEM_TYPES = ['services', 'faq', 'reviews_strip', 'team', 'stats'];

    public const ITEM_FIELDS = ['name', 'description', 'price_text', 'author', 'rating', 'source', 'text', 'role', 'question', 'answer', 'value', 'label'];

    /** The top banner's layouts: words beside the picture, large centred words, or words over a full-width picture. */
    public const HERO_VARIANTS = ['split', 'centered', 'cover'];

    /** The plain-text fields of a LIST item the owner may type over in the Studio (set_item_string). Never sent by the model. */
    public const ITEM_TEXT_FIELDS = [
        'faq' => ['question', 'answer'],
    ];

    /**
     * The section types the AI may add (owner ruling 2026-10-02). Never a gallery — its items are image files — and never
     * a form, which needs a form definition the AI cannot create.
     */
    public const ADDABLE_TYPES = ['hero', 'about', 'faq', 'services', 'reviews_strip', 'team', 'booking_button', 'booking_form', 'contact', 'video_embed', 'cta_band', 'stats'];

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
                                'properties' => array_fill_keys(self::MODEL_FIELDS, ['type' => 'string']),
                            ],
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'properties' => array_fill_keys(self::ITEM_FIELDS, ['type' => 'string']),
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

    public static function isSafeLink(string $value): bool
    {
        return preg_match('#^(https?://|tel:|mailto:)#i', trim($value)) === 1;
    }

    /** A field the AI may write, with a value it may write there. */
    public static function modelMayWrite(string $field, mixed $value): bool
    {
        if (! in_array($field, self::MODEL_FIELDS, true) || ! is_scalar($value)) {
            return false;
        }

        if ($field === 'variant' && ! in_array((string) $value, self::HERO_VARIANTS, true)) {
            return false;
        }

        return ! in_array($field, self::URL_FIELDS, true) || self::isSafeLink((string) $value);
    }

    /**
     * @return list<array<string, string>>
     */
    public static function modelItems(mixed $items): array
    {
        $clean = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $row = [];
            foreach ($item as $k => $v) {
                if (is_string($k) && in_array($k, self::ITEM_FIELDS, true) && is_scalar($v) && trim((string) $v) !== '') {
                    $row[$k] = (string) $v;
                }
            }
            if ($row !== []) {
                $clean[] = $row;
            }
        }

        return $clean;
    }

    /**
     * A section built from what the AI wrote: its type, only the fields it may write, and its items when the type has a
     * list. Anything else the AI sent — a file path, the type, a form's internals — is dropped here.
     *
     * @return array<string, mixed>
     */
    public static function modelBlock(string $type, array $fields, mixed $items = null): array
    {
        $block = ['type' => $type];
        foreach ($fields as $k => $v) {
            if (is_string($k) && self::modelMayWrite($k, $v) && trim((string) $v) !== '') {
                $block[$k] = (string) $v;
            }
        }
        if (in_array($type, self::ITEM_TYPES, true) && $items !== null) {
            $block['items'] = self::modelItems($items);
        }

        return $block;
    }

    /** Whether a section carries anything besides its type — an AI-built section with nothing in it is not added. */
    public static function hasContent(array $block): bool
    {
        foreach ($block as $k => $v) {
            if ($k !== 'type' && $v !== '' && $v !== []) {
                return true;
            }
        }

        return false;
    }
}
