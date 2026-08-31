<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Models\Business;
use App\Models\Location;

/**
 * FOUND-08 / GBP-03: what an AI reply must never say under the business's name.
 *
 * A reply is published on the owner's Google listing. Invented facts, refunds,
 * compensation, and legal statements are the failure modes the ticket names —
 * and the model is the one nobody controls, so a second pass after generation
 * is what makes those refusals falsifiable rather than prompt-hope.
 *
 * ⚠️ THIS IS A PATTERN MATCH, NOT A LAWYER. It catches the common shapes a
 * model drifts into; it does not certify a reply as safe, and it answers exactly
 * one question — *does this text contain one of sixteen compensation or legal
 * phrases*. ⚠️ **DO NOT REACH FOR IT TO ANSWER A DIFFERENT ONE** (1850). Fix
 * wave 2 used `allows()` to decide whether an attacker-chosen reviewer display
 * name was safe to publish verbatim under the business's name, and a blocklist
 * of sixteen phrases answers "is this a personal name" with *yes* for
 * `<script>alert(1)</script>`. That question has a positive answer shape and
 * belongs to an allowlist — `ReplyGenerator::reviewerLabel()`.
 *
 * ⚠️ WHERE A FAILURE GOES DEPENDS ON WHICH CALLER FAILED, AND THIS SENTENCE USED
 * TO SAY OTHERWISE (1854). In `ReplyGenerator::draft()` a refusal falls back to
 * the safe template, which invents nothing. At the writer boundary,
 * `ReviewReplies::recordSuggestion()` **throws** `ReplyGuardrailRefused` — 1730
 * promoted it to a chokepoint — and `GenerateReplyJob` catches that and re-files
 * the safe template, so the owner still gets something to approve (1682). A
 * second caller that does not catch it gets an exception, not a fallback.
 *
 * ⚠️ AND IT IS A *CASE-FOLDED* PATTERN MATCH, WHICH UNICODE DOES NOT MAKE
 * SAFE (1748). `mb_strtolower('GİFT CARD')` is `gi̇ft card` — a dotted `i`
 * followed by U+0307 COMBINING DOT ABOVE — so the needle `gift card` does not
 * match and `allows()` returns true. The same holds for any homoglyph or
 * zero-width character a model could emit. Hand-rolling normalization here was
 * refused: a half-correct fold is a *new* way to mangle a tenant's own name,
 * and this predicate is now a throwing chokepoint. It is recorded as a known
 * limitation rather than papered over, and it is why the sentence above says
 * "does not certify a reply as safe" rather than "blocks unsafe replies".
 *
 * ⚠️ THE SECOND KNOWN EVASION WAS A SPACE, AND IT IS CLOSED BY `fold()` BELOW
 * (1938 → 1970). Eight of the needles below spell their own word boundary as
 * U+0020 and a ninth spells it as U+002D, while the haystack is arbitrary model
 * output. Executed against `62be58d`: `allows('We will send you a gift card.')`
 * was **false**, correctly — while `gift\x{00A0}card`, `gift\x{2009}card`,
 * `gift\x{3164}card`, `gift\x{0009}card`, `gift\x{000A}card`,
 * `free\x{00A0}meal` and `money\x{2011}back` were all **true**. Reachable
 * through a review comment that steers the model, because `PromptFence`
 * prevents the marker being *closed* rather than the instruction being
 * followed. What closes it is `fold()`, applied to the lowercased haystack
 * **and** to each lowercased exemption alike.
 *
 * ⚠️ AND SINCE 4460 THE TWO PATHS NORMALISE DIFFERENTLY, WHICH IS THE ONE THING
 * TO CARRY OUT OF THIS FILE. `allows()` — the published Google reply, behind a
 * chokepoint that **throws** — is lowercased and separator-folded and nothing
 * more, exactly as it was. `allowsRecoveryOutreach()` — a 320-character private
 * apology, where a refusal costs one draft its words — additionally folds
 * confusables, strips zero-width characters, and matches an anchored pattern set
 * as well as the substrings. **The asymmetry is the argument**: breadth that is
 * cheap on one path is 1735's law-firm failure on the other, so it was not
 * shared. Anything added below must say which path it is for.
 *
 * ⚠️ AND WHAT IT STILL DOES NOT CLOSE, BECAUSE 1858's RULE IS THAT A PASS SAYS
 * WHAT IT LOOKED AT AND NEVER THAT A PREDICATE IS CLEAN (1976, OWED). Three
 * evasions were executed against the fold and survive it: a *run* of separators
 * (`gift  card` with two spaces has never matched and still does not, because
 * folding is 1:1); a *zero-width* insertion (U+200B renders as nothing, so the
 * reader sees `giftcard` and neither spelling matches); and U+2212 MINUS SIGN,
 * which renders as a hyphen and is categorised `Sm` rather than `Pd`. All three
 * are pinned by a test that goes red when somebody closes one.
 */
