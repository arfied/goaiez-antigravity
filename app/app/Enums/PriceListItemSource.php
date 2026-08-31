<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a row on a business's price list came from (T176 P5, §2.4).
 *
 * ⛔ **THIS IS PROVENANCE, NEVER PERMISSION.** What makes a row quotable is
 * `price_list_items.confirmed_at`, and nothing else — a `Document` row that a
 * person has confirmed is exactly as quotable as one they typed, and a `Manual`
 * row is quotable because the act of typing it *was* the confirmation. Reading
 * this enum as "manual is trusted, ingested is not" would put the review gate in
 * two places, and the second one would be the one somebody forgot.
 *
 * ⚠️ **IT SURVIVES CONFIRMATION DELIBERATELY.** After an owner confirms a
 * proposal the row stays `Document`, because the question the screen answers is
 * *"where did this figure come from?"* and the honest answer a fortnight later
 * is "we read it off your price sheet and you said yes" rather than "you typed
 * it".
 */
enum PriceListItemSource: string
{
    /**
     * Somebody typed it into the editor.
     *
     * Confirmed at the moment it is written: a person naming a price *is* the
     * review, and asking them to confirm what they have just typed is a second
     * press that teaches everybody to press through the first.
     */
    case Manual = 'manual';

    /**
     * Read off a price sheet the business uploaded.
     *
     * ⛔ **LANDS UNCONFIRMED AND IS NOT QUOTABLE UNTIL A PERSON SAYS SO.** The
     * parser is deterministic and it is still reading somebody's PDF-export
     * formatting; a misread column is a figure the assistant would quote to a
     * member of the public in the business's own name.
     */
    case Document = 'document';

    /**
     * What the editor calls it, in outcome language (`22`).
     */
    public function label(): string
    {
        return match ($this) {
            self::Manual => 'You set this',
            self::Document => 'From your price sheet',
        };
    }
}
