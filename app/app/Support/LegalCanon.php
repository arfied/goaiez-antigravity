<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\LegalCanonUnavailable;
use App\Services\Config\DefaultsRegistry;
use InvalidArgumentException;

/**
 * The two sentences counsel owns — their registry key names, the fail-closed
 * reads that compose with them, and the one rule that guards editing them.
 *
 * ## ⛔ THIS CLASS IS THE UNION OF TWO, AND THE MERGE IS WHY IT LOOKS LIKE THIS
 *
 * The 2026-08-18 wave built CC-4 and CC-5 in parallel and each shipped a class
 * called `LegalCanon` — CC-4's in `App\Services\Legal` (the key constants and
 * the write guard), CC-5's here (the fail-closed reads). **Different
 * namespaces, so git merged both without a conflict and both were live**
 * (decision 5285). They named the same two keys, so the strings always had one
 * home and the duplication was of *concept* rather than of truth — but two
 * classes with one name is how the next lane binds to whichever it happens to
 * find. W34 folded the guard in here and deleted the other (5290–5293).
 * ⚠️ **NO ALIAS WAS LEFT BEHIND AND THE OLD CONSTANT SPELLINGS ARE GONE**, on
 * purpose: an alias is how a duplicate comes back, and a lint now fails the
 * build if a second `LegalCanon` is ever declared (5295).
 *
 * ## ⛔ WHY A KEY AND NOT A STRING
 *
 * Both of these are legal wording that changes without any code changing: L-3's
 * review-invite footer and the written guarantee. Baked into a template, a
 * counsel edit would have to find every copy — and the copies are seeded rows,
 * so "every copy" means a data migration nobody would run. **Composed from the
 * key at the moment of sending, a counsel swap updates every future send and
 * touches nothing else.** That is CC-5 §3's rule for the ask footer and §2's for
 * the guarantee, and R53a's for both: *"Every legal text = a row in THE LEGAL
 * REGISTRY … versioned · zero-deploy · effective-dated · audit-logged ·
 * rendered wherever referenced. A text edit is a data edit, never a release."*
 *
 * ⚠️ **THEY ARE NOT `legal_documents` ROWS, AND THE DIFFERENCE IS THE FREEZE.**
 * That table makes a *published version* immutable because
 * `consent_records.disclosure_version` has to resolve to one exact text for
 * ever. These two are composed into a page or a text message at render time and
 * nothing stores a pointer back to a version of them, so freezing one would
 * mean a typo fix required a new version of a document nobody is citing.
 * `ReviewInviteSender::compose()` already draws this distinction about
 * `ConsentDisclosure` — two artefacts, two lifetimes.
 *
 * ## ⛔ IT HOLDS NO SENTENCE
 *
 * Every canonical sentence lives in `DefaultsManifest` as a seeded registry row
 * and nowhere else — `LegalTest`'s *"the guarantee sentence is written out in
 * exactly one place"* fails the build on a second copy in `app/`,
 * `resources/views` or `lang/`, and a constant here would be that copy, in the
 * file whose whole purpose is to make one source true. What it holds is **key
 * names**, which are identifiers rather than wording.
 *
 * ⚠️ **IT DOES HOLD TWO FRAGMENTS, AND SAYING "NO TEXT" WOULD HAVE BEEN THE
 * COMFORTABLE VERSION OF THAT SENTENCE** (314–316's shape, which this codebase
 * expects to bite inside the slice that quotes it).
 * {@see self::refuseIncompleteAskFooter()} needs needles, and a needle is a
 * literal wherever it lives. They are not the footer — they are *the part of it
 * that may not be removed*, which is the rule rather than the wording, and the
 * rule is what a constant should hold. The footer around them is the registry's
 * and stays editable.
 *
 * The key names are constants rather than string literals at each call site for
 * the ordinary reason: several lanes of the 2026-08-18 wave bind to them from
 * screens, senders and composers, and a typo in a registry key resolves to
 * `InvalidArgumentException` at runtime rather than at compile time.
 *
 * ## ⛔ AN UNSET KEY REFUSES THE SEND
 *
 * `mail.postal_address`'s posture (decision 4019), for the same reason: a review
 * invite without its footer is a text with no stated way to stop it, and a
 * guarantee macro without the guarantee is a promise a support agent appears to
 * have made up. **Both fail closed**, with a message naming the key an operator
 * has to set — never a plausible default, because a plausible default is a legal
 * sentence this codebase invented and then hid inside a real one.
 *
 * ## ⛔ AND AN EMPTIED FOOTER IS REFUSED AT THE WRITE
 *
 * `MissedCallTextBack::compose()` refuses to read its footer from a registry
 * key and says why: *"a wording an owner can edit is a wording an owner can
 * edit the disclosure out of."* That objection is answered here rather than
 * overruled. {@see self::refuseIncompleteAskFooter()} is consulted by
 * `DefaultsRegistry::set()`, which an architecture lint already makes the only
 * writer of `platform_settings`, so a counsel swap may reword the footer and no
 * edit can remove either obligation it carries.
 *
 * ⚠️ **THE WRITE GUARD AND THE READ REFUSAL GUARD DIFFERENT DOORS AND BOTH ARE
 * KEPT.** The guard makes an empty footer unreachable through every supported
 * path; the refusal is what still holds when a row is edited by hand in the
 * table, which after CC-4 is the only remaining way to reach one. Decision 5285
 * records that the second became defence in depth rather than redundant, and
 * `MessageCanonTest` drives it at the seam it guards rather than through a
 * write that now refuses — 398's shape, avoided deliberately.
 */
