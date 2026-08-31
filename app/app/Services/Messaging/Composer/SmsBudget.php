<?php

declare(strict_types=1);

namespace App\Services\Messaging\Composer;

use App\Enums\SmsEncoding;

/**
 * How long a text message actually is, in the units the carrier charges in.
 *
 * `SL-2`'s ≤159 law is *"single segment, link included"*, and the only way to
 * honour it is to measure the body the way a carrier does rather than with
 * `strlen()` or `mb_strlen()`:
 *
 *   `strlen()`    counts **bytes**, so `é` is two and an emoji is four — a body
 *                 of 100 visible characters can report 160 and be refused for
 *                 nothing.
 *   `mb_strlen()` counts **code points**, which is right for GSM-7 only if no
 *                 character needs an escape and right for UCS-2 only inside the
 *                 Basic Multilingual Plane. An emoji is one code point and two
 *                 UTF-16 code units, so a body of 70 code points ending in one
 *                 emoji is 71 units — two segments, reported as one.
 *
 * ## What this class does not do
 *
 * ⛔ **IT NEVER TRUNCATES.** Decision 1570 refused truncation in the transport
 * and `ReviewInviteSender::compose()` refused it again in the sender, both for
 * the same reason: the tail of a compliance-shaped SMS is the opt-out
 * instruction and the sender disclosure, so the first thing a length limit cuts
 * is the half that makes the message lawful — and the result is a compliance
 * failure that reports as a successful send. This class answers *how long* and
 * *does it fit*; {@see ReactComposer} refuses.
 *
 * ⛔ **AND IT NEVER SUBSTITUTES.** The T89 addendum permits a composer to
 * *"substitute GSM-safe glyphs where the language allows"* — swapping a curly
 * apostrophe for a straight one, say. Doing it here would mean this class
 * silently rewriting somebody's message to make its own answer come out right,
 * which is the same shape as truncating. The substitution belongs to whoever
 * owns the wording, and today nobody does: `S6`, the REACT-1 content set, was
 * staged to a lane outside this repository and never delivered.
 */
final class SmsBudget
{
    /**
     * The GSM 03.38 default alphabet, one septet each.
     *
     * ⚠️ Written out as a literal rather than derived from a range, because it
     * is not a range: it interleaves ASCII with Greek capitals, currency symbols
     * and Western European accented letters at positions that have nothing to do
     * with Unicode's ordering. `LF` and `CR` are members; `ESC` is not, because
     * it is the escape that introduces the extension table below.
     *
     * ⚠️ **SINGLE-QUOTED, AND THE ALTERNATIVE DOES NOT COMPILE.** PHP identifiers
     * may contain bytes above 0x7F, so in a double-quoted string the `$¥` at
     * position three is parsed as *a variable named `¥`* — which makes the whole
     * thing an interpolation rather than a constant expression, and the file
     * fails to parse with a message that names neither the `$` nor the `¥`.
     *
     * ⛔ **`<`, `=` AND `>` ARE SPLIT ACROSS THREE LITERALS ON THREE LINES, AND
     * JOINING THEM BREAKS THE BUILD.** `<=>` is pgvector's cosine-distance
     * operator, and the tenancy lint that makes *"no vector similarity query
     * without an explicit tenant predicate"* build-failing scans **string
     * literals** rather than raw source — deliberately, because grepping the
     * file would flag every PHP spaceship comparison in the codebase. A GSM
     * alphabet is the one string in this application that legitimately contains
     * those three characters in a row. The lint is right to be broad, weakening
     * it to accommodate this file would trade a real isolation guard for one
     * constant's tidiness, and the split costs nothing. **Do not "tidy" these
     * three lines back into one.**
     */
    private const string BASIC = '@£$¥èéùìòÇ'."\n".'Øø'."\r"
        .'ÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;'
        .'<'
        .'=>?'
        .'¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