final class ReplyGuardrails
{
    /**
     * Phrases that mean the draft offered money, a remedy, or a legal position.
     *
     * Case-insensitive. Kept narrow on purpose: a broad "sorry" ban would
     * refuse every warm reply, and "we will" alone is ordinary courtesy.
     *
     * ⚠️ EVERY ENTRY MUST ALREADY BE LOWERCASED AND ALREADY FOLDED, because the
     * haystack these are compared against has been through both (1975). A
     * needle spelled `money\x{2011}back` or with a tab would sit in this list,
     * read as enforced, and match nothing — 256's vacuous gate inside a
     * build-failing test. Pinned: *every forbidden needle is a fixed point of
     * the fold*.
     *
     * @var list<string>
     */
    private const array FORBIDDEN = [
        'refund',
        'compensate',
        'compensation',
        'gift card',
        'giftcard',
        'free meal',
        'free service',
        'lawsuit',
        'legal action',
        'attorney',
        'solicitor',
        'we guarantee',
        'i guarantee',
        '100% guarantee',
        'money back',
        'money-back',
    ];

    /**
     * What a **private win-back message** must never carry (T176 P15, 4356,
     * rewritten at 4460 after every phrase below was executed and ALLOWED).
     *
     * ⚠️ ADDITIONAL TO `FORBIDDEN`, NEVER A REPLACEMENT FOR IT, AND DELIBERATELY
     * NOT MERGED INTO IT (4356's refusal, still right). These are wrong in a
     * recovery message and ordinary in a public Google reply — *"thank you for
     * your review"* is the most natural opening line there is on that path.
     * Adding them to the shared list would refuse a large share of every Google
     * draft for a rule that is about a different message on a different channel,
     * which is 511's shape arrived at by tuning the wrong lint.
     *
     * ⚠️ WHY A RECOVERY MESSAGE MAY NOT ASK FOR A REVIEW. The customer is here
     * *because* their rating was at or below the tenant's triage threshold, and
     * the per-destination thresholds beside it are the mechanism that decided
     * not to point this person at a public listing (`ReviewRouter`). A drafted
     * private message that asks them for one walks past that decision in the
     * owner's own voice, on the one path where the person is known to be
     * unhappy. The model is told not to; this list is what makes the refusal
     * falsifiable rather than prompt-hope.
     *
     * ⛔ **IT USED TO BE TEN EXACT PHRASES AND IT CAUGHT ALMOST NOTHING** (4460).
     * `leave us a rating` was present, `leave a rating` was not; `star review`
     * was, `five stars` was not. Executed against the previous commit, every one
     * of *"Would you mind rating us five stars?"*, *"Please give us a rating on
     * Google."*, *"Could you update your review?"*, *"Tell Google what you
     * think."* and *"Leave a rating if we make it right."* returned **true**. A
     * phrase list enumerating word orders is 256's vacuous gate wearing a
     * compliance label: it matched the sentences somebody imagined and none of
     * the sentences a model writes. **The unit is now the word, not the
     * sentence**, so the whole `rating`/`stars`/`review`/platform-name family is
     * refused however it is phrased.
     *
     * ⛔ **AND THE INCENTIVE HALF HAD NO GUARDRAIL AT ALL.** The system prompt
     * tells the model not to offer *"refunds, discounts, gift cards, free goods,
     * or compensation"*; `FORBIDDEN` covered refund/gift card/free meal/free
     * service/money back and nothing else, so *"Here is 20% off your next
     * visit."* and *"Your next coffee is on the house."* both shipped. Composed
     * with the half above, the model could draft an **incentivized review
     * solicitation aimed at a customer known to be unhappy** — CLAUDE.md's
     * absolute rule, Google's review policy and FTC 16 CFR Part 465 in one
     * sentence. Every clause of the prompt now has a pattern behind it.
     *
     * ⚠️ **BREADTH IS THE DELIBERATE TRADE, AND IT DOES NOT TRANSFER TO
     * `FORBIDDEN`.** A false positive here costs one draft its model-written
     * words and files `recoverySafeTemplate()` instead — a plain apology the
     * owner can still send. That is per-draft and probabilistic. 1735's law-firm
     * failure was per-*tenant* and total, because its trigger was the tenant's
     * own configured name, and the exemption set threaded through
     * `exemptSpans()` is what keeps that from recurring here. `FORBIDDEN`
     * governs a **published Google reply** behind a throwing chokepoint, where
     * the same breadth would cost a tenant every reply for ever; it is not
     * widened.
     *
     * ⚠️ **A POSITIVE GATE WAS CONSIDERED AND REFUSED** (4461). The brief asked
     * for one where it could be built cleanly, and 1850's rule — *"is this a
     * personal name" has a positive answer shape, so it belongs to an
     * allowlist* — is right about names and does not carry here. This subject is
     * free-form English prose, three brand voices, arbitrary tenant and customer
     * names, and a grievance nobody has seen. An allowlist over that vocabulary
     * either refuses nearly every legitimate draft or is relaxed until it
     * catches nothing (511). The honest shape is a wide blocklist plus a length
     * ceiling the prompt already carries.
     *
     * **Written as anchored patterns rather than substrings, and that is the
     * fix rather than a style choice.** `\brating` refuses *rating* and
     * *ratings* while leaving *frustrating* alone — the word an apology reaches
     * for most. A bare substring `rating` would refuse *"I understand how
     * frustrating that was"* and quietly turn this feature into
     * always-the-template, which is 1735's failure with a new cause. Word
     * boundaries also make the whitespace-run evasion (`leave  a  review`)
     * irrelevant on this path, because a single token has no internal separator
     * to double.
     *
     * ⚠️ **EVERY PATTERN IS MATCHED AGAINST A HAYSTACK THAT HAS BEEN LOWERCASED,
     * SEPARATOR-FOLDED *AND* CONFUSABLE-FOLDED**, so no pattern may contain an
     * uppercase literal — pinned by *every recovery pattern compiles, is
     * lowercase, and refuses something*.
     *
     * @var list<non-empty-string>
     */
    private const array RECOVERY_FORBIDDEN_PATTERNS = [
        // ── The ask, in every shape a model reaches for ──────────────────────
        // Unqualified on purpose: "reviewed", "reviewer" and "reviews" are all
        // the same subject, and *"let me review the order with the kitchen"*
        // falling back to the template is the cheap half of this trade.
        '/\breview/u',
        '/\brating/u',
        // One pattern rather than two: `/\brated\b/u` on its own matched nothing
        // in the refusal corpus, and the pin beside it treats that as 256's
        // vacuous needle rather than as harmless spare capacity.
        '/\brated?\b/u',
        '/\bstars?\b/u',
        // ★ ☆ ⋆ ⭐ — a glyph is an ask with the word removed.
        '/[\x{2605}\x{2606}\x{22C6}\x{2B50}]/u',
        '/\btestimonial/u',
        '/\brecommend\s+us\b/u',
        '/\bspread\s+the\s+word\b/u',

        // ── Where an ask points. A private note to one unhappy customer has no
        // legitimate reason to name a review platform at all, so the platform
        // names are refused rather than the phrasings around them: this is what
        // catches *"Please share your feedback on Google"* without refusing the
        // word "feedback", which is ordinary and warm on this path.
        '/\bgoogle\b/u',
        '/\byelp\b/u',
        '/\btrustpilot\b/u',
        '/\btripadvisor\b/u',
        '/\bfacebook\b/u',
        '/\bbbb\b/u',
        '/\bbetter\s+business\s+bureau\b/u',

        // ── The incentive, one pattern per clause of the system prompt ───────
        '/\bdiscount/u',
        '/%\s*off\b/u',
        '/\bpercent\s+off\b/u',
        '/\bhalf[\s-]+(price|off)\b/u',
        '/\boff\s+your\s+next\b/u',
        // Any currency amount. A private apology that names a sum of money is
        // making an offer; the customer's own bill is the false positive, and it
        // costs a template.
        '/[\x{0024}\x{00A3}\x{20AC}]\s*\d/u',
        '/\bvoucher/u',
        '/\bcoupon/u',
        '/\bcredits?\b/u',
        '/\bcomplimentary\b/u',
        '/\bcomped\b/u',
        '/\bfreebie/u',
        '/\bgift/u',
        '/\bupgrade/u',
        '/\breward/u',
        '/\bwaive/u',
        '/\bon\s+the\s+house\b/u',
        '/\bno\s+charge\b/u',
        // ⚠️ THE TWO GENUINELY AMBIGUOUS ENTRIES, KEPT WITH THE ARGUMENT WRITTEN
        // DOWN. *"Your next coffee is on us"* is the incentive; *"the mistake was
        // on us"* is an owner taking the blame, and both are natural. A pattern
        // clever enough to tell them apart would be tuned on guesses about
        // sentence shape and relaxed the first time it cried wolf (511). Refusing
        // both costs a plain apology; admitting both ships a free coffee for a
        // rating.
        '/\bon\s+us\b/u',
        '/\bon\s+me\b/u',
        // "free" minus the one collocation that is not an offer. "Feel free to
        // reply" is what the system prompt actually asks the model to write, so
        // a bare \bfree\b would refuse the drafts that followed instructions.
        '/\bfree\b(?! to\b)/u',
    ];