final class LegalCanon
{
    /**
     * L-3's review-invite footer — composed onto every review-ask text at send
     * time by `ReviewAskComposer` and by `ReviewInviteSender::compose()`, and
     * never stored on a template.
     *
     * Editable in wording, never in substance — see the class docblock and
     * {@see self::refuseIncompleteAskFooter()}.
     */
    public const string ASK_FOOTER_KEY = 'legal.sms_ask_footer';

    /**
     * R39's conditional performance guarantee — read by the lifecycle rungs, by
     * support macro S-3 and by the marketing pricing and guarantee pages,
     * always bound and never pasted.
     *
     * Editable, and deliberately so: it is a commercial promise the owner may
     * move, and `registry_changes` records who moved it.
     */
    public const string GUARANTEE_SENTENCE_KEY = 'legal.guarantee_sentence';

    /**
     * The two things a review-ask footer may never lose.
     *
     * ⚠️ **THE LANE DISCLOSURE IS MATCHED CASE-INSENSITIVELY AND THE OPT-OUT
     * KEYWORD IS NOT.** *"Sent via GO AI EZ"* is a sentence and its
     * capitalisation is a style question; `STOP` is the keyword a carrier and a
     * recipient both read as an instruction, and a footer offering to "reply
     * stop" is advertising something quieter than the thing that actually
     * works.
     */
    private const string LANE_DISCLOSURE = 'Sent via GO AI EZ';

    private const string OPT_OUT_KEYWORD = 'STOP';

    /**
     * Both keys, with the reason each carries no seed.
     *
     * Read by `DefaultsManifest::declaredWithoutSeed()`. Kept here rather than
     * written into that file so there is one place the key names exist.
     *
     * ⚠️ **BOTH KEYS ARE SEEDED TODAY, SO THAT LOOP ADDS NOTHING.** CC-4's seed
     * landed and 5280 removed the competing withheld declaration of the
     * guarantee, so the `array_key_exists` guard on the far side skips both.
     * This is the parallel-wave handshake 5251 built, and it is kept rather
     * than deleted because the guard is what makes it inert — see 5296.
     *
     * @return array<string, string>
     */
    public static function declarations(): array
    {
        return [
            self::ASK_FOOTER_KEY => 'The footer every review-invite text carries — L-3\'s canon, exposed as a registry key by CC-4 (decision 5251). ⛔ No seed, and while it is empty **no review-invite text composes at all**: `ReviewAskComposer` refuses with a message naming this key, because a review invite with no footer is a message with no stated way to stop it. ⚠️ The words are counsel\'s and may not be guessed here — a plausible default would be a legal sentence this codebase invented, hidden inside a real one. ⚠️ It is composed at send time rather than stored on a template, so setting it changes every future send and no seeded row has to be rewritten.',

            self::GUARANTEE_SENTENCE_KEY => 'R39\'s written guarantee, in one place — exposed as a registry key by CC-4 (decision 5251). Read by the day-13 and trial-expiry lifecycle rungs and by support macro S-3, always bound and never pasted, so a counsel edit reaches every future use. ⛔ No seed, and while it is empty the rungs that carry it and macro S-3 refuse with a message naming this key. ⚠️ A promise is not a figure with a conservative direction: an invented one is a commitment nobody made, and a shortened one is a commitment quietly withdrawn.',
        ];
    }