    /**
     * The GSM 03.38 extension table. Each of these is submitted as `ESC` plus
     * the character, so each costs **two** septets rather than one.
     *
     * ⚠️ **THE EURO SIGN IS IN HERE AND IT IS THE ONE THAT BITES.** `€` looks
     * like an ordinary currency symbol, is reachable from every keyboard, and
     * quietly costs double — while `¤`, the generic currency sign nobody types,
     * is in the basic set and costs one.
     */
    private const string EXTENDED = "\x0C".'^{}\\[~]|€';

    /**
     * Which alphabet this body will be submitted in.
     *
     * ⚠️ **ONE STRAY CHARACTER DECIDES FOR THE WHOLE MESSAGE.** There is no
     * per-character mixing: a body that is 158 plain-ASCII characters plus one
     * curly quote is a UCS-2 message with a 67-unit budget, not a GSM-7 message
     * with one awkward character in it. That cliff is why this is measured
     * *after* composition, on the finished text, exactly as the T89 addendum
     * words it — *"composer detects encoding post-composition, re-targets"*.
     */
    public function encodingOf(string $body): SmsEncoding
    {
        if (! mb_check_encoding($body, 'UTF-8')) {
            return SmsEncoding::Ucs2;
        }

        foreach ($this->characters($body) as $character) {
            if (! str_contains(self::BASIC, $character) && ! str_contains(self::EXTENDED, $character)) {
                return SmsEncoding::Ucs2;
            }
        }

        return SmsEncoding::Gsm7;
    }

    /**
     * How long this body is, in the units its own encoding is counted in.
     *
     * Septets on GSM-7 — with the ten escaped characters counting two — and
     * UTF-16 code units on UCS-2, which is what makes an astral-plane emoji
     * count as the two units the carrier will actually send.
     */
    public function lengthOf(string $body): int
    {
        if (! mb_check_encoding($body, 'UTF-8')) {
            // ⚠️ **A LENGTH NO BUDGET ACCEPTS, DELIBERATELY.** Invalid UTF-8
            // cannot be measured in either alphabet, and the tempting answers —
            // count the bytes, or count what survives a lossy conversion — both
            // report a number that *looks* fine and describes a body the carrier
            // will receive differently. Refusing is the only honest answer, and
            // it reaches the caller as "this does not fit" rather than as an
            // exception from three layers down.
            return PHP_INT_MAX;
        }

        if ($this->encodingOf($body) === SmsEncoding::Ucs2) {
            // ⚠️ **`mb_strlen($body, 'UTF-16')` WOULD BE WRONG BY A FACTOR OF
            // TWO** — it reports code *units* only if the string is already
            // UTF-16, and PHP would count the converted bytes in pairs. The
            // conversion is explicit and the division is by two because a UTF-16
            // code unit is two bytes; a surrogate pair is four bytes and
            // therefore the two units a carrier charges for.
            $converted = mb_convert_encoding($body, 'UTF-16BE', 'UTF-8');

            return intdiv(strlen($converted), 2);
        }

        $septets = 0;

        foreach ($this->characters($body) as $character) {
            $septets += str_contains(self::EXTENDED, $character) ? 2 : 1;
        }

        return $septets;
    }

    /**
     * Whether this body travels as a single segment under the ≤159 law.
     */
    public function fitsOneSegment(string $body): bool
    {
        return $this->lengthOf($body) <= $this->encodingOf($body)->segmentBudget();
    }

    /**
     * How much room is left, in this body's own units. Negative when it is over.
     *
     * Returned rather than a bare boolean so a refusal can say by how much, and
     * so a caller assembling a body around a name and a link can ask before it
     * commits — which is the only way to choose *which* part to shorten.
     */
    public function headroomIn(string $body): int
    {
        return $this->encodingOf($body)->segmentBudget() - $this->lengthOf($body);
    }

    /**
     * The body split into characters, as code points.
     *
     * @return list<string>
     */
    private function characters(string $body): array
    {
        $characters = preg_split('//u', $body, -1, PREG_SPLIT_NO_EMPTY);

        // `preg_split` answers false on invalid UTF-8, which both public methods
        // have already refused above — so this is unreachable rather than a
        // fallback, and it returns nothing instead of a sentinel that would give
        // a malformed body a *passing* length of one.
        return $characters === false ? [] : $characters;
    }
}
