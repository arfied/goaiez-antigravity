<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a customer's inbound message asked us to do.
 *
 * ⚠️ **THE PARSING IS HERE RATHER THAN IN THE HANDLER, AND THE REASON IS THAT
 * THIS IS THE THING THAT MUST BE DRIVEN RED THE HARDEST.** `BUILD-PLAN` §2.10.4
 * names three of its cases individually — *"STOP in lower case, with
 * punctuation, and with leading whitespace"* — because each is a real message a
 * real handset sends and each is a way a naive `$text === 'STOP'` silently fails
 * to honour somebody's instruction. A refusal that is not recognised is not a
 * refusal that errors; it is a refusal that is **discarded**, after which every
 * later send looks perfectly permitted.
 *
 * ⚠️ **THE CARRIER-MANDATED KEYWORD SETS ARE NOT OURS TO NARROW.** CTIA's
 * messaging principles require STOP, HELP and START to be honoured, and the
 * industry-standard synonyms travel with them — a handset user who types
 * `UNSUBSCRIBE` or `CANCEL` has opted out whether or not our list happened to
 * include the word. So the sets below are deliberately generous in the
 * opt-out direction and narrow in the opt-in one, which is the asymmetry the
 * whole product is built on: over-recognising a STOP costs a message that was
 * not sent, and under-recognising one costs a message to somebody who said stop.
 *
 * ⚠️ **A MESSAGE THAT IS NOT A KEYWORD IS `None`, NOT NULL.** Most inbound text
 * is somebody answering a review invite in words, and it is recorded as such —
 * a table that only stored the three control keywords would report that no
 * conversational replies exist, which is decision 620's inversion in the one
 * place where "we have no record of them writing to us" is the wrong answer.
 */
enum InboundKeyword: string
{
    case Stop = 'stop';
    case Help = 'help';
    case Start = 'start';
    case None = 'none';

    /**
     * Words that end our right to message somebody.
     *
     * CTIA's standard set. `REVOKE` and `OPTOUT` are included because handsets
     * and other platforms have taught people to type them, and the cost of a
     * false positive here is one unsent message.
     *
     * ⚠️ **EVERY ENTRY IS COMPARED AGAINST A CANDIDATE WITH ITS SEPARATORS
     * ALREADY REMOVED, SO EVERY ENTRY MUST ITSELF BE SEPARATOR-FREE** (2030).
     * `'opt-out'` used to sit at the end of this list and it is gone — not
     * because we stopped honouring it, but because `optout` above now catches
     * every spelling of it, including the one it used to catch. Left in place it
     * would be 1975's vacuous entry in the one file where the miss is a TCPA
     * violation: present in the list, read as enforced, matching nothing.
     * Pinned by *every stop word is a fixed point of the interior fold*, so
     * re-adding it in any spelling fails the build.
     *
     * ⚠️ **`OPT OUT` AS TWO WORDS IS HONOURED, AND THAT IS A POLICY CHANGE
     * RATHER THAN A BUG FIX** (2031). It is not spelled here because it does not
     * need to be — the fold reduces it to `optout`. The argument, and the
     * owner's standing right to reverse it, are in decision 2031.
     *
     * @var list<string>
     */
    private const array STOP_WORDS = [
        'stop', 'stopall', 'unsubscribe', 'cancel', 'end', 'quit', 'revoke', 'optout',
    ];

    /**
     * Every way a handset can spell a separator *inside* a keyword.
     *
     * ⚠️ **APPLIED TO THE STOP COMPARISON AND TO NOTHING ELSE** — see `parse()`.
     *
     * `\p{Z}\p{C}` is deliberately the same class `normalise()` already strips
     * at the edges, so the interior and the edges agree about what a separator
     * is; `\p{Z}` covers every Unicode space including U+00A0, and `\p{C}` every
     * invisible control and format character, which is where U+200B ZERO WIDTH
     * SPACE and U+00AD SOFT HYPHEN live. Enumerated on this build (PCRE2 10.42)
     * rather than recalled: `[\p{Z}\p{C}]` is a strict superset of `\s` under
     * `/u`, so a tab or a newline inside the message is covered by it.
     *
     * The rest are the things that *render* as a separator and are neither `Z`
     * nor `C`:
     *
     *   - **`\p{Pd}`** — all twenty-six dashes, Unicode's own category rather
     *     than a hand-picked list, so this stays a rule instead of a heuristic
     *     tuned until it catches nothing (511). U+002D is only one of them, and
     *     a handset that autocorrects to U+2011 is the case 1981 recorded.
     *   - **`\p{Pc}`** — the ten connectors, U+005F among them. A connector's
     *     defining job is to join two words, which is a separator by another
     *     name.
     *   - **U+2212 MINUS SIGN**, which renders as a hyphen and is categorised
     *     `Sm`. ⚠️ **1976 refused this exact literal in `ReplyGuardrails` and it
     *     is admitted here** — the two are not in conflict, because the
     *     asymmetry runs the other way. There a false positive refuses a
     *     tenant's reply; here a false *negative* keeps texting somebody who
     *     asked us to stop. See 2032.
     *   - **The four blank letters** — `\p{L}` ∩ `Default_Ignorable`, all four
     *     Hangul fillers, derived and pinned at 1937 as
     *     `ReplyGenerator::BLANK_LETTERS`. They are letters to `\p{L}` and blank
     *     to a reader, so `opt{U+3164}out` renders as `optout`.
     */
    private const string INTERIOR_SEPARATORS = '/[\p{Z}\p{C}\p{Pd}\p{Pc}\x{2212}\x{115F}\x{1160}\x{3164}\x{FFA0}]+/u';

