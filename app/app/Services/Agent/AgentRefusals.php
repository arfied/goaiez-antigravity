<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Enums\AgentSkill;
use App\Services\Audit\PageText;
use App\Services\Reviews\ReplyGuardrails;

/**
 * What the assistant must never say — T176 §2.3 rails 5 and 7, patch P4.
 *
 * Rail 5: *"no prices off-list · no discounts or negotiation ('I can't adjust
 * pricing — {Owner} can') · no arrival-time promises unless the tenant set a
 * standard window · no legal/medical advice · no payment-status claims (R12) · no
 * availability invention."*
 *
 * Rail 7: *"banned-claims + disclosure lints run on the OUTBOUND path, not only
 * in drafting."* This class **is** that outbound pass — {@see AgentComposer} runs
 * it on the model's own words after generation, which is what makes rail 5 a
 * refusal a test can drive rather than a sentence in a prompt and a hope.
 *
 * ## ⛔ NOT `ReplyGuardrails`, AND THE DIFFERENCE IS THE QUESTION
 *
 * {@see ReplyGuardrails} answers *does this text offer money or take a legal
 * position*, over sixteen phrases, for a reply published on a Google listing. Its
 * own docblock forbids reaching for it to answer a different question — 1850 is
 * the entry, where it was used to decide whether a display name was safe and
 * answered *yes* for `<script>alert(1)</script>`. **Rail 5 is a different
 * question**: an arrival promise and an invented availability are not
 * compensation offers, and `ReplyGuardrails` is blind to both.
 *
 * ⚠️ **BUT ITS QUESTION IS STILL ASKED, BECAUSE AN AGENT TURN CAN ANSWER IT
 * TOO.** A model offering a refund over text is the same defect as one offering
 * it on a listing, so `AgentComposer` runs **both** passes and this class does
 * not re-implement the sixteen phrases. Two predicates, each answering the thing
 * it knows about.
 *
 * ## ⚠️ WHAT THIS DOES NOT DO, SAID PLAINLY (1858's RULE)
 *
 * It is a **pattern match over a fixed set of English phrasings**, not a
 * comprehension of meaning. It does not certify a message as safe:
 *
 *  - ✅ **A ZERO-WIDTH INSERTION AND A DOUBLE SEPARATOR ARE CLOSED — P19 (4188).**
 *    Both were pinned here as surviving evasions when this class shipped. They are
 *    **encoding evasions rather than meaning evasions**, which is what makes them
 *    worth closing and the two below not worth chasing: `kno<U+200B>ck` and
 *    `can  knock` are the same sentence to every human reader, so folding them
 *    cannot make this rail refuse a message somebody meant innocently — 511's
 *    test, passed rather than assumed. ⚠️ **AND THE THREAT MODEL IS RAIL 1's, NOT
 *    A MODEL WITH A TYPING HABIT**: a model does not spontaneously insert a
 *    zero-width space, but a customer's injected instruction can tell it to, and
 *    that chain is the one P19 exists to test.
 *  - ⛔ **IT READS ENGLISH ONLY, AND THAT IS DELIBERATELY NOT BEING PARTLY FIXED**
 *    (4189). §2.1 makes English the soft-launch template language, but the model
 *    *mirrors the customer's language when confident* — so a turn written in
 *    Spanish is unchecked by every needle here. Adding `descuento`, `rabais` and
 *    `Rabatt` was considered and **refused**: four languages out of thousands
 *    reads on this page as multilingual coverage and is 256's vacuous gate
 *    wearing a passport. **Localisation is Build 3 and rail 5 arrives with it,
 *    not after it.**
 *  - ⛔ **A PARAPHRASE PASSES AND NO PHRASE LIST CLOSES IT** (4190). "I could
 *    probably shave a bit off that for you" is a discount and matches nothing
 *    below. Adding `shave` refuses a decorator talking about shaving down a door,
 *    which is 511 exactly. **The standing instructions in `AgentComposer` are the
 *    only control that reaches this class of evasion**, and P19's eval for it is
 *    named for the limit rather than for the capability.
 *
 *
 * ## ⛔ THE FOURTH INVENTION — A DOCUMENT — IS REFUSED AS A LIMB, IN WRITING (6107)
 *
 * The standing instructions say *"Never invent a link, an address, a phone number
 * or a document."* Three of the four now have an outbound limb; **the document
 * half does not, and it is not owed one.** Written down rather than left as a
 * silence, because 314–316's failure is a claim standing while the mechanism is
 * missing, and 6107 records this class as its second instance:
 *
 *  - ✅ **THE DELIVERABLE FORM OF A DOCUMENT IN THIS SYSTEM IS A LINK, AND THAT
 *    IS ALREADY CHECKED.** Skill 7's grounding is `TenantLinkKind::Document` and
 *    its prompt line is *"send the matching one"* — so a document the assistant
 *    offers is a short link, and {@see self::linksOffList()} refuses one the
 *    prompt did not carry. ⚠️ **Today `AgentComposer::facts()` puts no document
 *    link in the prompt at all**, which is 4192's shape on a third skill, so
 *    every document link an assistant writes is refused already.
 *  - ⛔ **WHAT IS LEFT IS A PROSE CLAIM THAT A DOCUMENT EXISTS** — *"our warranty
 *    sheet covers that"* — **and no allowed set can answer it.** The other three
 *    limbs work because the forbidden thing has a *closed* comparison space: a
 *    figure, a host, a street line, ten digits. A document is referred to by
 *    whatever noun phrase the sentence happens to use, so the comparison space is
 *    English, and a predicate over it is either a model call on the outbound path
 *    — which `29` §2 forbids — or a phrase list that catches the four wordings
 *    somebody thought of. **That is 4190's paraphrase argument and 256's vacuous
 *    gate at once**, and shipping it would put a third claim in this docblock
 *    that a reviewer would then stop looking behind.
 *  - ⚠️ **AND THE HARM IS THE SMALLEST OF THE FOUR.** An invented link, address
 *    or phone number sends a member of the public somewhere; an invented document
 *    reference is a sentence the owner corrects on the next turn, on a thread
 *    rail 6 hands to them anyway. **The control that reaches it is the standing
 *    instruction**, which is where 4190 left the paraphrase class as well.
 *
 * ⛔ **SO IT IS A NET UNDER THE PROMPT, NOT A SUBSTITUTE FOR IT.** The standing
 * instructions in `AgentComposer` are the primary control; this catches the
 * common drift and, more importantly, makes the drift *falsifiable* — a
 * prompt-only rail is one nobody can write a red test for.
 */
