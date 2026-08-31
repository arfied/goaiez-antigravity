<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DiffLineKind;

/**
 * A line-level diff between two texts, written here rather than installed.
 *
 * ⛔ **NO PACKAGE FOR THIS.** `CLAUDE.md` forbids a new dependency without
 * approval, and the thing a diff library buys — word-level highlighting, unified
 * output, patch application — is not what the one caller needs. What it needs is
 * "which lines of the published text did this draft change", on documents of a
 * few hundred lines, on an admin screen. That is the fifty lines below.
 *
 * ⚠️ **IT IS A LINE DIFF AND THE WORD "LINE" IS LOAD-BEARING.** A legal document
 * is usually one paragraph per line, so a single retyped comma renders as the
 * whole paragraph removed and the whole paragraph added. That is honest — the
 * paragraph did change — and it is why the screen prints line numbers rather
 * than only a count: the number is what sends a reader to the place.
 *
 * ⚠️ **THE COST IS BOUNDED AND THE BOUND IS VISIBLE IN THE OUTPUT.** The longest
 * common subsequence is quadratic, so a pathological pair of texts would build a
 * table of tens of millions of cells on a web request. The common head and tail
 * are trimmed first, which is what collapses a real edit to a handful of lines,
 * and what is left is refused above {@see self::MAX_CELLS} — refused into an
 * honest whole-block replacement rather than into an exception, because a diff
 * that cannot be computed cheaply must still let somebody publish their
 * document.
 */
final class LineDiff
{
    /**
     * The largest LCS table this will build, after the common head and tail are
     * trimmed away.
     *
     * 250,000 is two texts of 500 differing lines each — far past any legal
     * document in `database/seeders/legal`, and about ten megabytes of PHP
     * array. Beyond it the two middles are reported as one block removed and one
     * block added, which is what a person would say about two texts with nothing
     * in common anyway.
     */
    public const int MAX_CELLS = 250_000;

    /**
     * The diff from `$before` to `$after`, in reading order.
     *
     * @return list<DiffLine>
     */
    public static function between(string $before, string $after): array
    {
        $a = self::lines($before);
        $b = self::lines($after);

        $head = self::commonPrefixLength($a, $b);
        $tail = self::commonSuffixLength($a, $b, $head);

        $middleA = array_slice($a, $head, count($a) - $head - $tail);
        $middleB = array_slice($b, $head, count($b) - $head - $tail);

        $diff = [];

        for ($i = 0; $i < $head; $i++) {
            $diff[] = new DiffLine(DiffLineKind::Unchanged, $a[$i], $i + 1, $i + 1);
        }

        foreach (self::middle($middleA, $middleB, $head) as $line) {
            $diff[] = $line;
        }

        for ($i = 0; $i < $tail; $i++) {
            $diff[] = new DiffLine(
                DiffLineKind::Unchanged,
                $a[count($a) - $tail + $i],
                count($a) - $tail + $i + 1,
                count($b) - $tail + $i + 1,
            );
        }

        return $diff;
    }

    /**
     * The rows that are not identical in both texts.
     *
     * @param  list<DiffLine>  $diff
     * @return list<DiffLine>
     */
    public static function changes(array $diff): array
    {
        return array_values(array_filter(
            $diff,
            static fn (DiffLine $line): bool => $line->kind !== DiffLineKind::Unchanged,
        ));
    }

    /**
     * How many rows of each kind the diff holds.
     *
     * @param  list<DiffLine>  $diff
     * @return array{added:int,removed:int,unchanged:int}
     */
    public static function totals(array $diff): array
    {
        $totals = ['added' => 0, 'removed' => 0, 'unchanged' => 0];

        foreach ($diff as $line) {
            $totals[$line->kind->value]++;
        }

        return $totals;
    }

    /**
     * The differing middles, diffed against each other.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<DiffLine>
     */
    private static function middle(array $a, array $b, int $offset): array
    {
        if ($a === [] && $b === []) {
            return [];
        }

        if ($a === [] || $b === [] || (count($a) + 1) * (count($b) + 1) > self::MAX_CELLS) {
            return [
                ...self::block(DiffLineKind::Removed, $a, $offset),
                ...self::block(DiffLineKind::Added, $b, $offset),
            ];
        }

        return self::walk($a, $b, self::table($a, $b), $offset);
    }