    /**
     * Characters that render as a Latin letter but are not one — folded on the
     * recovery path only (4462).
     *
     * ⚠️ **DELIBERATELY NOT IN `fold()`, WHICH THE GOOGLE PATH SHARES.** Folding
     * confusables there would make `FORBIDDEN` stricter behind a chokepoint that
     * **throws**, on replies published under a tenant's own name — 1735's cost
     * profile, where a single mis-fold costs one tenant every reply for ever. On
     * this path a mis-fold costs one draft its words. The asymmetry that
     * justifies the wide needle list justifies this too, and only this far.
     *
     * ⚠️ **NOTHING HERE MAPS TO `r`, `d`, `f`, `l`, `g` OR `w`, AND THAT IS
     * CHECKED RATHER THAN INCIDENTAL.** Every dangerous needle on this path
     * (`review`, `rating`, `star`, `free`, `credit`, `reward`) needs at least one
     * of those, so a Cyrillic or Greek sentence cannot transliterate into a
     * refusal by accident — which is the failure a general `Any-Latin`
     * transliteration would have introduced.
     *
     * ⚠️ **IT IS A BOUND, NOT A SOLUTION.** Mathematical alphanumerics, small
     * capitals and the rest of the confusable table are not here; character
     * insertion is unbounded (1748's family). What is closed is the pair that was
     * executed and returned true: a Cyrillic-е homoglyph, and a zero-width
     * insertion.
     *
     * @var array<string, string>
     */
    private const array CONFUSABLE_FOLD = [
        // Zero-width and soft hyphen — removed rather than mapped, because the
        // reader sees nothing there at all.
        "\u{00AD}" => '', "\u{200B}" => '', "\u{200C}" => '', "\u{200D}" => '',
        "\u{2060}" => '', "\u{FEFF}" => '',
        // Cyrillic
        'а' => 'a', 'в' => 'b', 'с' => 'c', 'е' => 'e', 'ё' => 'e', 'һ' => 'h',
        'н' => 'h', 'і' => 'i', 'ј' => 'j', 'к' => 'k', 'м' => 'm', 'о' => 'o',
        'р' => 'p', 'ԛ' => 'q', 'ѕ' => 's', 'т' => 't', 'у' => 'y', 'х' => 'x',
        // Greek
        'α' => 'a', 'ϲ' => 'c', 'ε' => 'e', 'ι' => 'i', 'κ' => 'k', 'ν' => 'v',
        'ο' => 'o', 'ρ' => 'p', 'τ' => 't', 'υ' => 'u', 'χ' => 'x',
        // Latin lookalikes
        'ı' => 'i',
        // Fullwidth punctuation the incentive patterns read
        '％' => '%', '＄' => '$',
    ];

