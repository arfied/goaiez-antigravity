<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use Random\RandomException;

/**
 * The boundary between our instructions and a stranger's 2,000 characters.
 *
 * WHAT WENT WRONG WITH THE FIRST VERSION, BECAUSE IT IS THE WHOLE POINT OF THIS
 * CLASS. The fence used to be a fixed public constant, `<<<REVIEW_TEXT>>>`, and
 * the comment was interpolated between two copies of it verbatim. Its docblock
 * said the marker "cannot be closed early because there is nothing to close —
 * the same marker opens and shuts". The symmetry is exactly what makes it
 * closable: a review whose text contains `<<<REVIEW_TEXT>>>` supplies the third
 * marker itself, and everything after it reads as ours rather than theirs — on
 * the call that decides whether that same text publishes. A protection layer
 * asserted before it was true is decisions 314–316's pattern, on the branch that
 * rewrote that lesson into three files.
 *
 * TWO REPAIRS WERE AVAILABLE AND THIS IS THE OTHER ONE. Stripping the marker out
 * of the customer's text is the obvious fix and it is the worse one:
 *
 *   - It edits what the customer wrote. The text we send is the evidence the
 *     verdict is made on, and a silent edit means the model judged something the
 *     person did not write.
 *   - A single pass is not even correct. `<<<REVIEW_<<<REVIEW_TEXT>>>TEXT>>>`
 *     re-forms the marker when the inner copy is removed, so the strip has to
 *     repeat until stable — the classic sanitiser shape, where the bug is always
 *     the input nobody thought of.
 *
 * Minting the marker per request has neither problem. The text is passed through
 * byte-identical, and an attacker who never sees the prompt cannot type a string
 * they cannot predict. 128 bits of entropy from `random_bytes()` — the CSPRNG,
 * not `rand()`, because guessability is the entire property.
 *
 * AND THE PROBABILISTIC GAP IS CLOSED RATHER THAN ACCEPTED. `around()` re-mints
 * while the text contains the marker, so the guarantee this class actually makes
 * is not "a collision is unlikely" but "the fenced text does not contain the
 * marker, checked, on every call". That is a property a test can assert, which
 * is the difference between this docblock and the one it replaces.
 */
final class PromptFence
{
    private function __construct(
        public readonly string $marker,
    ) {}

    /**
     * A marker none of these texts provably contains.
     *
     * ⚠️ VARIADIC, AND THAT IS NOT A CONVENIENCE (1727). A prompt usually has
     * more than one stranger's words in it: `ReplyGenerator` interpolated the
     * reviewer's **display name** — which the reviewer chooses — the business
     * name, the location name and tenant-authored template bodies raw, while
     * fencing only the comment. A fence minted over one input and a prompt that
     * carries four is a fence around the input nobody was attacking. Mint over
     * every untrusted value and wrap each of them.
     *
     * @throws RandomException when the platform has no source of randomness — a
     *                         broken CSPRNG must stop the call, never fall back
     *                         to a guessable marker.
     */
    public static function around(string ...$texts): self
    {
        do {
            $marker = '<<<'.strtoupper(bin2hex(random_bytes(16))).'>>>';

            $collides = false;

            foreach ($texts as $text) {
                if (str_contains($text, $marker)) {
                    $collides = true;

                    break;
                }
            }
        } while ($collides);

        return new self($marker);
    }

    /**
     * The text, unedited, between one opening and one closing marker.
     */
    public function wrap(string $text): string
    {
        return $this->marker."\n".$text."\n".$this->marker;
    }
}