    /**
     * One whole middle, reported as added or removed in a block.
     *
     * @param  list<string>  $lines
     * @return list<DiffLine>
     */
    private static function block(DiffLineKind $kind, array $lines, int $offset): array
    {
        $rows = [];

        foreach ($lines as $index => $text) {
            $number = $offset + $index + 1;

            $rows[] = $kind === DiffLineKind::Removed
                ? new DiffLine($kind, $text, $number, null)
                : new DiffLine($kind, $text, null, $number);
        }

        return $rows;
    }

    /**
     * The longest-common-subsequence table, flattened.
     *
     * One flat array rather than an array of arrays: the same numbers in a
     * quarter of the memory, and the index arithmetic is the only thing a reader
     * has to hold, which is cheaper than a nested structure at this size.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return array<int, int>
     */
    private static function table(array $a, array $b): array
    {
        $rows = count($a);
        $columns = count($b);
        $width = $columns + 1;

        $table = array_fill(0, ($rows + 1) * $width, 0);

        for ($i = $rows - 1; $i >= 0; $i--) {
            for ($j = $columns - 1; $j >= 0; $j--) {
                $table[$i * $width + $j] = $a[$i] === $b[$j]
                    ? $table[($i + 1) * $width + $j + 1] + 1
                    : max($table[($i + 1) * $width + $j], $table[$i * $width + $j + 1]);
            }
        }

        return $table;
    }

    /**
     * Read the table forwards, emitting one row per line.
     *
     * ⚠️ **REMOVED BEFORE ADDED ON A TIE, ALWAYS.** Where neither direction is
     * longer the walk has a free choice, and taking the same one every time is
     * what makes a replaced paragraph render as "here is what it said, here is
     * what it says now" rather than the two interleaved.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @param  array<int, int>  $table
     * @return list<DiffLine>
     */
    private static function walk(array $a, array $b, array $table, int $offset): array
    {
        $rows = count($a);
        $columns = count($b);
        $width = $columns + 1;

        $diff = [];
        $i = 0;
        $j = 0;

        while ($i < $rows && $j < $columns) {
            if ($a[$i] === $b[$j]) {
                $diff[] = new DiffLine(DiffLineKind::Unchanged, $a[$i], $offset + $i + 1, $offset + $j + 1);
                $i++;
                $j++;

                continue;
            }

            if ($table[($i + 1) * $width + $j] >= $table[$i * $width + $j + 1]) {
                $diff[] = new DiffLine(DiffLineKind::Removed, $a[$i], $offset + $i + 1, null);
                $i++;

                continue;
            }

            $diff[] = new DiffLine(DiffLineKind::Added, $b[$j], null, $offset + $j + 1);
            $j++;
        }

        while ($i < $rows) {
            $diff[] = new DiffLine(DiffLineKind::Removed, $a[$i], $offset + $i + 1, null);
            $i++;
        }

        while ($j < $columns) {
            $diff[] = new DiffLine(DiffLineKind::Added, $b[$j], null, $offset + $j + 1);
            $j++;
        }

        return $diff;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function commonPrefixLength(array $a, array $b): int
    {
        $limit = min(count($a), count($b));
        $length = 0;

        while ($length < $limit && $a[$length] === $b[$length]) {
            $length++;
        }

        return $length;
    }

    /**
     * ⚠️ **THE HEAD IS SUBTRACTED FIRST, OR THE TWO OVERLAP.** Two identical
     * texts would otherwise report their whole length as both prefix and suffix,
     * and the slice between them would run backwards.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function commonSuffixLength(array $a, array $b, int $head): int
    {
        $limit = min(count($a), count($b)) - $head;
        $length = 0;

        while (
            $length < $limit
            && $a[count($a) - 1 - $length] === $b[count($b) - 1 - $length]
        ) {
            $length++;
        }

        return $length;
    }

    /**
     * The text as lines, with the three line endings treated alike.
     *
     * ⚠️ **A TRAILING NEWLINE IS NOT A BLANK LAST LINE.** A textarea posts
     * `\r\n` and a seeded file ends in `\n`, so without this every document
     * would differ from every other by one phantom empty row at the bottom —
     * and the screen would report a change nobody made on a draft nobody had
     * touched.
     *
     * @return list<string>
     */
    private static function lines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\n|\r/', $text);

        if ($lines === false) {
            return [$text];
        }

        if (end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }
}
