<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

/**
 * The plain-text fields an owner may type straight onto the page in the Studio canvas. Never a url, phone,
 * email, address, price or image — those need real details and stay in their own editors. The canvas only
 * marks these fields, but the server checks this list, because a message from the frame is never trusted.
 * A list FAQ's items are addressed as items.N.question / items.N.answer.
 */
final class InlineFields
{
    public const FIELDS = [
        'about' => ['heading', 'text'],
        'booking_button' => ['label'],
        'booking_form' => ['heading'],
        'faq' => ['question', 'answer'],
        'cta_band' => ['heading', 'text', 'label'],
        'gallery' => ['heading'],
        'hero' => ['headline', 'subline', 'cta_label'],
        'products' => ['heading'],
        'reviews_strip' => ['heading'],
        'services' => ['heading'],
        'stats' => ['heading'],
        'team' => ['heading'],
    ];

    public static function allows(mixed $block, string $field): bool
    {
        if (! is_array($block)) {
            return false;
        }

        $type = $block['type'] ?? null;
        if ($type === 'faq' && preg_match('/^items\.(\d+)\.(question|answer)$/', $field, $m) === 1) {
            return is_array($block['items'][(int) $m[1]] ?? null);
        }
        if (! is_string($type) || ! in_array($field, self::FIELDS[$type] ?? [], true)) {
            return false;
        }

        // A list-shaped FAQ renders its items, not a top-level question/answer: an edit there would be invisible.
        return ! ($type === 'faq' && isset($block['items']));
    }
}
