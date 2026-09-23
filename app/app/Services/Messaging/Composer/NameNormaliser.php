<?php

declare(strict_types=1);

namespace App\Services\Messaging\Composer;

use App\Services\Config\DefaultsRegistry;

/**
 * `name.normalizer` — the greeting name for one contact, or nothing at all.
 *
 * `SL-2`: *"`react.composer` ≤159 law + `name.normalizer`"*. The two ship
 * together because they are one problem seen from two ends — the composer owns
 * how much room there is, and this owns what may go in it.
 *
 * ## Nothing is the most important answer this returns
 *
 * A reactivation text opening *"Hi CUSTOMER,"*, *"Hi N/A,"* or *"Hi
 * bob@example.com,"* is worse than one that opens with no name, and there is no
 * screen anywhere that would have shown somebody the difference before it went
 * out to a list. So every rule below fails towards **null**, and
 * {@see ReactComposer} has a nameless rendering that is a real sentence rather
 * than a fallback with a hole in it.
 *
 * ⛔ **A NAME IS UNTRUSTED CONTENT AND THIS IS WHERE THAT IS ENFORCED.** The
 * strings this reads arrive from a customer typing into a public feedback form
 * and from spreadsheets a tenant uploaded, and they are about to be interpolated
 * into a marketing SMS sent over the **GOAIEZ** 10DLC brand from our own number
 * pool (dedicated number pool, decision 2101). A `name` cell holding `http://cheap-pills.example`
 * would put a second link in a message the ≤159 law allows exactly one of — and
 * a multi-link SMS is the shape carriers filter, which fails silently and
 * accrues to the platform's reputation across every tenant at once. Rejecting
 * anything that does not look like a name is therefore not tidiness.
 *
 * ⚠️ **IT DOES NOT WRITE ANYTHING BACK.** A tidier spelling of somebody's name
 * is this class's opinion, not a fact about them, and `customers.name` is the
 * one the owner sees on the contact profile. `CustomerImports::upsertCustomer()`
 * already refuses to let a spreadsheet rename anybody; this refuses for the same
 * reason one layer further out.
 */
final class NameNormaliser
{
    /**
     * Longer than this and it is not a first name.
     *
     * The longest first names in ordinary use are around fifteen characters;
     * this leaves generous room and still refuses the free-text case — a whole
     * company name, an address, or a sentence somebody typed into the wrong box
     * — before it can eat a budget that only has 159 units in it.
     */
    public const int MAX_LENGTH = 24;

    public function maxLength(): int
    {
        return app(DefaultsRegistry::class)->int('messaging.composer.name_max_length');
    }

    /**
     * The strings that are in the name column and are not names.
     *
     * ⚠️ **PLACEHOLDERS AND HONORIFICS ARE THE SAME PROBLEM HERE**, though they
     * arrive differently: `customer` is what an import wrote when the cell was
     * empty, and `mr` is what survives when a title was typed ahead of a name
     * this class has already split away. Both produce a greeting addressed to
     * nobody.
     *
     * ⚠️ **`friend` AND `guest` ARE ON IT DELIBERATELY.** They read as
     * personalisation and are the exact words a previous system substituted when
     * *it* had no name — so letting one through republishes another product's
     * fallback as though it were this one's data.
     *
     * @var list<string>
     */
    public const array NOT_A_NAME = [
        'client', 'clients', 'customer', 'customers', 'consumer', 'contact', 'friend', 'guest',
        'member', 'na', 'n/a', 'nil', 'none', 'null', 'patient', 'resident', 'sir', 'madam',
        'test', 'testing', 'tbd', 'unknown', 'undefined', 'user', 'valued', 'visitor',
        'dr', 'mr', 'mrs', 'ms', 'miss', 'mx', 'prof', 'rev', 'sr', 'jr',
    ];

    /**
     * The greeting name for this contact, or null when there is not one.
     *
     * @param  ?string  $name  `customers.name` exactly as stored — the full
     *                         name, a single name, an empty cell, or whatever
     *                         else a spreadsheet contained.
     */
    public function firstName(?string $name): ?string
    {
        $candidate = $this->collapse($name);

        if ($candidate === null || $this->looksLikeSomethingElse($candidate)) {
            return null;
        }

        $token = $this->leadingToken($candidate);

        if ($token === null) {
            return null;
        }

        // ⚠️ **THE LENGTH IS CHECKED ON THE TOKEN, NOT ON THE WHOLE FIELD.**
        // `Bartholomew Fitzwilliam-Featherstonehaugh` is a perfectly ordinary
        // full name and a hopeless greeting; checking the field would refuse it
        // outright, and checking the token keeps `Bartholomew`.
        if (mb_strlen($token) < 2 || mb_strlen($token) > $this->maxLength()) {
            return null;
        }

        return $this->cased($token);
    }