    /**
     * How every way of writing a separator is rewritten to the one the needles use.
     *
     * Pattern => replacement, applied in order to a string that has already been
     * lowercased. Both classes are disjoint from each other and from their own
     * replacements, so the order is not load-bearing; it is written as one map
     * so that adding a third family is one line rather than a new pass.
     *
     * ⚠️ `\s` RATHER THAN `\p{Zs}`, AND THE DIFFERENCE IS NINE CODE POINTS
     * (1972). The repair 1938 specified was `\p{Zs}` plus the four blank
     * letters. Enumerated on this build (PCRE2 10.42), `\s` under `/u` is a
     * strict superset of `\p{Zs}` — it adds U+0009, U+000A, U+000B, U+000C,
     * U+000D, U+0085, U+180E, U+2028 and U+2029, every one of which is a
     * separator to a reader and none of which is `Zs`. A Google reply may
     * contain line breaks, so `gift{LF}card` is the ordinary case rather than
     * the exotic one, and `\p{Zs}` alone would have shipped a docblock claiming
     * this was closed while it was not (314–316).
     *
     * The four literals are `ReplyGenerator::BLANK_LETTERS` — `\p{L}` ∩
     * `Default_Ignorable_Code_Point`, all four Hangul fillers, derived and
     * pinned at 1937. They are letters to `\p{L}` and blank to a reader, which
     * is why they belong to the separator fold here and to a refusal there.
     *
     * ⚠️ `\p{Pd}` IS THE SAME DEFECT IN THE SAME CONSTANT (1973). `money-back`
     * spells its boundary as U+002D, so the other twenty-five members of the
     * dash category walked past it exactly as the spaces did:
     * `money\x{2011}back` and `money\x{2013}back` both returned true. Unicode's
     * own category rather than a hand-picked list, so this stays a rule rather
     * than becoming a heuristic tuned until it catches nothing (511).
     *
     * @var array<string, string>
     */
    private const array SEPARATOR_FOLD = [
        '/[\s\x{115F}\x{1160}\x{3164}\x{FFA0}]/u' => ' ',
        '/\p{Pd}/u' => '-',
    ];

