<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which alphabet a text message is submitted in, and therefore how much of it
 * fits in one segment.
 *
 * ⚠️ **THIS IS PHYSICS, NOT PREFERENCE.** GSM 03.38 packs seven bits per
 * character, so a single-segment message holds 160 of them; a message containing
 * one character outside that alphabet is submitted as UCS-2 and a single segment
 * holds **70 UTF-16 code units**. Nobody chooses this — the carrier picks the
 * encoding from the bytes, so a composer that assumes GSM-7 and emits one `’`
 * has silently more than halved its own budget.
 *
 * ⛔ **AND THE COST OF GETTING IT WRONG IS INVISIBLE.** A two-segment message is
 * delivered, so nothing fails: it costs a second segment, it is more likely to be
 * filtered, and on some handsets it arrives as two texts out of order. Every test
 * passes. That is the failure shape `CLAUDE.md` records most often, which is why
 * the budget is measured rather than assumed.
 */
enum SmsEncoding: string
{
    /**
     * The GSM 03.38 default alphabet, seven bits per character.
     *
     * ⚠️ Ten characters cost **two** septets rather than one — they are
     * submitted as an escape followed by the character. `SmsBudget` counts them;
     * see its extension table.
     */
    case Gsm7 = 'gsm7';

    /**
     * UTF-16, sixteen bits per code unit. Anything outside GSM-7 forces it, for
     * the whole message rather than for the offending character.
     */
    case Ucs2 = 'ucs2';

    /**
     * The most a single segment may carry, in this encoding's own units.
     *
     * ⚠️ **159 RATHER THAN 160, AND THE MISSING UNIT IS THE LAW** — T137's
     * `SL-2` and the T89 addendum both word it as *"≤159 chars, single segment,
     * link included"*. The spare septet is deliberate headroom: a body assembled
     * at exactly the physical limit has none left for the one character a later
     * change adds, and the change that adds it is invisible at review because
     * nothing fails.
     *
     * ⚠️ **67 RATHER THAN 70 ON UCS-2, AND THE ADDENDUM GIVES A RANGE** — it
     * says *"≤67–70"*. The conservative end is taken, on `CLAUDE.md`'s ambiguity
     * rule: anything that fits in 67 certainly fits in 70, and the error in the
     * other direction is a two-segment send that reports as a success.
     */
    public function segmentBudget(): int
    {
        return match ($this) {
            self::Gsm7 => 159,
            self::Ucs2 => 67,
        };
    }
}
