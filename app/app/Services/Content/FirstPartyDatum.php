<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\FirstPartyDataKind;

/**
 * One piece of genuine first-party data a page claims to be built on — doc `16`
 * §15.3's *"≥2 pieces of genuine first-party data"*.
 *
 * ⛔ **THE GATE COUNTS APPEARANCES, NOT DECLARATIONS** (5569). Counting what a
 * caller says it used would make this check a self-assessment: a generator could
 * satisfy the whole gate by attaching two labels and writing the same generic
 * page it was going to write anyway, and the suite would stay green because
 * nothing ever compared the claim against the copy. `16` §15.2's argument is
 * that the *page* carries data no generic writer has, which is a fact about the
 * text.
 */
final readonly class FirstPartyDatum
{
    public function __construct(
        public FirstPartyDataKind $kind,
        /**
         * The words that must actually be on the page — a price as written, a
         * fragment of the review quoted, the street a fact is about.
         */
        public string $value,
    ) {}

    /**
     * Whether this datum is genuinely in the copy.
     *
     * ⚠️ **AN EMPTY VALUE NEVER APPEARS, WHICH IS THE OPPOSITE OF WHAT
     * `str_contains` SAYS.** PHP holds that every string contains the empty
     * string, so an unset datum would count toward the threshold and a page
     * citing two blanks would pass the first-party check.
     *
     * ⚠️ **WHITESPACE IS FLATTENED ON BOTH SIDES.** A quote that arrives with a
     * line break where the page has a space is the same quote, and failing it
     * would teach whoever meets it that the check is unreliable — which is how a
     * gate ends up widened until it catches nothing (511).
     */
    public function appearsIn(string $text): bool
    {
        $needle = self::flatten($this->value);

        if ($needle === '') {
            return false;
        }

        return mb_stripos(self::flatten($text), $needle) !== false;
    }

    private static function flatten(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