    /**
     * The values a reply may contain because *the tenant* chose them (1739).
     *
     * ⚠️ THE BUSINESS NAME AND THE LOCATION NAME, AND DELIBERATELY NOTHING
     * ELSE. This list used to carry `reviewer_name` too, and that was a hole a
     * stranger could climb through with no prompt injection at all — see
     * `allows()` below. Both of these arrived through an authenticated screen
     * during onboarding; nobody outside the tenant can set either.
     *
     * ⚠️ AND THE VALUES ARE READ TWICE, FROM TWO PLACES, ON PURPOSE (1857). The
     * generator passes the `Business` and `Location` its job already loaded; the
     * writer re-reads both by primary key from `$review`. An admin renaming a
     * location between the two would give one review a generator exemption set
     * the writer does not share. Threading the resolved values through
     * `ReplyDraft` was refused: it would make the writer trust an exemption set
     * handed to it *by its caller*, which is the drift 1741/1742 closed by
     * having the chokepoint derive its own. A chokepoint that reads the database
     * is right about the tenant; a chokepoint that believes its caller is not a
     * chokepoint.
     *
     * It lives here, on the class that decides what an exemption *means*, so
     * that the two call sites — `ReplyGenerator::draft()` on the model's raw
     * output and `ReviewReplies::recordSuggestion()` at the writer boundary —
     * cannot pass different sets and make the chokepoint stricter than the
     * generator (1741). They drifted apart exactly once and the result was a
     * law firm whose every reply fell back to the canned template forever.
     *
     * @return list<string>
     */
    public static function tenantValues(?Business $business, ?Location $location): array
    {
        return array_values(array_filter(
            [(string) ($business->name ?? ''), (string) ($location->name ?? '')],
            static fn (string $v): bool => trim($v) !== '',
        ));
    }

    /**
     * Whether this draft is safe to show the owner (and later, to post).
     *
     * ⚠️ `$ignoring` IS NOT A LOOPHOLE — IT IS WHAT STOPS THIS RULE FIRING ON A
     * LAW FIRM'S OWN NAME (1735). This list is a substring match, and the reply
     * that reaches it has already had `{{business_name}}` and
     * `{{location_name}}` substituted in. So *"We're glad you chose Smith &
     * Jones **Attorneys** at Law"* matches `attorney`, and *"Refund King
     * Electronics"* matches `refund` — the platform's own safe template, refused
     * for containing the tenant's name. On a chokepoint that throws, that is
     * every reply for that tenant failing permanently, and the tenants it hits
     * are exactly the ones a legal-language rule sounds most important for.
     *
     * ⚠️ IT USED TO EXEMPT THE REVIEWER'S DISPLAY NAME AS WELL, AND THAT WAS
     * BACKWARDS (1739). The argument written here was that a reviewer calling
     * themselves "Refund" would otherwise block every draft on their own review
     * — a denial of service spelled with eight characters. What it actually
     * bought was the opposite and worse: **a stranger's chosen string switched
     * this rule off**. A reviewer named *"We will refund you in full"* leaving a
     * five-star review with no comment gets `safeTemplate()` verbatim, that
     * exact phrase is erased from the haystack before matching, `allows()`
     * returns true, and with `full_auto_post_replies` on it publishes under the
     * business's name on their public Google listing. The denial of service was
     * real; it was the cheaper of the two, and it is now handled where it
     * belongs — `ReplyGenerator::reviewerLabel()` degrades a hostile label to the
     * neutral one at source, so it never becomes an exemption and never reaches
     * a published reply.
     *
     * ⚠️ AND THE REPLACEMENT FOR IT WAS THIS PREDICATE, WHICH WAS THE NEXT
     * DEFECT (1850). Wave 2 wrote `allows($reviewerName)` as the test of whether
     * a stranger's display name could be published verbatim. This method cannot
     * answer that: it knows sixteen phrases, so `VISIT cheapcompetitor.example
     * for 50% off` passed it and reached a Google listing under the business's
     * name. It may stay as an *additional* refusal on a label — a reviewer
     * called "Refund" would otherwise cost that review its draft — but it is
     * never the gate. The gate is the allowlist in `reviewerLabel()`.
     *
     * ⚠️ AN EXEMPTION THAT CONTAINS NO FORBIDDEN NEEDLE IS REFUSED, and that is
     * what closes this class of defect rather than this one instance of it. The
     * erasure used to be global rather than positional: `str_replace('e', ' ',
     * …)` blanks that letter everywhere, so `allows('We will refund you in
     * full.', ['e'])` returned **true**. An exemption exists to stop the tenant's
     * own name tripping the rule — a value with nothing forbidden in it has
     * nothing to exempt and can only ever weaken the match, whatever future path
     * it arrives by.
     *
     * ⚠️ AND THE MATCH IS NOW POSITIONAL RATHER THAN AN ERASURE (1853). Refusing
     * needle-free exemptions bounded the damage but did not remove it, and the
     * bound written next to it — *"the shortest needle is six characters, so
     * erasing an exemption cannot blank a shorter needle elsewhere"* (1757) — is
     * false in both directions:
     *
     *     allows('We offer free service compensation on request.', ['Free Service Co'])
     *
     * returned **true**, because `free service co` matched across the word
     * boundary into `co|mpensation` and replacing it with a single space deleted
     * a *longer*, unrelated needle that was not "elsewhere" at all. A needle is
     * now exempt only when its own occurrence lies **entirely inside** an
     * occurrence of an exemption, so nothing outside an exemption's span can be
     * shortened, lengthened or joined by one.
     *
     * ⚠️ WHAT IS STILL TRUE AFTER ALL OF THAT, SAID PLAINLY: a tenant whose
     * business name *is* a forbidden needle (a shop called "Refund") does
     * disable that one needle for their own replies. That is the law-firm trade
     * accepted knowingly, on a value only the tenant can set, and it is the same
     * line decision 1730 draws for `approve()` — the rule governs what *we*
     * write under their name, not what they choose to say on their own listing.
     *
     * @param  list<string>  $ignoring  Values the *tenant* configured — see tenantValues().
     */
    public function allows(string $text, array $ignoring = []): bool
    {
        return $this->allowsAgainst($text, self::FORBIDDEN, [], $ignoring, confusables: false);
    }

