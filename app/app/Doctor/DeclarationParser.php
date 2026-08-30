<?php

declare(strict_types=1);

namespace App\Doctor;

/**
 * The ONE parser for an `@declaration` line. Everything reads through this.
 *
 * ⛔⛔⛔ WHY THIS CLASS EXISTS — MEASURED, NOT THEORETICAL.
 *
 * Headers carry provenance notes INSIDE declaration lines:
 *
 *   @emits `approval.requested` ⭐ *(2026-08-27 — the `send.requested` shape…)*
 *   @owns_table messages ⛔ *(X-121 owns `messages` — C-Sms reads them)*
 *
 * A parser that keeps the note reads the EXPLANATION as a DECLARATION:
 *   · 4 modules were credited with emitting `send.requested` — they do not
 *   · `C-Sms` would be recorded as OWNING `messages`, a canonical noun it must
 *     never own (P-163), because its note says so while denying it
 *
 * Measured on the real plan: 892 declaration lines, 339 carry a note, and
 * 35 of those notes contain a token a naive parser reads as real.
 *
 * ⭐⭐ Each tool having its own regex is what let this diverge. One parser means
 * one place to fix, and a bug found by `impact` is fixed for `doctor`,
 * `brief`, `map` and the scaffold at the same moment.
 */
final class DeclarationParser
{
    /**
     * Strip everything that is commentary, leaving only declared values.
     *
     * ⛔ ORDER MATTERS AND WAS LEARNED THE HARD WAY. An earlier version split on
     * the next `@word` BEFORE stripping backticks, so the split never matched
     * and one field swallowed the next: 341 fields carried a leaked declaration,
     * a first "fix" moved it to 338, and only reordering reached 0.
     */
    /**
     * ⛔ CALLERS: match the field with `@name\s*`, never `@name\s+`.
     *
     * A declaration may open with a backtick and no space — `@owns_table`fact_sources` —
     * and `\s+` then fails to match the field at all. A verification script using
     * `\s+` reported X-119 as owning NOTHING while its header correctly listed
     * three tables: the artifact was right and the checker was wrong, which is
     * the harder failure to notice because it manufactures work rather than
     * hiding it.
     */
    public static function clean(string $line): string
    {
        // ⭐⭐⭐ ⓪ STOP AT THE BACKTICK THAT **CLOSES** THE DECLARATION.
        //
        // A declaration is written inside backticks:
        //
        //     `@owns_table demand_series · demand_regions` disjoint; the
        //                                                ^ everything after
        //                                                  here is PROSE
        //
        // Four modules were flagged "prose inside a declaration" when the real
        // defect was this parser reading past the closing backtick into the
        // sentence after it. ⛔ A checker that reads past its own delimiter
        // reports the DOCUMENT as broken when the PARSER is — and sends someone
        // to reword four correct sentences.
        //
        // ⛔⛔ AND THE TRAP I FELL INTO WHILE FIXING IT: the FIRST backtick on
        // the line is the OPENING one. Truncating there deletes the whole
        // declaration — when I tried it, `@consumes` went empty across the
        // roster and the violation count jumped from 4 to 80. The count going
        // UP is the stop rule doing its job.
        //
        // ⭐ So: skip the opening tick, stop at the NEXT one.
        // ⭐⭐⭐ THE RULE THAT ACTUALLY WORKS — derived from the real headers,
        //   after TWO wrong guesses that each made the count go UP.
        //
        // Truncating on backticks CANNOT work, because both shapes are in use:
        //     `@owns_table a · b`      one pair around the whole list
        //     `@owns_table` `a` · `b`  a pair around every token
        //
        // ⛔ Guess 1 (stop at the first tick) emptied every declaration:
        //    violations 4 → 80.
        // ⛔ Guess 2 (skip one, stop at the next) over-read the per-token shape:
        //    4 → 28.
        //
        // ⭐ What works: PROSE announces itself. It begins at an italic note
        //   *( … ), a ⭐/⛔/⚠️ glyph, or a spaced em-dash — never mid-token.
        //   Stop there and both shapes parse correctly.
        // ⛔⛔ GUARDED. `preg_split` RETURNS FALSE ON ERROR.
        //
        // I wrote this as `preg_split(...)[0]` — and `false[0]` is a fatal.
        // The `/u` modifier makes it fail on any invalid UTF-8 byte, and a
        // 3.2 MB hand-edited markdown plan is exactly where such a byte lives.
        //
        // ⭐ It killed `module:scaffold` on the owner's box. His agent patched
        //   it locally; that patch would have been OVERWRITTEN by the next
        //   bundle, so the fix belongs here.
        //
        // ⭐⭐ Note the shape: every OTHER preg_split in this runtime already
        //   carries `?: []`. I added this one later, in a hurry, and did not
        //   match the convention I had already established.
        $parts = preg_split('/\*\(|⭐|⛔|⚠️|\s+—\s+/u', $line) ?: [$line];
        $line = $parts[0] ?? $line;

        // ① stop at the next declaration on the same line
        $v = (preg_split('/@[a-z_]+\b/', $line) ?: [$line])[0];

        // ② drop parenthetical notes  *( … )*  — the send.requested case
        $v = (string) preg_replace('/⭐*\s*\*\([^)]*\)\*/u', '', $v);

        // ③ drop strike commentary  ⛔ … up to the next separator
        $v = (string) preg_replace('/⛔[^·|\n]*/u', '', $v);

        // ④ only now strip markup.
        //
        // ⛔⛔ THE WILDCARD MUST SURVIVE THIS. `@consumes *` is a legal
        // declaration for the four SPINE modules (X-123 EventBus, X-125 flows,
        // X-207 push, X-156 ingest) — they consume every event BY NATURE.
        //
        // A first version stripped `*` as markup, so the wildcard parsed to an
        // EMPTY field and four spine modules read as "consumes nothing" — the
        // exact opposite of the truth, and the kind of inversion that looks like
        // a clean result. A second attempt used a lookaround to spare a lone
        // asterisk and still ate it.
        //
        // ⭐ What works, verified against the real headers: PROTECT the
        //   backticked wildcard first, strip everything, then restore it.
        //   Sequencing beats cleverness — the same lesson as step ①.
        $v = str_replace('`*`', "\x00WILD\x00", $v);
        $v = (string) preg_replace('/[`*⭐⚠️✅]/u', '', $v);
        $v = str_replace("\x00WILD\x00", '*', $v);

        // ⑤ drop trailing em-dash prose
        $v = (preg_split('/\s+—\s+/u', $v) ?: [$v])[0];

        return trim($v, " \t·");
    }

    /**
     * Event tokens — `noun.verb`. Never bare words, so a note that survives
     * cleaning still cannot invent an event.
     *
     * @return list<string>
     */
    public static function events(string $line): array
    {
        preg_match_all('/\b[a-z][a-z0-9_]*\.[a-z0-9_.]+\b/', self::clean($line), $m);

        return array_values(array_unique($m[0] ?? []));
    }

    /**
     * Table names.
     *
     * ⛔⛔ THE DANGEROUS ONE. A table name is any lowercase word, so a surviving
     * note contributes real-looking tables — `C-Sms`'s note would have made it
     * own `messages`. Cleaning is not optional here, it is the whole safety.
     *
     * @return list<string>
     */
    public static function tables(string $line): array
    {
        preg_match_all('/\b[a-z][a-z0-9_]{3,}\b/', self::clean($line), $m);

        return array_values(array_unique($m[0] ?? []));
    }
}