final class AgentRefusals
{
    /**
     * Rail 5, one needle set per limb, already lowercased and already folded.
     *
     * ⚠️ **EVERY ENTRY IS A FIXED POINT OF {@see self::fold()}**, which is
     * `ReplyGuardrails::FORBIDDEN`'s rule and 1975's lesson: a needle spelled with
     * a non-breaking space would sit in this list, read as enforced, and match
     * nothing — 256's vacuous gate inside the control that is supposed to be
     * catching things. A test asserts it.
     *
     * ⚠️ **KEPT NARROW, BECAUSE A RAIL TUNED UNTIL IT STOPS CRYING WOLF IS ONE
     * TUNED UNTIL IT CATCHES NOTHING** (511). "We can be there soon" is not a
     * time promise and must pass; "we'll be there at 3pm" is, and is caught by
     * the time-shaped pattern rather than by a word list.
     *
     * @var array<string, list<string>>
     */
    private const array FORBIDDEN = [
        // Discounts and negotiation. §2.3: "I can't adjust pricing — {Owner} can."
        'discount' => [
            'discount',
            'i can knock',
            'we can knock',
            'take off',
            'ill do it for',
            "i'll do it for",
            'special price',
            'best price i can',
            'meet you in the middle',
            'negotiate',
            'haggle',
            'waive the fee',
            'waive the charge',
        ],

        // R12: the platform never asserts a payment happened.
        'payment_status' => [
            'payment received',
            'payment confirmed',
            'we have received your payment',
            'your payment has gone through',
            'that has been paid',
            'marked as paid',
            'paid in full',
        ],

        // Model fallbacks when a fact (like a price) is missing.
        'NO_FACT' => [
            'can look at pricing',
            'will confirm and come back',
        ],

        // Legal and medical advice.
        'advice' => [
            'you are entitled to',
            "you're entitled to",
            'you have a claim',
            'legally you',
            'you should sue',
            'that is covered by law',
            'you do not need a permit',
            "you don't need a permit",
            'take ibuprofen',
            'that sounds like',
            'you should see a doctor about',
        ],

        // Availability invented out of nothing.
        'availability' => [
            'we have a slot',
            'we are free',
            "we're free",
            'i can book you in',
            'i have booked you',
            'you are booked',
            "you're booked",
            'i have put you down',
        ],
    ];

    /**
     * Arrival-time promises, which are a *shape* rather than a phrase.
     *
     * ⛔ **A WORD LIST CANNOT DO THIS ONE.** "we'll be there at 3pm", "someone
     * will arrive by 14:00" and "engineer with you within 2 hours" share no
     * substring, and enumerating them is the losing half of the game. What they
     * share is a first-person arrival verb followed by a clock time or a
     * duration, and that is what these match.
     *
     * ⚠️ **THE TENANT MAY SET A STANDARD WINDOW AND THEN THIS IS PERMITTED** —
     * §2.3 says *"unless the tenant set a standard window"*. No such setting
     * exists in this schema today, so the exemption has nothing to key on and
     * every arrival promise is refused. **Stated rather than half-built**: an
     * `$allowedWindow` parameter with no writer would be 272's shape on a
     * compliance rail, and the conservative direction is the one to sit in while
     * the setting is missing.
     *
     * @var list<string>
     */
    private const array ARRIVAL_PATTERNS = [
        // "…be there at 3pm", "…arrive by 14:00", "…with you at 9 am"
        '/\b(be there|arrive|get there|be with you|come out|be round)\b[^.!?]{0,40}?\b(at|by|before)\b\s*\d{1,2}\s*(:\s*\d{2})?\s*(am|pm|o.clock)?/u',
        // "…within 2 hours", "…in 30 minutes", "…in half an hour"
        '/\b(be there|arrive|get there|be with you|come out|be round|with you)\b[^.!?]{0,40}?\b(within|in)\b\s*(\d{1,3}|half an|an)\s*(hour|hours|hr|hrs|min|mins|minute|minutes)/u',
    ];