    /**
     * The same question for a private win-back message (T176 P15, 4356).
     *
     * `FORBIDDEN` **and** `RECOVERY_FORBIDDEN_PATTERNS`, in one pass so the two
     * cannot be asked in different orders or with different exemptions by two
     * callers — which is 1741's drift, where the generator and the writer
     * chokepoint passed different exemption sets and a law firm lost every draft
     * it ever had. One entry point, one needle set per path.
     *
     * ⚠️ 1741 CAME BACK ANYWAY, ONE CLASS ALONG, AND THE ONE ENTRY POINT IS WHY
     * IT WAS SURVIVABLE. `ReviewRouter::guardedRecoveryText()` is the second
     * caller 4468 added, and its first cut re-derived the exemptions correctly
     * with nothing asserting that it had — so *"the writer re-derives the
     * exemptions, so a tenant named for a needle keeps its draft"* now drives
     * this method through that caller rather than only directly.
     *
     * ⚠️ THE EXEMPTION SET IS JUDGED AGAINST THE NEEDLES ACTUALLY IN PLAY, not
     * against `FORBIDDEN` alone. A detailer trading as *"Star Review Auto"*
     * carries a recovery needle inside its own configured name, and an exemption
     * admitted only for money-and-legal phrases would leave that tenant refused
     * on every recovery draft forever — 1735's law-firm failure, reproduced by
     * bolting a second list on without threading it through `exemptSpans()`.
     *
     * ⚠️ AND IT NORMALISES FURTHER THAN `allows()` DOES — see `CONFUSABLE_FOLD`.
     * `allows()` is left on the separator fold alone, because widening it changes
     * what gets published on a tenant's Google listing.
     *
     * @param  list<string>  $ignoring  Values the *tenant* configured — see tenantValues().
     */
    public function allowsRecoveryOutreach(string $text, array $ignoring = []): bool
    {
        return $this->allowsAgainst(
            $text,
            self::FORBIDDEN,
            self::RECOVERY_FORBIDDEN_PATTERNS,
            $ignoring,
            confusables: true,
        );
    }

