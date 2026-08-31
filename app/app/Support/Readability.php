<?php

declare(strict_types=1);

namespace App\Support;

/**
 * How much schooling a reader needs to get through a piece of writing, on the
 * Flesch–Kincaid grade-level scale.
 *
 * `29` §9.1 asks generated content for a *"target reading level 8"* and doc `33`
 * says the same as a gate line — *"readability grade ≤8 for consumer topics"*.
 * `28` §7.2's stricter ≤6 is a different rule about a different thing: the
 * simple-language gate over **owner-facing report copy**, which is not what this
 * measures and must not be conflated with it.
 *
 *     0.39 × (words ÷ sentences) + 11.8 × (syllables ÷ words) − 15.59
 *
 * ⚠️ **THE NUMBER IS A GRADE, SO LOWER IS BETTER, AND THE COLUMN IT LANDS IN IS
 * CALLED `readability_score`.** `DATA-MODEL.md` §5.11 names it that and the name
 * reads as though bigger were better; it is not, and the gate's rule is `≤`.
 * Written here, in the model, and back into `DATA-MODEL.md`, because a reader
 * who assumes the other direction inverts the whole check and every fixture
 * still passes.
 *
 * ⛔ **NO PACKAGE, AND NO PRETENCE OF PRECISION** (`LineDiff`'s precedent).
 * English syllable counting has no exact algorithm — the vowel-group heuristic
 * below is the one every implementation of this formula uses, and it is wrong
 * about "queue", "business" and every proper noun. That is tolerable because the
 * gate compares against a whole grade rather than a decimal, and because a page
 * that lands within one grade of the ceiling is a page nobody should be
 * confident about either way. **It is not tolerable to describe this as
 * accurate**, which is why it says so here rather than in a commit message.
 */
final class Readability
{
    /**
     * The Flesch–Kincaid grade level of a text, rounded to a whole grade.
     *
     * ⚠️ **FLOORED AT ZERO.** The formula goes negative on writing simpler than
     * its scale describes — three-word sentences of one-syllable words — and a
     * negative grade is not a reading level, it is the arithmetic running out of
     * scale. Zero is the honest bottom.
     *
     * ⚠️ **NULL WHEN THERE IS NOTHING TO MEASURE**, on `AuditScore`'s rule: zero
     * means "we looked and it is trivially easy", null means "there were no
     * words". Collapsing the second into the first would pass an empty page
     * through the readability line of a gate whose whole job is to refuse thin
     * ones.
     */
    public static function gradeLevel(string $text): ?int
    {
        $words = self::words($text);

        if ($words === []) {
            return null;
        }

        $sentences = max(1, self::sentenceCount($text));

        $syllables = 0;

        foreach ($words as $word) {
            $syllables += self::syllables($word);
        }

        $grade = 0.39 * (count($words) / $sentences)
            + 11.8 * ($syllables / count($words))
            - 15.59;

        return max(0, (int) round($grade));
    }

    /**
     * Syllables in one word, by the vowel-group heuristic.
     *
     * ⚠️ **NEVER ZERO.** A word made entirely of consonants — an initialism, a
     * brand — still takes breath to say, and returning zero for it would drag a
     * whole page's average below the scale.
     */
    public static function syllables(string $word): int
    {
        $letters = preg_replace('/[^a-z]/', '', mb_strtolower($word)) ?? '';

        if ($letters === '') {
            return 1;
        }

        // A trailing silent "e" is the one exception worth encoding: "make" is
        // one syllable and the vowel groups say two. "le" after a consonant is
        // not silent — "table" — so it is left alone.
        if (str_ends_with($letters, 'e')
            && ! preg_match('/[^aeiou]le$/', $letters)) {
            $letters = substr($letters, 0, -1);
        }

        preg_match_all('/[aeiouy]+/', $letters, $groups);

        return max(1, count($groups[0]));
    }

    /**
     * ⚠️ **A RUN OF TERMINATORS IS ONE SENTENCE.** "Really?!" ends one sentence,
     * and counting two would halve the average sentence length and report a page
     * as easier than it reads.
     */
    private static function sentenceCount(string $text): int
    {
        preg_match_all('/[.!?]+/u', $text, $matches);

        return count($matches[0]);
    }

    /**
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $split = preg_split("/[^\p{L}\p{N}']+/u", $text) ?: [];

        return array_values(array_filter($split, static fn (string $word): bool => $word !== ''));
    }
}