    /**
     * Refuse a review-ask footer that drops either obligation.
     *
     * ⚠️ **IT IS STATIC WHERE THE READS ARE NOT, AND THAT IS NOT AN
     * INCONSISTENCY LEFT OVER FROM THE MERGE.** It is called from inside
     * `DefaultsRegistry::set()`, and an instance of this class takes a
     * `DefaultsRegistry` — so resolving one to answer a question about a string
     * that has not been written yet would be a container round trip back
     * through the object doing the asking. The guard needs no registry: its
     * whole subject is the value in hand.
     *
     * ⚠️ **IT REFUSES A NON-STRING TOO, RATHER THAN IGNORING ONE.**
     * `platform_settings.value` is jsonb and `set()` takes `mixed`, so `null`,
     * `false` and `0` are all reachable from a screen or a console — and every
     * one of them would empty the footer while passing a substring check
     * written to expect a string. Refusing the type is the same refusal, one
     * step earlier.
     *
     * ⚠️ **AND IT SAYS WHAT TO DO.** The message names both obligations and the
     * decision behind the first, because the operator meeting this refusal is
     * usually somebody shortening a message to fit a segment, which is a
     * reasonable thing to be doing.
     *
     * @throws InvalidArgumentException when the value is not a string, or drops
     *                                  the lane disclosure or the opt-out
     *                                  instruction
     */
    public static function refuseIncompleteAskFooter(mixed $value): void
    {
        // Sequential `if`s rather than one `match (true)`: the type check has to
        // narrow `mixed` to `string` for the two `str_contains()` calls below,
        // and a `match` arm does not narrow the arms after it — so that spelling
        // is a static-analysis error and, without strict types, a TypeError at
        // runtime on exactly the input this method exists to refuse.
        if (! is_string($value)) {
            self::refuse('is not text');
        }

        if (! str_contains(strtolower($value), strtolower(self::LANE_DISCLOSURE))) {
            self::refuse('does not say "'.self::LANE_DISCLOSURE.'"');
        }

        if (! str_contains($value, self::OPT_OUT_KEYWORD)) {
            self::refuse('does not tell the recipient to reply '.self::OPT_OUT_KEYWORD);
        }
    }

    public function __construct(private readonly DefaultsRegistry $registry) {}

    /**
     * L-3's footer, or a refusal naming the key.
     *
     * @throws LegalCanonUnavailable
     */
    public function askFooter(): string
    {
        return $this->sentence(self::ASK_FOOTER_KEY, 'the footer every review-invite text carries');
    }

    /**
     * R39's guarantee, or a refusal naming the key.
     *
     * @throws LegalCanonUnavailable
     */
    public function guaranteeSentence(): string
    {
        return $this->sentence(self::GUARANTEE_SENTENCE_KEY, 'the written guarantee this account is offered');
    }

    /**
     * Whether a sentence is available, without throwing to find out.
     *
     * For a screen that has to decide whether to offer something rather than
     * whether to send it — an insert-button for a macro whose canon is unset
     * should be absent, not an exception when somebody clicks it.
     */
    public function has(string $key): bool
    {
        try {
            $this->sentence($key, 'that sentence');

            return true;
        } catch (LegalCanonUnavailable) {
            return false;
        }
    }

    /**
     * One canon sentence, fail-closed on every way of not having it.
     *
     * ⚠️ **THREE FAILURES COLLAPSE INTO ONE REFUSAL AND THAT IS DELIBERATE.**
     * The key may be undeclared, declared and unset (no operator has typed it),
     * or set to something blank. From the point of view of the person about to
     * be sent a message, those are the same fact — *there is no sentence* — and
     * three different exceptions would mean three call sites each catching two
     * of them.
     *
     * @throws LegalCanonUnavailable
     */
    private function sentence(string $key, string $what): string
    {
        try {
            $value = $this->registry->value($key);
        } catch (InvalidArgumentException) {
            // The manifest does not declare it. Both canon keys are declared and
            // seeded today, so this arm is reachable only for a key a caller
            // invented — for which refusing is the only honest answer.
            throw LegalCanonUnavailable::because($key, $what);
        }

        if (! is_string($value) || trim($value) === '') {
            throw LegalCanonUnavailable::because($key, $what);
        }

        return trim($value);
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function refuse(string $reason): never
    {
        throw new InvalidArgumentException(
            'That review-ask footer '.$reason.', so it cannot be saved. Every review invite '
            .'leaves the GOAIEZ 10DLC brand from our own number pool, so the message has to '
            .'say so (decision 3191), and the opt-out instruction is what makes the stop path '
            .'visible to the person who needs it. Reword the rest freely — those two stay.'
        );
    }
}