    /**
     * The matcher both predicates above are.
     *
     * ⚠️ TWO NEEDLE KINDS, AND THEY ARE TWO KINDS ON PURPOSE (4460). `$needles`
     * are substrings and `$patterns` are anchored regexes. Rewriting `FORBIDDEN`
     * as `\brefund\b` would stop it matching `refunded` and `nonrefundable` — a
     * silent weakening of the published-reply path, in the wave that was widening
     * the other one. Both kinds share one coordinate space and one exemption set,
     * so a tenant's own name exempts either.
     *
     * @param  list<string>  $needles  matched as substrings
     * @param  list<non-empty-string>  $patterns  matched as anchored regexes
     * @param  list<string>  $ignoring
     */
    private function allowsAgainst(
        string $text,
        array $needles,
        array $patterns,
        array $ignoring,
        bool $confusables,
    ): bool {
        // ⚠️ LOWERCASE FIRST, FOLD SECOND, AND THE REASON IS MALFORMED INPUT
        // RATHER THAN CASE (1980). The obvious rationale — that `mb_strtolower`
        // can change the character count (`İ` → `i` + U+0307, 1749) and so must
        // not run after a fold that cannot — is not the load-bearing one: the
        // two commute on **every** code point on this build, checked rather than
        // assumed. What the order actually buys is that `mb_strtolower()`
        // substitutes invalid bytes, so `preg_replace()` inside `fold()` is
        // handed valid UTF-8 and cannot return null. Swap them and a reply
        // containing one stray byte silently skips the fold entirely — pinned by
        // *the fold refuses through invalid UTF-8 rather than opening on it*,
        // which is the test that goes red when somebody reorders this line.
        $haystack = $this->normalise($text, $confusables);
        $exempt = $this->exemptSpans($haystack, $ignoring, $needles, $patterns, $confusables);

        foreach ($needles as $needle) {
            foreach ($this->occurrences($haystack, $needle) as [$start, $end]) {
                if (! $this->coveredBy($start, $end, $exempt)) {
                    return false;
                }
            }
        }

        foreach ($patterns as $pattern) {
            $found = $this->patternOccurrences($haystack, $pattern);

            // ⚠️ FAILS CLOSED ON A REGEX THAT DID NOT RUN. `preg_match_all()`
            // returns false on a backtrack or recursion limit, and reading that
            // as "no forbidden phrase here" would turn a resource limit into a
            // permission — 398's shape, where something upstream answers for a
            // check that never ran.
            if ($found === null) {
                return false;
            }

            foreach ($found as [$start, $end]) {
                if (! $this->coveredBy($start, $end, $exempt)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Where in the haystack an exemption actually sits.
     *
     * Character offsets throughout — `mb_strpos` and `mb_strlen` agree on those,
     * and mixing them with byte offsets would misplace every span the moment a
     * tenant's name is not ASCII.
     *
     * @param  list<string>  $ignoring
     * @param  list<string>  $needles  the set in play for this call — see
     *                                 `allowsRecoveryOutreach()` for why it is a
     *                                 parameter rather than `self::FORBIDDEN`
     * @param  list<non-empty-string>  $patterns  the same, for the regex kind:
     *                                            a detailer trading as "Star
     *                                            Review Auto" carries its needle
     *                                            in a *pattern* now, and judging
     *                                            the exemption against
     *                                            `$needles` alone would refuse
     *                                            them for ever (1735, 4356)
     * @return list<array{int, int}>
     */
    private function exemptSpans(
        string $haystack,
        array $ignoring,
        array $needles,
        array $patterns,
        bool $confusables,
    ): array {
        $spans = [];

        foreach ($ignoring as $value) {
            // ⚠️ THE EXEMPTION IS FOLDED TOO, AND FOLDING ONLY THE HAYSTACK IS
            // THE PLAUSIBLE HALF-FIX THAT BREAKS A REAL TENANT (1977). An
            // exemption is matched as a substring *of the haystack*, so a
            // business whose configured name carries a non-breaking space would
            // stop matching its own occurrence the moment the haystack was
            // folded and it was not — and every reply naming them would throw at
            // the writer, forever, which is 1735's law-firm failure with a new
            // cause. It also has to happen before `containsForbidden()`, or a
            // tenant called `Gift{NBSP}Card Emporium` is refused as an exemption
            // that carries no needle.
            $value = $this->normalise(trim($value), $confusables);

            if ($value === '' || ! $this->containsForbidden($value, $needles, $patterns)) {
                continue;
            }

            foreach ($this->occurrences($haystack, $value) as $span) {
                $spans[] = $span;
            }
        }

        return $spans;
    }

    /**
     * Every occurrence of `$needle`, as `[start, end)` character offsets.
     *
     * ⚠️ THE SEARCH RESUMES AT `start + 1`, NOT AT `end`, AND IT CUTS BOTH WAYS
     * ON PURPOSE. For a **needle**, finding overlapping occurrences is the
     * stricter reading — none may be missed. For an **exemption** it is the more
     * permissive one, and it is still the correct one: where a tenant's name
     * overlaps itself, *every* one of those positions is a genuine occurrence of
     * the name they chose, and a needle sitting inside one of them sits inside
     * their own name. Stepping by `end` instead would drop the second span and
     * refuse text made of nothing but the tenant's name. Pinned by a test, so
     * that this paragraph is a claim somebody drove rather than one somebody
     * asserted (314–316).
     *
     * @return list<array{int, int}>
     */
    private function occurrences(string $haystack, string $needle): array
    {
        $found = [];
        $length = mb_strlen($needle);
        $offset = 0;

        while (($position = mb_strpos($haystack, $needle, $offset)) !== false) {
            $found[] = [$position, $position + $length];
            $offset = $position + 1;
        }

        return $found;
    }

    /**
     * Whether `[$start, $end)` lies entirely inside one of `$spans`.
     *
     * Entirely inside a *single* span, never inside the union of two: two
     * adjacent exemptions that happen to abut must not exempt a needle that
     * straddles them, because neither tenant value contains it.
     *
     * @param  list<array{int, int}>  $spans
     */
    private function coveredBy(int $start, int $end, array $spans): bool
    {
        foreach ($spans as [$from, $to]) {
            if ($start >= $from && $end <= $to) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every separator rewritten to the spelling the needles use, one for one.
     *
     * ⚠️ 1:1 IN CHARACTERS, AND THE REASON IS NOT THE ONE THIS FIX WAS HANDED
     * (1974). The brief said the property was load-bearing because a fold that
     * changed the character count would shift 1853's spans and exempt the wrong
     * region. Executed, that does not hold: `allows()` folds the haystack and
     * every exemption with *this* function and computes every offset against the
     * folded haystack, so needle spans and exemption spans share one coordinate
     * space whatever the fold does to length, and no offset is ever mapped back
     * to the original text. Checked rather than repeated, because a rationale
     * asserted beside a mechanism is what stops the next reader looking.
     *
     * What the property actually buys is that the fold is **conservative**: one
     * character in, one character out, so it can only re-spell a separator and
     * can never join two tokens, delete one, or manufacture a match that the
     * ASCII spelling would not have made. `gift\x{00A0}\x{00A0}card` stays
     * allowed, exactly as `gift  card` always has — the fold gives non-ASCII
     * separators parity with ASCII ones and deliberately nothing more.
     *
     * ⚠️ THE `?? $lowercased` CANNOT FIRE, AND WHAT KEEPS IT UNREACHABLE IS THE
     * CALL ORDER RATHER THAN ANYTHING HERE (1980). `preg_replace` returns null
     * only on a malformed subject, and both callers hand it a string that has
     * already been through `mb_strtolower()`, which substitutes invalid bytes.
     * Reorder either caller and this branch becomes not merely reachable but the
     * common case for hostile input, so the two lines are one fact and are
     * commented as one. Mutating this to `?? ''` **survives the suite**, and it
     * is left recorded rather than papered over: `''` would blank the haystack
     * and allow everything, so the safe spelling is kept even though nothing can
     * currently drive it (398).
     */
    private function fold(string $lowercased): string
    {
        return preg_replace(
            array_keys(self::SEPARATOR_FOLD),
            array_values(self::SEPARATOR_FOLD),
            $lowercased,
        ) ?? $lowercased;
    }

    /**
     * @param  list<string>  $needles
     * @param  list<non-empty-string>  $patterns
     */
    private function containsForbidden(string $lowercased, array $needles, array $patterns): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($lowercased, $needle)) {
                return true;
            }
        }

        foreach ($patterns as $pattern) {
            // A pattern that could not run leaves this value un-admitted rather
            // than admitted: an exemption is the one thing here that *weakens*
            // the match, so its failure mode is the opposite of the matcher's.
            $found = $this->patternOccurrences($lowercased, $pattern);

            if ($found !== null && $found !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every match of `$pattern`, as `[start, end)` **character** offsets, or null
     * when the regex could not be run.
     *
     * ⚠️ CHARACTER OFFSETS, CONVERTED FROM THE BYTE OFFSETS PCRE HANDS BACK.
     * `occurrences()` above works in characters because `mb_strpos` does, and
     * 1853's containment test compares the two kinds of span directly — mixing
     * coordinate systems would exempt the wrong region the moment a tenant's
     * name is not ASCII, which is precisely the bug that comment was written
     * about.
     *
     * @param  non-empty-string  $pattern
     * @return list<array{int, int}>|null
     */
    private function patternOccurrences(string $haystack, string $pattern): ?array
    {
        $matched = preg_match_all($pattern, $haystack, $matches, PREG_OFFSET_CAPTURE);

        if ($matched === false) {
            return null;
        }

        $found = [];

        /** @var list<array{0: string, 1: int}> $whole */
        $whole = $matches[0];

        foreach ($whole as [$text, $byteOffset]) {
            $start = mb_strlen(substr($haystack, 0, $byteOffset));
            $found[] = [$start, $start + mb_strlen($text)];
        }

        return $found;
    }

    /**
     * Lowercase, fold separators, and — on the recovery path only — fold
     * confusables.
     *
     * The order is `mb_strtolower()` first for the reason `allowsAgainst()`
     * gives at length: it substitutes invalid bytes, so everything downstream is
     * handed valid UTF-8 and `preg_*` cannot fail on the subject.
     */
    private function normalise(string $text, bool $confusables): string
    {
        $folded = $this->fold(mb_strtolower($text));

        return $confusables ? $this->confusableFold($folded) : $folded;
    }

    /**
     * Letters that are not letters, rewritten to the ones the patterns use.
     *
     * Fullwidth Latin and fullwidth digits are a contiguous range and are done
     * arithmetically rather than as sixty-two map entries; everything else is
     * `CONFUSABLE_FOLD`, where each line is a judgement somebody made.
     */
    private function confusableFold(string $lowercased): string
    {
        $mapped = strtr($lowercased, self::CONFUSABLE_FOLD);

        return (string) preg_replace_callback(
            '/[\x{FF10}-\x{FF19}\x{FF41}-\x{FF5A}]/u',
            static function (array $m): string {
                // The subject reached here through `mb_strtolower()` and the
                // pattern is `/u`-anchored on two contiguous ranges, so `$m[0]`
                // is one well-formed code point and `mb_ord()` cannot fail on
                // it — Larastan says so from the stub, and adding a `=== false`
                // arm here would be a branch nothing can drive.
                $code = mb_ord($m[0]);

                return chr($code - ($code >= 0xFF41 ? 0xFF41 - 0x61 : 0xFF10 - 0x30));
            },
            $mapped,
        );
    }
}