    /**
     * @var list<string>
     */
    private const array HELP_WORDS = ['help', 'info'];

    /**
     * Words that ask to be messaged again.
     *
     * ⚠️ **DELIBERATELY THE SHORTEST LIST OF THE THREE, AND IT MUST STAY THAT
     * WAY.** A false positive here does not cost an unsent message — it lifts a
     * suppression and resumes texting somebody who told us to stop. `YES` is
     * conspicuously absent for exactly that reason: it is the single most likely
     * word to arrive as a reply to an unrelated question, and treating it as a
     * re-subscription would resume sending on the strength of somebody agreeing
     * to something else entirely.
     *
     * @var list<string>
     */
    private const array START_WORDS = ['start', 'unstop', 'subscribe'];

    /**
     * Read one inbound message.
     *
     * ⚠️ **STOP IS TESTED FIRST — AND THE REASON WRITTEN HERE WAS FALSE, WHICH
     * IS WHY MOVING THE ARM SURVIVES THE SUITE** (2036). The old sentence said
     * *"the reverse order would let `start` inside a longer message outrank a
     * `STOP` beside it"*, which describes a **substring** matcher; the paragraph
     * immediately below says this one refuses substrings, so the two contradict
     * each other and the second is the true one. Every comparison here is
     * whole-string equality against three lists that share no member, so no
     * input can reach two arms and the order currently decides nothing. Moving
     * `Stop` below `Start` was mutated and the whole file stayed green.
     *
     * It stays first anyway, and the guard is now the *disjointness* rather than
     * the order: *no word can be read as two things* fails the build the moment
     * somebody adds a word to two lists — or adds one to START whose folded form
     * is already a STOP word, which is the way the fold below could create an
     * overlap that did not exist. On the day that fires, the order is what makes
     * the tie resolve to the reading that stops sending.
     *
     * ⚠️ **THE WHOLE MESSAGE MUST BE THE KEYWORD — a substring match is refused,
     * and that is the one place this method is deliberately strict.** Matching
     * `stop` anywhere inside the text would classify *"please don't stop sending
     * these"* as an opt-out, and — far worse in the other direction — would
     * classify *"how do I start"* as a re-subscription. Carriers apply the same
     * rule: the keyword is the message.
     *
     * ⚠️ **THERE ARE TWO NORMAL FORMS AND THAT IS THE FIX, NOT AN OVERSIGHT**
     * (2030, 2031). The STOP comparison sees the candidate with every interior
     * separator removed; HELP and START see it exactly as they always have. One
     * shared fold would have been the obvious shape and it is **wrong here**:
     * executed, it reads `un stop`, `un-stop`, `sub scribe` and `st art` as
     * `Start`, which lifts a suppression and resumes texting somebody who told
     * us to stop. `START_WORDS`' docblock below is the argument, and the split
     * is what makes it true of the code rather than only of the prose.
     *
     * Being generous with STOP costs one unsent message. Being generous with
     * START costs a TCPA violation per message, at $500–$1500 each. So the fold
     * goes where the miss is expensive and stays away from where the match is.
     *
     * ⚠️ **STOP STILL WINS, AND THE FOLD DOES NOT CHANGE THAT.** The arms are
     * evaluated in order against a candidate that is only ever *more* likely to
     * be a stop word than before, so nothing that used to be `Stop` can now be
     * `Start` or `Help`.
     */
    public static function parse(?string $text): self
    {
        $normalised = self::normalise($text);

        if ($normalised === '') {
            return self::None;
        }

        return match (true) {
            in_array(self::foldSeparators($normalised), self::STOP_WORDS, true) => self::Stop,
            in_array($normalised, self::HELP_WORDS, true) => self::Help,
            in_array($normalised, self::START_WORDS, true) => self::Start,
            default => self::None,
        };
    }

