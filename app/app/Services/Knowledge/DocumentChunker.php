<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Services\Config\DefaultsRegistry;

/**
 * Splits a document into overlapping slices small enough to embed.
 *
 * DETERMINISTIC AND PURE, WITH NO DATABASE AND NO VENDOR. The same text always
 * produces the same chunks in the same order, which is what makes `checksum` on
 * `knowledge_sources` mean anything: a re-upload of an unchanged file can be
 * recognised and skipped rather than re-embedded, and that is the difference the
 * table's own migration says it exists for.
 *
 * ⚠️ **CHARACTERS, NOT TOKENS, AND THE DIFFERENCE IS DECLARED RATHER THAN
 * HIDDEN.** A true token count needs the vendor's tokeniser, which is a
 * dependency this slice is not adding, so the size limit is in characters and
 * `token_count` is stored as an estimate. **The estimate is never the billing
 * figure** — `ai_calls.input_tokens` comes from the vendor's own `usage` block,
 * and the two must not be conflated. A cap enforced against a guess is not a cap.
 *
 * ⚠️ **THE OVERLAP IS THE WHOLE POINT AND IT IS NOT FREE.** Retrieval returns a
 * chunk, not a document, so an answer that straddles a boundary is an answer
 * nobody gets — the sentence "we are open until 6pm on Saturdays" split between
 * two chunks retrieves as two half-facts. The cost is that overlapping text is
 * embedded twice and stored twice, which is a real bill on a large corpus. The
 * ratio below is the trade, stated so the next person can argue with it.
 *
 * ⚠️ **PARAGRAPHS FIRST, THEN A HARD SPLIT.** Splitting on blank lines keeps
 * whole thoughts together where a document has them; a paragraph longer than the
 * limit is then cut on a word boundary rather than mid-word, because a chunk
 * ending in "…we close at 6p" embeds a token nobody wrote. A document with no
 * blank lines at all — the common case for a pasted FAQ — falls straight to the
 * hard split, which is why that path is not an edge case.
 */
final class DocumentChunker
{
    /**
     * The largest chunk we will produce, in characters.
     *
     * Sized well under the model's 8,191-token input ceiling rather than up
     * against it: a chunk that fills the window retrieves as a wall of text the
     * conversation lane then has to trim, and retrieval quality falls as chunks
     * grow because the vector averages more subjects together.
     */
    public const int MAX_CHARACTERS = 1_200;

    /**
     * How much of the previous chunk each new one repeats.
     *
     * Roughly a long sentence. Enough to carry a fact across a boundary; small
     * enough that the duplication is a rounding error on the bill rather than a
     * second copy of the document.
     */
    public const int OVERLAP_CHARACTERS = 150;

    /**
     * ⚠️ **A CHUNK THIS SHORT IS NOISE AND IT ACTIVELY HARMS RETRIEVAL.** A
     * two-word fragment left over from a split embeds to a vector that is near
     * everything, so it surfaces against unrelated questions and pushes a real
     * answer out of the result set. Dropped rather than merged, because merging
     * it into a neighbour would change that neighbour's text and break the
     * determinism the checksum depends on.
     */
    public const int MINIMUM_CHARACTERS = 40;

    private function overlapCharacters(): int
    {
        return app(DefaultsRegistry::class)->int('knowledge.chunker.overlap_characters');
    }

    private function minimumCharacters(): int
    {
        return app(DefaultsRegistry::class)->int('knowledge.chunker.minimum_characters');
    }

    /**
     * Characters per token, for the stored estimate only.
     *
     * The commonly cited English figure. Deliberately a named constant so that
     * nobody reads `intdiv($length, 4)` at a call site and takes it for a real
     * count — see the class docblock.
     */
    private const int CHARACTERS_PER_TOKEN = 4;

