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

    /**
     * Each section's layouts — the first is the default and renders exactly the markup that section always had. A
     * layout is a modifier class on the section (services--list, faq--cards…) styled in SiteBlockRenderer, so the
     * markup every module parses (FAQ items, reviews, contact links) never changes shape. A theme picks one per section
     * (SiteThemes) and the AI may pick one per section ('variant').
     */
    public const VARIANTS = [
        'hero' => self::HERO_VARIANTS,
        'services' => ['cards', 'list', 'columns'],
        'reviews_strip' => ['cards', 'quote', 'row'],
        'faq' => ['list', 'cards', 'columns'],
        'about' => ['plain', 'centered', 'split'],
        'contact' => ['stack', 'columns', 'card'],
        'booking_button' => ['inline', 'banner', 'card'],
        'stats' => ['row', 'cards', 'bar'],
    ];

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

    /**
     * A video is the owner's: the AI may put a video address on a page only when the owner gave it — in their own words
     * (with or without https:// and www.), or already on the page. An address the AI made up would show a video the
     * business never made and tell search engines about it (the publisher writes a VideoObject for every video section).
     *
     * @param  array<int, mixed>  $currentBlocks
     */
    public static function ownerGaveVideo(mixed $url, string $ownerWords, array $currentBlocks): bool
    {
        $address = is_scalar($url) ? trim((string) $url) : '';
        if ($address === '') {
            return false;
        }
        foreach ($currentBlocks as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'video_embed' && is_scalar($block['contentUrl'] ?? null)
                && trim((string) $block['contentUrl']) === $address) {
                return true;
            }
        }
        $bare = (string) preg_replace('#^(https?://)?(www\.)?#i', '', $address);

        return $bare !== '' && str_contains(strtolower($ownerWords), strtolower($bare));
    }

    /**
     * An AI patch that would write a video address the owner did not give: adding a video section, or changing a
     * video's address.
     *
     * @param  array<string, mixed>  $patch
     * @param  array<int, mixed>  $currentBlocks
     */
    public static function patchInventsVideo(array $patch, string $ownerWords, array $currentBlocks): bool
    {
        $op = $patch['op'] ?? null;
        if ($op === 'add_block' && ($patch['type'] ?? null) === 'video_embed') {
            return ! self::ownerGaveVideo(is_array($patch['fields'] ?? null) ? ($patch['fields']['contentUrl'] ?? null) : null, $ownerWords, $currentBlocks);
        }
        if ($op === 'set_string' && ($patch['field'] ?? null) === 'contentUrl') {
            return ! self::ownerGaveVideo($patch['value'] ?? null, $ownerWords, $currentBlocks);
        }

        return false;
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

        if ($field === 'variant' && ! in_array((string) $value, array_merge(...array_values(self::VARIANTS)), true)) {
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
        // A layout must be one this section has; anything else is dropped and the section keeps its default.
        if (isset($block['variant']) && ! in_array($block['variant'], self::VARIANTS[$type] ?? [], true)) {
            unset($block['variant']);
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