    /**
     * One normal form, applied before every comparison.
     *
     * Each step is a message a handset actually sends, and each was a way the
     * naive comparison failed:
     *
     *   - **Leading and trailing whitespace.** A predictive keyboard adds a
     *     trailing space to almost every word it completes.
     *   - **Case.** `Stop` is what autocapitalisation produces, and it is
     *     probably the most common spelling of all.
     *   - **Trailing punctuation.** `STOP.` and `STOP!` are ordinary; a full
     *     stop after a one-word message is what a keyboard's double-space
     *     shortcut inserts on its own.
     *   - **Surrounding quotes**, which arrive when somebody copies the word out
     *     of the disclosure line we sent them — *Reply STOP to opt out* — and
     *     the copy takes the quotes with it.
     *
     * ⚠️ **Interior punctuation is STILL not stripped, and that paragraph used
     * to say something stronger than the code now does** (2034). Removing all
     * of it would turn `"s.t.o.p"` into a match, which nobody types, while also
     * turning any sentence whose words happened to strip down to a keyword into
     * one. What *is* removed — and only for the STOP comparison — is interior
     * **separators**: see `foldSeparators()`, which is a strictly narrower class
     * than `\p{P}` and leaves `s.t.o.p`, `opt.out` and `opt/out` as `None`.
     *
     * ⚠️ **UNICODE-AWARE, because the whitespace is not always a space.** An SMS
     * composed on some keyboards carries a non-breaking space or a zero-width
     * character, and `trim()`'s default character list contains neither — so
     * `"STOP\u{00A0}"` would survive an ASCII trim intact and fail every
     * comparison below it.
     *
     * ⚠️ **THE CASE FOLD RUNS FIRST, AND THAT IS 1980's ORDERING RATHER THAN A
     * STYLE CHOICE** (2033). `preg_replace()` returns null on a malformed
     * subject, so running it on the raw text meant a body carrying one stray
     * byte fell through **both** `?? ` fallbacks and was never trimmed at all:
     * `parse("STOP\xFF")` was `None`. `mb_strtolower()` substitutes invalid
     * sequences, so putting it first hands every pattern below valid UTF-8 and
     * makes those fallbacks unreachable. ⚠️ **Nothing can currently drive this
     * through the webhook** — `json_decode()` refuses invalid UTF-8, so
     * `InfobipInboundController` answers 422 before this is called — so it is
     * hardening on a public API rather than a live repair, and it is written
     * down that way instead of claimed as more.
     */
    private static function normalise(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $lowered = mb_strtolower($text);

        // \p{Z} is every Unicode separator, \p{C} every invisible control and
        // format character — which is where the zero-width space lives.
        $trimmed = preg_replace('/^[\p{Z}\p{C}]+|[\p{Z}\p{C}]+$/u', '', $lowered) ?? $lowered;

        // Edge punctuation and quotes, after the whitespace so that `STOP . `
        // reduces the same way `STOP.` does.
        $trimmed = preg_replace('/^[\'"“”‘’\p{P}]+|[\'"“”‘’\p{P}]+$/u', '', $trimmed) ?? $trimmed;

        return trim($trimmed);
    }

    /**
     * The candidate with every interior separator removed — the STOP form only.
     *
     * ⚠️ **REMOVED RATHER THAN REWRITTEN TO A CANONICAL CHARACTER, AND THE TWO
     * ARE NOT EQUIVALENT** (2030). `ReplyGuardrails::fold()` rewrites one
     * separator to another because it scans a haystack for needles and a fold
     * that deleted characters could join two tokens into a match the ASCII
     * spelling would not have made (1974). This is the other problem: a short
     * candidate compared for **whole-string equality** against a list of
     * separator-free words, where nothing is being scanned and there is nothing
     * to join across. Deleting gives one canonical form — `opt-out`,
     * `opt{U+2011}out`, `opt out`, `opt  out` and `optout` all reduce to
     * `optout` — and makes `optout` the only entry the list needs.
     *
     * ⚠️ **A RUN OF SEPARATORS COLLAPSES — AND THE `+` IS NOT WHAT DOES IT**
     * (2037). `OPT  OUT` with two spaces is a real message, and the case
     * `ReplyGuardrails` deliberately leaves open (1976) is closed here because
     * deleting is per-character: `preg_replace` removes each separator in the
     * run whether or not they were matched as one. Dropping the `+` was mutated
     * and **survived the whole file**, so it is a performance detail and this
     * paragraph says so rather than claiming a guarantee it does not carry
     * (314–316).
     *
     * ⚠️ **THE `?? ` CANNOT FIRE**, because `normalise()` above returns the
     * output of `mb_strtolower()` and that is always valid UTF-8. It is the safe
     * spelling anyway — the alternative, `''`, would make every message parse as
     * `None` and discard refusals wholesale.
     */
    private static function foldSeparators(string $normalised): string
    {
        return preg_replace(self::INTERIOR_SEPARATORS, '', $normalised) ?? $normalised;
    }
}