    /**
     * @return list<string>
     */
    public function chunk(string $text): array
    {
        $normalised = $this->normalise($text);

        if ($normalised === '') {
            return [];
        }

        $chunks = [];

        foreach ($this->paragraphs($normalised) as $paragraph) {
            foreach ($this->split($paragraph) as $piece) {
                $chunks[] = $piece;
            }
        }

        return array_values(array_filter(
            $chunks,
            fn (string $chunk): bool => mb_strlen($chunk) >= $this->minimumCharacters(),
        ));
    }

    /**
     * An approximate token count for one chunk. Never a billing figure.
     */
    public function estimateTokens(string $chunk): int
    {
        return max(1, intdiv(mb_strlen($chunk), self::CHARACTERS_PER_TOKEN));
    }

    /**
     * Collapse line endings and runs of blank lines, and trim.
     *
     * ⚠️ NORMALISATION IS PART OF THE CHECKSUM'S CONTRACT. The same document
     * saved on Windows and on macOS differs by a `\r` on every line, and without
     * this the two would checksum differently and re-embed a corpus for nothing.
     */
    private function normalise(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Runs of three or more newlines collapse to a paragraph break. Two is
        // already the break; more is somebody's spacing.
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        // Trailing spaces before a newline are invisible and would otherwise
        // survive into a chunk and into its vector.
        $text = (string) preg_replace('/[ \t]+\n/', "\n", $text);

        return trim($text);
    }

    /**
     * @return list<string>
     */
    private function paragraphs(string $text): array
    {
        $parts = preg_split("/\n{2,}/", $text) ?: [];

        return array_values(array_filter(
            array_map(trim(...), $parts),
            static fn (string $part): bool => $part !== '',
        ));
    }

    /**
     * One paragraph, cut on word boundaries with overlap.
     *
     * @return list<string>
     */
    private function split(string $paragraph): array
    {
        if (mb_strlen($paragraph) <= self::MAX_CHARACTERS) {
            return [$paragraph];
        }

        $chunks = [];
        $offset = 0;
        $length = mb_strlen($paragraph);

        while ($offset < $length) {
            $window = mb_substr($paragraph, $offset, self::MAX_CHARACTERS);

            // Cut back to the last space, unless this is the final window (in
            // which case there is nothing after it to lose) or there is no space
            // at all (a single enormous word — a URL, a hash — which is cut
            // where it is rather than growing the chunk without limit).
            if ($offset + self::MAX_CHARACTERS < $length) {
                $lastSpace = mb_strrpos($window, ' ');

                if ($lastSpace !== false && $lastSpace > $this->overlapCharacters()) {
                    $window = mb_substr($window, 0, $lastSpace);
                }
            }

            $chunks[] = trim($window);

            $reached = $offset + mb_strlen($window);

            // ⚠️ **STOP WHEN THE WINDOW HAS REACHED THE END, NOT WHEN THE OFFSET
            // HAS.** This condition was originally `while ($offset < $length)`
            // alone, and the difference is not cosmetic — it was a real defect
            // this slice's own test caught. Once the remaining tail is shorter
            // than the overlap, the step goes to zero or negative, the
            // non-advancing guard below moves the offset by **one character**,
            // and the loop emits a fresh chunk per character: a 3,600-character
            // paragraph with no spaces produced **115 chunks**, of which 111 were
            // near-duplicate tails. Not an infinite loop — the guard did its job
            // — but 111 unnecessary vectors bought and stored for one paragraph,
            // and 111 near-identical rows crowding real answers out of every
            // result set. The tail is already inside the previous chunk, so
            // there is nothing left to emit.
            if ($reached >= $length) {
                break;
            }

            // ⚠️ THE NON-ADVANCING GUARD, KEPT AS A BACKSTOP. With the break
            // above it is no longer reachable by the path that found it — a
            // short window now ends the loop — but a future change to the
            // cut-back could reintroduce a zero step, and the cost of an
            // unreachable `max()` is nothing against a queue worker spinning on
            // a tenant's own upload.
            $offset += max(1, mb_strlen($window) - $this->overlapCharacters());
        }

        return $chunks;
    }
}
