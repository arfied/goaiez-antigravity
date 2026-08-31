<?php

declare(strict_types=1);

namespace App\Support;

/**
 * How much of one page's writing already exists somewhere else on the same site.
 *
 * Doc `16` §15.3's first gate line names the method as well as the number:
 * *"≥60% content unique vs. every other page on the site (shingle
 * comparison)"*. A shingle is a fixed-width window of consecutive words, so two
 * texts made of the same sentences in a different order still overlap, while two
 * texts that merely share a vocabulary do not. That is exactly the distinction
 * `16` §15.1's pattern 2 turns on — *"Best [service] in [city]" across hundreds
 * of locations where only the city name changes* — which word-frequency
 * similarity scores as different documents and this scores as one document.
 *
 * ⛔ **CONTAINMENT, NOT JACCARD, AND THE DIFFERENCE DECIDES REAL CASES.** The
 * question the gate asks is *"how much of THIS page is new?"*, so the
 * denominator is this page's own shingles. Jaccard divides by the union, so
 * pasting a 200-word service page into the middle of a 4,000-word article would
 * score as barely similar — the union is enormous — while every word of the
 * shorter page is a duplicate. Containment answers the question that was asked.
 *
 * ⛔ **NO PACKAGE FOR THIS** (`CLAUDE.md` forbids a new dependency without
 * approval, and `LineDiff` is the precedent). What a text-similarity library
 * buys — TF-IDF, cosine, embeddings, MinHash at web scale — is not what one
 * tenant's few dozen pages need.
 *
 * ⚠️ **PURE AND DETERMINISTIC, WHICH IS WHAT MAKES THE TEST WORTH ANYTHING.**
 * `AuditScore`'s rule: the determinism test asserts a literal score rather than
 * recomputing the formula, because a test that recalculates passes whatever the
 * formula becomes (`BUILD-PLAN` §2.5.3-E).
 */
final class Shingles
{
    /**
     * Words per window.
     *
     * ⚠️ **FIVE IS A CHOICE AND IT IS THE ONE THE FAILURE MODE ARGUES FOR.** Too
     * narrow and ordinary English collides — *"in the heart of"* appears on
     * every page ever written, so a width of two would score honest pages as
     * duplicates. Too wide and the template swap `16` warns about slips
     * through: at width twelve, changing one city name per sentence breaks every
     * window that contains it. Five spans a clause, which is the unit a
     * template actually varies.
     */
    public const int WIDTH = 5;

    /**
     * How much of `$text` already appears in `$others`, as a whole percentage.
     *
     * ⚠️ **THE WORST NEIGHBOUR DECIDES, NOT THE AVERAGE.** A page duplicating
     * one existing page and sharing nothing with forty others would average to
     * an excellent score, and the duplicate is still a duplicate. `16`'s wording
     * is *"vs. every other page"*.
     *
     * ⚠️ **AN EMPTY CORPUS SCORES 100 AND THAT IS A FACT RATHER THAN A DEFAULT.**
     * The first page on a site repeats nothing, because there is nothing to
     * repeat.
     *
     * @param  iterable<int, string>  $others
     * @return int 0–100, where 100 is entirely new writing
     */
    public static function uniquenessPercent(string $text, iterable $others): int
    {
        $mine = self::of($text);

        if ($mine === []) {
            return 0;
        }

        $lookup = array_flip($mine);
        $worst = 0.0;

        foreach ($others as $other) {
            $shared = 0;

            foreach (self::of($other) as $shingle) {
                if (isset($lookup[$shingle])) {
                    $shared++;
                }
            }

            $worst = max($worst, $shared / count($mine));
        }

        // Rounded half-up, then subtracted, so a page that is 40.4% borrowed is
        // reported as 60% unique rather than as 59.6% and refused on a rounding
        // direction nobody chose.
        return 100 - (int) round($worst * 100);
    }

    /**
     * The distinct word windows in a text, in no particular order.
     *
     * ⚠️ **PUNCTUATION AND CASE ARE DISCARDED ON PURPOSE.** A duplicate page
     * with the commas moved is a duplicate page, and leaving punctuation in
     * would let a template escape the check by varying it.
     *
     * @return list<string>
     */
    public static function of(string $text): array
    {
        $words = self::words($text);

        if ($words === []) {
            return [];
        }

        // A text shorter than one window is one window. The alternative —
        // returning nothing — makes a two-word page infinitely unique.
        if (count($words) <= self::WIDTH) {
            return [implode(' ', $words)];
        }

        $shingles = [];

        for ($i = 0, $last = count($words) - self::WIDTH; $i <= $last; $i++) {
            $shingles[implode(' ', array_slice($words, $i, self::WIDTH))] = true;
        }

        return array_keys($shingles);
    }

    /**
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $lowered = mb_strtolower($text);

        // Letters, digits and apostrophes survive; everything else is a
        // separator. `u` so that a tenant writing in anything but ASCII is not
        // silently reduced to one enormous shingle.
        $split = preg_split("/[^\p{L}\p{N}']+/u", $lowered) ?: [];

        return array_values(array_filter($split, static fn (string $word): bool => $word !== ''));
    }
}