    /**
     * Separator spellings folded to the ones the needles use.
     *
     * ⚠️ **BORROWED FROM `ReplyGuardrails::SEPARATOR_FOLD`, INCLUDING ITS TWO
     * CORRECTIONS** (1972, 1973): `\s` rather than `\p{Zs}` because a message may
     * carry a line break, and `\p{Pd}` rather than a hand-picked dash list. Copied
     * rather than shared because the two classes answer different questions and a
     * shared private constant is a coupling that would make changing one change
     * the other silently; a test pins that both remain fixed points of their own
     * fold.
     *
     * ⛔ **P19 ADDED THE FIRST ARM AND A `+` TO THE SECOND, AND THE ORDER OF THE
     * THREE IS LOAD-BEARING** (4188). `preg_replace()` applies an array of
     * patterns in order, so the invisibles have to be **deleted before** the
     * whitespace collapse — a U+200B folded to a space first would leave
     * `kno ck`, which is not the sentence anybody wrote and matches nothing
     * either. Deleted rather than folded because a zero-width character occupies
     * no width: it is *absent* to the reader, so absence is the faithful
     * normalisation.
     *
     * ⚠️ **`\p{Cf}` IS THE WHOLE FORMAT-CHARACTER CATEGORY, NOT A LIST OF FOUR.**
     * U+200B/C/D, U+FEFF, U+00AD and the bidi controls are all `Cf`, and
     * enumerating the ones somebody thought of is 1952's defect — a lint that
     * catches the mutation its author imagined rather than the one it was written
     * for. ⛔ **AND IT IS NOT `\p{C}`**: that would swallow `\p{Cc}`, taking the
     * newline this fold's own first correction exists to preserve.
     *
     * ⚠️ **WHAT THE COLLAPSE STILL DOES NOT CLOSE**: `k n o c k`, one separator
     * per letter, folds to itself and matches nothing. Closing that means
     * deleting all whitespace before matching, which makes `we can` match inside
     * `we cannot` — refusing the right answer, which is the failure mode 511
     * names and this class's needles are spaced to avoid.
     *
     * @var array<string, string>
     */
    private const array SEPARATOR_FOLD = [
        '/\p{Cf}/u' => '',
        '/[\s\x{115F}\x{1160}\x{3164}\x{FFA0}]+/u' => ' ',
        '/\p{Pd}/u' => '-',
    ];

