<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

/**
 * The plain-text fields an owner may type straight onto the page in the Studio canvas. Never a url, phone,
 * email, address, price or image — those need real details and stay in their own editors. The canvas only
 * marks these fields, but the server checks this list, because a message from the frame is never trusted.
 */
final class InlineFields
{
    public const FIELDS = [
        'hero' => ['headline', 'subline'],
        'about' => ['heading', 'text'],
        'faq' => ['question', 'answer'],
        'booking_button' => ['label'],
    ];

    public static function allows(mixed $block, string $field): bool
    {
        if (! is_array($block)) {
            return false;
        }

        $type = $block['type'] ?? null;
        if (! is_string($type) || ! in_array($field, self::FIELDS[$type] ?? [], true)) {
            return false;
        }

        // A list-shaped FAQ renders its items, not a top-level question/answer: an edit there would be invisible.
        return ! ($type === 'faq' && isset($block['items']));
    }
}