    /**
     * Trim, collapse runs of whitespace, and strip anything unprintable.
     *
     * ⚠️ **THE CONTROL-CHARACTER STRIP IS NOT COSMETIC.** A newline inside a
     * name would break the composed message into lines the author never wrote,
     * and a zero-width or bidirectional-override character is invisible in every
     * screen that would review this and visible on the handset.
     */
    private function collapse(?string $name): ?string
    {
        if ($name === null || ! mb_check_encoding($name, 'UTF-8')) {
            return null;
        }

        $stripped = preg_replace('/[\p{C}\p{Z}]+/u', ' ', $name);

        if ($stripped === null) {
            return null;
        }

        $stripped = trim($stripped);

        return $stripped === '' ? null : $stripped;
    }

    /**
     * Whether the whole field is something other than a person's name.
     *
     * Asked of the **whole** field rather than the token, because that is where
     * the evidence is: `bob@example.com` splits to a first token of
     * `bob@example.com`, but `Call 8005551234 now` splits to `Call`, which looks
     * like a name and is not.
     */
    private function looksLikeSomethingElse(string $candidate): bool
    {
        // An address, a link, or a markup-ish payload. `{` and `}` are here
        // because ReactComposer's own placeholders use them, and a name
        // containing `{link}` would otherwise be substituted into by a later
        // pass — untrusted data rewriting the template that renders it.
        if (preg_match('~[@<>{}\[\]|\\\\]|://|\bwww\.~u', $candidate) === 1) {
            return true;
        }

        // A run of digits long enough to be a phone number, an order number or
        // an account reference. Four is deliberately below the ten a US number
        // has: nothing that belongs in a greeting carries four consecutive
        // digits, and the ways a number arrives partially formatted are endless.
        if (preg_match('/\d{4}/u', $candidate) === 1) {
            return true;
        }

        // Nothing to say. A field of punctuation collapses to a token that
        // passes every other check and greets somebody as `--`.
        return preg_match('/\p{L}/u', $candidate) !== 1;
    }

    /**
     * The token to greet with.
     *
     * ⚠️ **`Smith, John` IS THE COMMON EXPORT SHAPE AND ITS FIRST TOKEN IS THE
     * SURNAME.** A CRM export, a booking system and a spreadsheet somebody
     * sorted all produce it, so taking the leading token unconditionally
     * addresses a stranger by their family name — which reads, precisely, like a
     * cold marketing message from somebody who does not know them.
     */
    private function leadingToken(string $candidate): ?string
    {
        if (str_contains($candidate, ',')) {
            $after = trim((string) mb_strstr($candidate, ',', false));
            $after = trim(mb_substr($after, 1));

            if ($after !== '') {
                $candidate = $after;
            }
        }

        foreach (explode(' ', $candidate) as $token) {
            $token = $this->stripEdgePunctuation($token);

            if ($token === '' || in_array(mb_strtolower($token), self::NOT_A_NAME, true)) {
                // A title, a placeholder, or punctuation that survived. Skipped
                // rather than refused, so `Dr. Jane Doe` still greets Jane —
                // and a field that is *only* titles runs out of tokens and
                // returns null below, which is the right answer for `Mr.`.
                continue;
            }

            if (preg_match('/\p{L}/u', $token) !== 1) {
                continue;
            }

            return $token;
        }

        return null;
    }

    /**
     * Strip the punctuation a name never starts or ends with.
     *
     * Interior punctuation survives, because `O'Brien` and `Anne-Marie` need it.
     */
    private function stripEdgePunctuation(string $token): string
    {
        $trimmed = preg_replace('/^[^\p{L}]+|[^\p{L}]+$/u', '', $token);

        return $trimmed ?? '';
    }

    /**
     * Present the token the way somebody would write it.
     *
     * ⚠️ **A TOKEN THAT IS ALREADY MIXED-CASE IS LEFT ALONE, AND THAT IS THE
     * RULE THAT MATTERS.** `DeShawn`, `McDonald`, `van Dijk` and `iolanda` are
     * not all the same problem: the first three carry deliberate capitals that a
     * blind `ucfirst(strtolower())` destroys, and destroying somebody's name in
     * a message addressed to them is worse than leaving a shouted one shouted.
     * So casing is applied only to the two shapes that carry no information —
     * ALL CAPS and all lower.
     *
     * ⛔ **AND `mb_convert_case(..., MB_CASE_TITLE)` IS NOT USED**, because it
     * lowercases after every non-letter: it turns `MCDONALD` into `Mcdonald`
     * (fine) and `O'BRIEN` into `O'Brien` (fine) but also `DeSHAWN` into
     * `Deshawn` — and the reason those disagree is only reachable by reading
     * ICU's rules. The two branches here are the whole behaviour.
     */
    private function cased(string $token): string
    {
        $lower = mb_strtolower($token);
        $upper = mb_strtoupper($token);

        if ($token !== $lower && $token !== $upper) {
            return $token;
        }

        return $this->titleCase($lower);
    }

    /**
     * Capitalise the first letter, and the letter after each hyphen or
     * apostrophe — `anne-marie` to `Anne-Marie`, `o'brien` to `O'Brien`.
     */
    private function titleCase(string $lower): string
    {
        $out = '';
        $capitalise = true;

        foreach (preg_split('//u', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $out .= $capitalise ? mb_strtoupper($character) : $character;
            $capitalise = $character === '-' || $character === "'" || $character === '’';
        }

        return $out;
    }
}