    /**
     * What a link looks like in a text message — rail 5's *"never invent a link"*.
     *
     * ⛔ **THIS RAIL EXISTED ONLY IN THE PROMPT UNTIL P19** (4187). The standing
     * instructions have said *"Never invent a link, an address, a phone number or
     * a document. Use only what you were given"* since P4, and **nothing checked
     * it on the way out** — the exact shape rail 7 exists to forbid, and the one
     * `AgentComposer`'s own docblock claims to have closed for rail 5's other
     * limbs. A prompt-only rail is one nobody can write a red test for.
     *
     * ⚠️ **FOUR ARMS, BECAUSE AN SMS DOES NOT WRITE URLs THE WAY A DOCUMENT
     * DOES.** A scheme; a `www.` host; **any host with a path**, which is what
     * makes `ledgerplumbing.co.nz/book` reachable without enumerating the world's
     * TLDs; and a bare host on a short list of TLDs, for `book.ledgerplumbing.com`
     * with no path at all. The third arm is why the fourth can stay short.
     *
     * ⚠️ **THE LOOKBEHIND IS WHAT KEEPS AN EMAIL ADDRESS OUT.** `info@ledger.com`
     * carries a host and is not a link, and refusing every reply that offers an
     * email address is a rail somebody deletes. ⛔ **An invented *email* address is
     * therefore not caught by this** — stated rather than implied; it is a
     * different predicate and P19 did not write it.
     *
     * ⚠️ **THE HYPHEN IN THE LOOKBEHIND WAS ADDED BECAUSE THE FIRST DRAFT WITHOUT
     * IT MATCHED INSIDE AN EMAIL ADDRESS ANYWAY, AND THE TEST WRITTEN FOR THE
     * LIMIT IS WHAT FOUND IT.** `bookings@ledger-plumbing.example.com` blocks at
     * `@l` and again at every `\w`, and then **starts cleanly at `plumbing`**,
     * because a hyphen is none of `\w`, `@` or `.` — so the predicate reported an
     * off-list link in a reply offering an email address. **A lookbehind is a claim
     * about every character that can precede a host**, and enumerating three of the
     * four is 1952's shape.
     */
    private const string LINK_PATTERN = '~
        (?<![\w@.\-])
        (?:
            https?://[^\s<>"\'\]\)]+
          | www\.[^\s<>"\'\]\)]+
          | [a-z0-9](?:[a-z0-9\-]*[a-z0-9])?(?:\.[a-z0-9\-]+)*\.[a-z]{2,24}/[^\s<>"\'\]\)]*
          | [a-z0-9](?:[a-z0-9\-]*[a-z0-9])?(?:\.[a-z0-9\-]+)*\.(?:com|net|org|io|ai|app|co|uk|us|biz|info|shop|site|online|link|page|dev|me)\b
        )
    ~xiu';

    /**
     * The closed vocabulary of street-type words — rail 5's *"never invent an
     * address"*, 6107.
     *
     * ⛔ **THIS IS NOT A DETECTOR THAT RECOGNISES ADDRESSES, AND THE DIFFERENCE
     * IS THE WHOLE DESIGN.** 231 is the entry: the audit's own permissive
     * phone-shaped pattern spanned a street number and *told a business its own
     * number was missing from its own homepage*, and `PageText::containsPhone()`
     * records that the extract-candidates-then-compare approach was its **second**
     * design because the first was wrong in that exact way. A general address
     * detector on an outbound reply is that failure with a send on the end of it.
     * What this is instead is {@see self::quotesOffList()}'s shape: that limb does
     * not decide *"is this a price"* either — it requires a currency symbol or one
     * of six currency **words**, a closed set, and compares what it finds against
     * what the business stated. `85 dollars` is to `$` what `12 High Street` is
     * to this list.
     *
     * ⚠️ **THE UNIT IS `<house number?> <name word> <type>`, WHICH IS ONE WORD OF
     * NAME AND NOT THE FORMATTED STRING.** 231's own second half says why:
     * *"a footer writes an address a dozen ways and matching the whole string
     * would report a mismatch on nearly every real website"*, so it anchors on
     * components. **The cost is stated rather than hidden**: `Old Mill Road` and
     * `New Mill Road` are one unit to this limb, so a business on one permits its
     * assistant writing the other. That is the loose direction, chosen
     * deliberately — see {@see self::addressesOffList()}.
     *
     * @var array<string, string> spelling → the canonical form both sides compare on
     */
    private const array STREET_TYPES = [
        'street' => 'street',
        'st' => 'street',
        'road' => 'road',
        'rd' => 'road',
        'avenue' => 'avenue',
        'ave' => 'avenue',
        'boulevard' => 'boulevard',
        'blvd' => 'boulevard',
        'lane' => 'lane',
        'ln' => 'lane',
        'highway' => 'highway',
        'hwy' => 'highway',
        'parkway' => 'parkway',
        'pkwy' => 'parkway',
        'crescent' => 'crescent',
        'drive' => 'drive',
        'dr' => 'drive',
        'court' => 'court',
        'ct' => 'court',
        'place' => 'place',
        'pl' => 'place',
        'square' => 'square',
        'sq' => 'square',
        'circle' => 'circle',
        'terrace' => 'terrace',
        'close' => 'close',
        'way' => 'way',
        'walk' => 'walk',
        'row' => 'row',
        'mews' => 'mews',
        'plaza' => 'plaza',
        'gardens' => 'gardens',
    ];

    /**
     * The types that mean nothing without a house number in front of them.
     *
     * ⛔ **EVERY ENTRY HERE IS AN ORDINARY ENGLISH WORD AS WELL AS A STREET
     * TYPE**, and admitting them bare is how this rail would refuse *"we can send
     * someone your way"*, *"a two-way street"*, *"in the drive"* and *"we close at
     * five"*. 511's rule cuts both ways and this is the side of it that matters
     * here: a rail that fires on the right answer is one somebody deletes.
     *
     * ⚠️ **THE ABBREVIATIONS ARE HERE FOR A SECOND REASON — `st` IS ALSO
     * `Saint` AND `dr` IS ALSO `Doctor`.** *"closed on St Patrick's day"* and
     * *"Dr Okafor will call you"* carry no house number, so requiring one costs
     * this limb *"we're on High St"* — a real miss, in the permissive direction —
     * and buys back a refusal on two sentences a small business genuinely writes.
     *
     * @var list<string>
     */
    private const array NUMBERED_STREET_TYPES = [
        'st', 'rd', 'ave', 'blvd', 'ln', 'hwy', 'pkwy', 'dr', 'ct', 'pl', 'sq',
        'drive', 'court', 'place', 'square', 'circle', 'terrace', 'close', 'way',
        'walk', 'row', 'mews', 'plaza', 'gardens',
    ];

    /**
     * Words that cannot be the last word of a street name.
     *
     * ⚠️ **FUNCTION WORDS AND UNITS OF MEASURE, CLOSED AND ARGUED RATHER THAN
     * TUNED.** 511 warns about a rail tuned until it stops crying wolf, and the
     * distinction that keeps this list honest is that **no entry is a proper
     * noun**: a street is named after a person, a place or a thing, so *"the"*,
     * *"of"*, *"on"* and *"minutes"* can never be the word before `Street` in an
     * address. `main` and `high` are deliberately absent — they are the two
     * commonest street names there are.
     *
     * ⛔ **IT IS APPLIED WITH A HOUSE NUMBER PRESENT AS WELL AS WITHOUT ONE**,
     * which costs the real UK address *"12 The Drive"* and is what stops
     * *"we open at 9 on St Patrick's day"* reading as `9 on st`. The miss is
     * silent and the false refusal would not be.
     *
     * @var list<string>
     */
    private const array NOT_A_STREET_NAME = [
        'a', 'an', 'and', 'any', 'are', 'as', 'at', 'be', 'been', 'by', 'each',
        'every', 'for', 'from', 'her', 'here', 'his', 'i', 'in', 'into', 'is',
        'it', 'its', 'me', 'my', 'near', 'no', 'of', 'off', 'on', 'one', 'or',
        'our', 'out', 'over', 'some', 'that', 'the', 'their', 'them', 'these',
        'they', 'this', 'those', 'to', 'up', 'us', 'was', 'we', 'were', 'you',
        'your',
        // Units of measure, which is how a distance reads as an address:
        // "5 minutes down Elm Street" is caught on `elm`, never on `minutes`.
        'day', 'days', 'hour', 'hours', 'hr', 'hrs', 'metres', 'meters', 'mile',
        'miles', 'min', 'mins', 'minute', 'minutes', 'week', 'weeks', 'yards',
    ];

    /**
     * What a phone number looks like in a text message — rail 5's *"never invent
     * a phone number"* (6107).
     *
     * ⛔ **TEN TO FIFTEEN DIGITS, AND AT MOST TWO SEPARATOR CHARACTERS BETWEEN
     * ANY TWO OF THEM.** Both bounds are `PageText::containsPhone()`'s, borrowed
     * with its reasoning: a separator run permissive enough to span
     * `(901) 555-0134` also spans the space into whatever follows, *"so
     * `(901) 555-0134` beside a street number matched as `(901) 555-0134 1234`
     * and its last ten digits were nobody's phone number"* — 231, and the reason
     * that class searches for a known number rather than extracting candidates.
     * Two characters is what `(901) ` costs and nothing wider is permitted.
     *
     * ⚠️ **A RUN OF SIXTEEN OR MORE DIGITS MATCHES NOTHING AT ALL**, which falls
     * out of the trailing `(?!\d)` and is deliberate: an order reference or an
     * account number is not a phone number, and the leading `(?<!\d)` stops the
     * pattern re-entering the run one digit along to find one inside it.
     *
     * ⛔ **AND NOTHING SHORTER THAN TEN DIGITS IS SEEN.** `PageText::phoneDigits()`
     * — which is this limb's normaliser, on 6110's instruction that a second
     * answer to *"is this the same phone number"* is how the two come to disagree
     * — returns null below ten, because *"a four-digit extension must never match
     * a page"*. So a seven-digit local number invented by the model walks
     * straight through, and that is a named limit rather than an implied one.
     */
    private const string PHONE_PATTERN = '/(?<!\d)\+?\d(?:[\s().\-]{0,2}\d){9,14}(?!\d)/u';

    /**
     * Whether this draft may be sent, and which rail refused it if not.
     *
     * ⚠️ **IT RETURNS THE LIMB RATHER THAN A BOOLEAN**, because the three
     * consumers need different things: the composer needs *stop*, the run row
     * needs *why*, and the owner notify needs to say what the assistant declined
     * to answer. A boolean would send all three to re-derive it.
     *
     * ⚠️ **THE PRICE LIMB IS NOT HERE AND CANNOT BE.** *"No prices off-list"* is
     * not answerable from the text alone — a figure in a message is legitimate
     * exactly when it is on the tenant's list, so the check needs the list.
     * {@see self::quotesOffList()} takes it.
     *
     * @return ?string the limb of rail 5 that refused, or null when nothing did
     */
    public function refusalIn(string $text): ?string
    {
        $haystack = $this->fold(mb_strtolower($text));

        foreach (self::FORBIDDEN as $limb => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $limb;
                }
            }
        }

        foreach (self::ARRIVAL_PATTERNS as $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                return 'arrival_time';
            }
        }

        return null;
    }

    /**
     * Whether the draft quotes a figure this business does not list — rail 5's
     * first limb and R13's *"never invent a price"*.
     *
     * ⚠️ **THE SUBJECT IS A CURRENCY-SHAPED FIGURE, NOT ANY NUMBER.** A message
     * saying *"we've done that job 200 times"* carries a number and no price;
     * requiring the currency symbol or a decimal amount is what keeps this from
     * refusing every reply containing a house number.
     *
     * ⛔ **AND AN EMPTY ALLOWED SET REFUSES EVERY FIGURE, WHICH IS THE POINT.**
     * A business with no price list has skill 4 dark, so a figure in its
     * assistant's message came from nowhere — that is precisely the invention
     * R13 forbids, and it is the case this method exists for rather than an edge
     * of it.
     *
     * ⚠️ **WHAT IT CANNOT SEE**: a price written in words ("two hundred pounds"),
     * a figure the model rounded off a listed one, and a listed figure quoted for
     * the wrong job. The first is a real gap; the third is not this predicate's
     * question at all.
     *
     * @param  list<string>  $allowed  Every figure this business lists, as the
     *                                 minor-unit-formatted strings a message
     *                                 would carry — `PriceList` renders them.
     */
    public function quotesOffList(string $text, array $allowed): bool
    {
        $haystack = $this->fold(mb_strtolower($text));

        // A currency symbol followed by digits, or digits followed by a currency
        // word. Deliberately not `\d+` alone — see above.
        if (preg_match_all('/(?:[$£€]\s?\d[\d,]*(?:\.\d{1,2})?)|(?:\d[\d,]*(?:\.\d{1,2})?\s?(?:dollars|pounds|euros|usd|gbp|eur))/u', $haystack, $matches) < 1) {
            return false;
        }

        $permitted = [];

        foreach ($allowed as $figure) {
            $permitted[] = $this->normaliseFigure($figure);
        }

        foreach ($matches[0] as $found) {
            if (! in_array($this->normaliseFigure($found), $permitted, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the draft carries a link this business never gave the assistant —
     * rail 5's *"never invent a link"*, enforced on the way out (4187).
     *
     * ⛔ **THE RULE IS "A LINK IS PERMITTED EXACTLY WHEN IT APPEARS IN WHAT THE
     * MODEL WAS GIVEN", AND IT IS NOT "REFUSE EVERY URL".** The difference is the
     * whole design. Refusing every URL was a gate that would have been true by
     * accident while `AgentComposer::facts()` put no link in the prompt for
     * anybody, and would have become wrong — silently, and in the permissive
     * direction — the day P6 wired the booking link in. ✅ **P6 HAS WIRED IT IN
     * (4271) AND THIS PREDICATE NEEDED NO EDIT**, which is the design paying out:
     * the allowed set widened itself the moment the grounding arrived, and a
     * booking short link is now permitted for a tenant that has one while every
     * neighbouring URL is still refused.
     *
     * ⚠️ **AND IT MAKES THE PREDICATE FALSIFIABLE IN BOTH DIRECTIONS.** A rail
     * whose allowed set is empty for every fixture is `sending_health_windows`
     * with a URL in it: the guard is invisible, and deleting it leaves the suite
     * green. That is L7's own M19 finding on the price limb, one limb over, so the
     * eval for this one is built with a business whose notes *do* carry a URL —
     * and, since 4271, with a business whose **booking** link is in the prompt and
     * whose assistant is caught writing a neighbouring one.
     *
     * ⚠️ **THE CUSTOMER'S OWN MESSAGE IS NOT PART OF THE ALLOWED SET**, though it
     * is technically something the model "was given". A number this platform owns
     * emitting a URL it was handed by a stranger is a redistribution surface with
     * the tenant's name on it, and the cost of the strictness is one refusal that
     * lands on the owner rather than one link that lands on a phone.
     *
     * @param  list<string>  $allowed  Every link the prompt actually carried, as
     *                                 {@see self::linksIn()} renders them.
     */
    public function linksOffList(string $text, array $allowed): bool
    {
        $found = $this->linksIn($text);

        if ($found === []) {
            return false;
        }

        $permitted = [];

        foreach ($allowed as $link) {
            $permitted[] = $this->normaliseLink($link);
        }

        foreach ($found as $link) {
            if (! in_array($link, $permitted, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every link in a piece of text, normalised for comparison.
     *
     * ⚠️ **ONE EXTRACTOR FOR BOTH SIDES OF THE COMPARISON**, which is
     * `AgentComposer::money()`'s argument in the other limb: the facts block is
     * read with this method and so is the model's reply, so a link that was given
     * cannot fail to be recognised because two readers disagreed about a trailing
     * slash.
     *
     * @return list<string>
     */
    public function linksIn(string $text): array
    {
        if (preg_match_all(self::LINK_PATTERN, $this->fold(mb_strtolower($text)), $matches) < 1) {
            return [];
        }

        $links = [];

        foreach ($matches[0] as $found) {
            $links[] = $this->normaliseLink($found);
        }

        return $links;
    }

    /**
     * A link reduced to the part that identifies it.
     *
     * ⚠️ **SCHEME, `www.`, A TRAILING SLASH AND TRAILING SENTENCE PUNCTUATION ALL
     * COME OFF**, because a model writes `book.example.com` for a list that holds
     * `https://book.example.com/` and ends the sentence with a full stop. Treating
     * those as different links refuses the correctly-quoted one, which is the
     * failure mode {@see self::normaliseFigure()} was written to avoid one limb
     * over.
     *
     * ⚠️ **THE PATH IS LOWERCASED WITH THE HOST**, which a URL's own rules do not
     * do. The consequence is stated rather than hidden: a business given
     * `…/Book` permits an assistant writing `…/book`. That is a link off by its
     * capitalisation and pointing at the same tenant's own domain, and it is a
     * narrower hazard than refusing a reply because the model tidied a capital.
     */
    private function normaliseLink(string $link): string
    {
        $trimmed = rtrim(trim($link), ".,;:!?)]}'\"");
        $lowered = mb_strtolower($trimmed);
        $withoutScheme = preg_replace('~^https?://~', '', $lowered) ?? $lowered;
        $withoutHost = preg_replace('~^www\.~', '', $withoutScheme) ?? $withoutScheme;

        return rtrim($withoutHost, '/');
    }

    /**
     * Whether the draft carries a street address this business never stated —
     * rail 5's *"never invent an address"*, enforced on the way out (6107).
     *
     * ⛔ **THE RULE IS `linksOffList()`'s AND IT IS NOT "REFUSE EVERY ADDRESS".**
     * The allowed set is derived from the facts block the model was actually
     * given, so the limb permits repetition and refuses invention, and it
     * **widens itself the moment the grounding arrives** — which is 4187's design
     * paying out a second time. Today that grounding is real and reachable: 6104
     * put `AgentSkills::addressFor()`'s answer in the prompt whenever skill 2 is
     * lit, and a tenant's uploaded notes can carry an address whether or not it
     * is. ⚠️ **A business that has stated nothing has an empty allowed set and
     * every street address in its assistant's reply is refused**, which is
     * {@see self::quotesOffList()}'s reading of the same situation and is the
     * case this limb mostly exists for: 6109 records that **no tenant in
     * production has stated an address at all**, so this is the live arm and not
     * the edge.
     *
     * ⛔ **IT ERRS LOOSE, AND THE DIRECTION WAS CHOSEN RATHER THAN FALLEN INTO.**
     * A comparison strict enough to catch every invention refuses a correctly
     * stated address the moment the model tidies `St.` to `Street` or drops the
     * town, and a rail that fires on the right answer is one somebody deletes
     * (511) — while the reply it replaces is the standing handoff line, which is
     * a good answer rather than a broken one. So the misses are named:
     *
     *  - **An address with no street-type word at all.** `Unit B, The Old Mill,
     *    Bakewell` is 6111's own example of a real address this platform must
     *    not refuse to *store*, and it is invisible here for the same reason.
     *  - **A neighbouring street name that shares its last word.** `Old Mill
     *    Road` and `New Mill Road` are one unit — see {@see self::STREET_TYPES}.
     *  - **A town, a postcode or a what3words on its own.** None of them is a
     *    street line.
     *  - **Anything not written in English.** Rail 5's standing limit (4189).
     *
     * ⚠️ **WHAT IT DOES CATCH IS THE ONE THAT SENDS SOMEBODY TO A DOOR**: a
     * street the business never named, and a **house number** on the right street
     * that the business never gave — `come to 45 High Street` from a business at
     * number 12 is refused, because the unit carries the number as well as the
     * name.
     *
     * @param  list<string>  $allowed  Every address the prompt actually carried.
     *                                 Raw address lines are accepted as readily
     *                                 as units: both sides go through
     *                                 {@see self::addressesIn()}, which is
     *                                 `AgentComposer::money()`'s one-formatter
     *                                 rule — the string the model was shown is
     *                                 read by the same reader that reads its
     *                                 reply.
     */
    public function addressesOffList(string $text, array $allowed): bool
    {
        $found = $this->addressesIn($text);

        if ($found === []) {
            return false;
        }

        $permitted = [];

        foreach ($allowed as $address) {
            foreach ($this->addressesIn($address) as $unit) {
                $permitted[] = $unit;
            }
        }

        foreach ($found as $unit) {
            if (! in_array($unit, $permitted, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every street line in a piece of text, as the units both sides compare on.
     *
     * ⚠️ **ONE EXTRACTOR FOR BOTH SIDES**, {@see self::linksIn()}'s rule: an
     * address that was given cannot fail to be recognised because two readers
     * disagreed about `St.` and `Street`.
     *
     * ⚠️ **A NUMBERED MATCH YIELDS TWO UNITS, THE NUMBERED ONE AND THE BARE
     * ONE**, and that is what lets a business at `12 High Street` have its
     * assistant say *"we're on High Street"* without being refused while
     * *"come to 45 High Street"* still is. Emitting both on the **draft** side
     * too costs nothing: any single off-list unit refuses the message.
     *
     * @return list<string>
     */
    public function addressesIn(string $text): array
    {
        $haystack = $this->fold(mb_strtolower($text));

        if (preg_match_all($this->addressPattern(), $haystack, $matches, PREG_SET_ORDER) < 1) {
            return [];
        }

        $units = [];

        foreach ($matches as $match) {
            $spelling = (string) $match['type'];
            $number = (string) ($match['num'] ?? '');
            $name = str_replace(["'", "\u{2019}"], '', (string) $match['name']);

            if (in_array($name, self::NOT_A_STREET_NAME, true)) {
                continue;
            }

            if ($number === '' && in_array($spelling, self::NUMBERED_STREET_TYPES, true)) {
                continue;
            }

            $unit = $name.' '.self::STREET_TYPES[$spelling];

            $units[] = $unit;

            if ($number !== '') {
                $units[] = $number.' '.$unit;
            }
        }

        return array_values(array_unique($units));
    }

    /**
     * Whether the draft carries a phone number this business never gave the
     * assistant — rail 5's *"never invent a phone number"* (6107).
     *
     * ⛔ **THE ALLOWED SET IS WHAT THE PROMPT CARRIED, AND TODAY THAT IS ONLY
     * EVER A TENANT'S OWN UPLOADED NOTES.** Nothing in `AgentComposer::facts()`
     * puts a phone number in front of the model on purpose — ⚠️ **including
     * `UrgentTerms::emergencyLine()`, which skill 9's prompt line tells the model
     * to *"give … if one is listed"* and which is never listed anywhere the model
     * can see.** That is 4192's shape a third time and it is deliberately **not**
     * closed here: the composer only ever writes a turn on a message that matched
     * **no** urgent term, because {@see AgentTurns} escalates a matching one
     * deterministically before any model is reached — so putting
     * the emergency line in the facts would hand it out on the messages that are
     * *not* emergencies. `EscalateUrgentThreadJob` gives that number out from a
     * static template, which is the right door for it.
     *
     * ⛔ **SO THIS LIMB REFUSES EVERY PHONE NUMBER FOR ALMOST EVERY BUSINESS,
     * AND THAT IS THE CORRECT READING RATHER THAN A DECORATION.** *"Never invent
     * a phone number. Use only what you were given"* — given nothing, every
     * number is invented. It is written as an allowed set rather than as
     * *"refuse every number"* for 4187's reason: the flat refusal would be true
     * today by accident and would go wrong **silently and in the permissive
     * direction** the day anything puts a number in the prompt.
     *
     * ⚠️ **THE CUSTOMER'S OWN NUMBER IS NOT IN THE ALLOWED SET**, on 4187's
     * ruling for links, so the assistant cannot read a number back to the person
     * who just typed it — *"I'll get them to ring you on 901-555-0182"* becomes
     * the handoff line. **The cost is one flat reply and it is the conservative
     * side**: a number this platform owns emitting a number a stranger handed it
     * is a redistribution surface with the tenant's name on it, and the
     * alternative needs the customer's message threaded into the outbound pass,
     * where every value is attacker-controlled.
     *
     * ⚠️ **WHAT IT CANNOT SEE**: a number written in words, a seven- or
     * nine-digit local number ({@see self::PHONE_PATTERN}), and a number spread
     * across more than two characters of punctuation per digit.
     *
     * @param  list<string>  $allowed  Every number the prompt actually carried.
     *                                 Raw as written or already reduced to
     *                                 digits — both sides go through
     *                                 {@see self::phonesIn()}.
     */
    public function phonesOffList(string $text, array $allowed): bool
    {
        $found = $this->phonesIn($text);

        if ($found === []) {
            return false;
        }

        $permitted = [];

        foreach ($allowed as $phone) {
            foreach ($this->phonesIn($phone) as $digits) {
                $permitted[] = $digits;
            }
        }

        foreach ($found as $digits) {
            if (! in_array($digits, $permitted, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every phone number in a piece of text, reduced to what identifies it.
     *
     * ⛔ **THE NORMALISER IS `PageText::phoneDigits()` AND IT IS BORROWED RATHER
     * THAN REWRITTEN** — 6110's instruction in as many words: *"is this the same
     * phone number"* already has an implementation here, and *"a second answer is
     * how the two come to disagree"*. Its rule is the **last ten digits**,
     * because `+1 (555) 010-1234`, `555-010-1234` and `5550101234` are one number
     * and the country code is the part that comes and goes. `normaliseFigure()`
     * is the precedent one limb over: `$85`, `85.00` and `85 dollars` are one
     * figure, and treating them as three would refuse every correct answer.
     *
     * ⚠️ **LAST-TEN COLLIDES ACROSS COUNTRY CODES IN PRINCIPLE**, and the
     * direction of that collision is permissive — it can let an invented number
     * through, never refuse a given one.
     *
     * @return list<string>
     */
    public function phonesIn(string $text): array
    {
        if (preg_match_all(self::PHONE_PATTERN, $this->fold(mb_strtolower($text)), $matches) < 1) {
            return [];
        }

        $numbers = [];

        foreach ($matches[0] as $found) {
            $digits = PageText::phoneDigits($found);

            if ($digits !== null) {
                $numbers[] = $digits;
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * The street-line pattern, built from {@see self::STREET_TYPES} so the
     * vocabulary is written down exactly once.
     *
     * ⚠️ **BUILT RATHER THAN WRITTEN OUT, BECAUSE A SECOND COPY OF THE LIST IS A
     * LIST THAT DRIFTS** — 1975's argument for the fixed-point test one limb
     * over. A constant cannot call `implode()`, so this is a method.
     *
     * ⚠️ **THE HOUSE NUMBER IS GUARDED BY `(?<!\d)`**, without which `12-14 High
     * Street` would re-enter the run and report the house number as `4`.
     */
    private function addressPattern(): string
    {
        $types = implode('|', array_keys(self::STREET_TYPES));

        return '/(?:(?<!\d)(?<num>\d{1,6}[a-z]?)\s+)?\b(?<name>[a-z\'\x{2019}]+)\s+(?<type>'.$types.')\b/u';
    }

    /**
     * A figure reduced to its digits, so that `$85`, `85.00` and `85 dollars`
     * compare equal.
     *
     * ⚠️ **TRAILING ZERO CENTS ARE DROPPED**, because a price list holding `8500`
     * minor units renders as `$85.00` while a model writes `$85`, and treating
     * those as different figures would refuse every correctly-quoted price — a
     * rail that fires on the right answer is one that gets deleted.
     */
    private function normaliseFigure(string $figure): string
    {
        $digits = preg_replace('/[^\d.]/u', '', $figure) ?? '';

        if (str_contains($digits, '.')) {
            $digits = rtrim(rtrim($digits, '0'), '.');
        }

        return $digits;
    }

    private function fold(string $lowercased): string
    {
        return preg_replace(
            array_keys(self::SEPARATOR_FOLD),
            array_values(self::SEPARATOR_FOLD),
            $lowercased,
        ) ?? $lowercased;
    }

    /**
     * The line the assistant says instead, when rail 5 refused what it wrote.
     *
     * ⚠️ **IT NEVER DEAD-ENDS AND IT NEVER EXPLAINS ITSELF.** Rail 6's rule is
     * that the thread always gets something; telling a customer *"my safety
     * filter blocked that"* is how this platform is built, which is what `22`'s
     * outcome-language rule forbids. The owner is told; the customer is answered.
     *
     * ⚠️ **`{Owner}` IS THE BUSINESS NAME, NOT A PERSON'S**, matching §2.1's
     * *"{Business}'s assistant"* — this application does not reliably hold an
     * owner's first name and a message addressing somebody by the wrong one is
     * worse than one that does not.
     */
    public function replacementFor(string $businessName, ?string $limb = null): string
    {
        if ($limb === 'discount') {
            // §2.3 gives this one its own words, so it is said rather than
            // collapsed into the generic line: a customer asking for money off
            // is asking a question, and "I'll get somebody to confirm" reads as
            // a maybe.
            return "I can't adjust pricing, but {$businessName} can — I'll pass this on and they'll come back to you.";
        }

        return "Thanks — I'll get {$businessName} to confirm that and come back to you.";
    }

    /**
     * The skills whose absence this class is the backstop for.
     *
     * Used by the composer's own tests to state the relationship rather than
     * imply it: rail 5's limbs map onto the skills R13 switches off, and each
     * refusal here is the second layer under one of them.
     *
     * ⚠️ **P19 READS THIS AS ITS INDEX OF WHAT TO EVALUATE**, which is what the
     * two limbs added below are for: the map was the five phrase-and-shape limbs,
     * and the two limbs that need a *list* to answer — the price and the link —
     * were absent from it, so an eval suite driven off this map would have had a
     * hole exactly where R13's two invention failures live.
     *
     * ⛔ **THE KEYS ARE THE `fallbackReason` STRINGS `AgentComposer` WRITES**, so
     * `off_list_price` and `off_list_link` are spelled the way the run row spells
     * them. A map keyed on a prettier name would be a second vocabulary for the
     * same events, and the eval suite asserts an exact reason precisely so that a
     * case cannot pass behind a different guard's refusal (398).
     *
     * @return array<string, AgentSkill>
     */
    public function limbToSkill(): array
    {
        return [
            'discount' => AgentSkill::Quotes,
            'payment_status' => AgentSkill::CallOutFee,
            'availability' => AgentSkill::BookAppointment,
            'arrival_time' => AgentSkill::BookAppointment,
            'advice' => AgentSkill::CustomerQuestions,
            'off_list_price' => AgentSkill::Quotes,
            'NO_FACT' => AgentSkill::Quotes,
            'off_list_link' => AgentSkill::BookAppointment,
            'off_list_address' => AgentSkill::HoursAndDirections,
            // ⚠️ **SKILL 9 IS THE ONLY SKILL THAT WOULD LEGITIMATELY GIVE A
            // PHONE NUMBER OUT** — its prompt line says *"give the emergency
            // line if one is listed"* — and it is mapped here even though
            // `UrgentTerms::emergencyLine()` never reaches the prompt.
            // {@see self::phonesOffList()} argues why it must not, and the map
            // records the skill this limb is the second layer under rather than
            // the skill that happens to be able to fill its allowed set.
            'off_list_phone' => AgentSkill::UrgentEscalation,
        ];
    }
}
