<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a satisfied customer may be sent to leave a public review.
 *
 * ⚠️ YELP IS HERE BY THE OWNER'S RULING, WITH THE PENALTY IN VIEW — DECISION 112
 * IS REVERSED AT 1160, AND DO NOT RE-OPEN THE ARGUMENT. Yelp's Content
 * Guidelines say plainly *"Businesses should never ask customers to write
 * reviews"* (read live 2026-08-07), enforced by a search ranking penalty and, in
 * the extreme, a public **Consumer Alert on the tenant's own page**. The case
 * against was put to the owner twice and their answer was *"we want it so build
 * it"*. **Both positions stay written up in `DECISIONS.md`**, on decision 110's
 * pattern.
 *
 * ⚠️ **The penalty lands on the tenant, not on us**, and no code here reduces
 * it. In particular the pasted-link path of 1162 is not a mitigation: the
 * penalty attaches to *asking*, never to how the URL was obtained.
 *
 * WHAT THE REVERSAL DID NOT DO — `29` §12.1's build-failing test is REPLACED,
 * NEVER DELETED (1161). The old rule was "Yelp cannot be enabled through config,
 * seed, or admin". The new rule is **"Yelp is enabled only through the
 * confirmed-listing path, and never by seed, default, or provisioning"**, and it
 * is still enforced in four places: this enum, `DestinationSettings`, a CHECK,
 * and the lint in `tests/Feature/Architecture/ReviewsTest.php`. A policy the
 * owner changed is a different act from a test somebody weakened to get green,
 * and it has to look different in the diff.
 *
 * Note that `ReviewSource::Yelp` predates all of this and was always correct:
 * reading a Yelp review we were given was permitted even while soliciting one
 * was not. Ingest and invitation are two different pipelines.
 *
 * EACH PLATFORM'S RULES LIVE HERE, not in whichever service happens to ask.
 * `24` §2.3.1 marks Google, Facebook and Trustpilot verified; Yelp's and BBB's
 * rules were read live on 2026-08-07. **This is now the whole of the v1 set the
 * owner confirmed at 1198.** Every other ⚠️ platform in that section —
 * TripAdvisor, Healthgrades, Vitals, RateMDs, Cars.com, DealerRater, Avvo,
 * Nextdoor — stays out until somebody reads its live terms, and the healthcare
 * three additionally sit behind the unbuilt PHI/BAA gate (decision 303).
 * ⚠️ **1144, 1166 and 1201/1202 are three category-error classes caught in
 * consecutive rulings** — Bing has no write-a-review surface at all, G2 reviews
 * B2B software so a local business cannot hold a profile, and Amazon, Zocdoc,
 * OpenTable, Angi and Thumbtack are *closed* destinations where only somebody
 * who transacted **through the platform** may review, so a cascade card is a
 * dead end for most customers. **Read the platform before the enum case.**
 *
 * BBB IS OPEN, WHICH IS WHY IT IS THE CHEAP ONE (1201). Anyone who used the
 * business may review; there is no transaction-through-BBB requirement and no
 * accreditation requirement (1165, both confirmed live). Three of its rules are
 * worth knowing and none of them costs us anything:
 *
 *   - ⚠️ **Customer reviews are not used in the BBB Rating.** BBB says so in its
 *     own submission terms. An owner will assume inviting reviews lifts their
 *     A+ and it does not, so **nothing owner-facing may imply it does** — that
 *     is `29` §2's no-guaranteed-rankings rule pointed at a grade rather than at
 *     a search position.
 *   - ⚠️ **BBB does not accept anonymous reviews** — the reviewer must give
 *     contact details and certify no affiliation with the business. Alone among
 *     these five it asks the customer to identify themselves, which is a real
 *     drop-off we do not control and must not describe as a completed review
 *     (decision 113).
 *   - **Incentivised reviews are refused outright, with no disclosure
 *     exception** — stricter than the FTC's own rule. Already inside `29` §2's
 *     "no incentivized reviews, ever", so it constrains nothing here.
 *
 * WHAT TRUSTPILOT COSTS US, STATED PLAINLY. Trustpilot's threshold is not a
 * setting. A tenant who does not want to invite every customer **disables
 * Trustpilot; they cannot gate it** (`17` FPR-04b: "the destination is disabled,
 * not gated"). This will read as a missing feature to anybody who does not know
 * why. It is their condition of use, and a profile flagged for cherry-picking is
 * not recoverable by editing a row back.
 */
enum ReviewDestination: string
{
    case Google = 'google';
    case Facebook = 'facebook';
    case Trustpilot = 'trustpilot';
    case Yelp = 'yelp';
    case Bbb = 'bbb';

    /**
     * The threshold this platform imposes on us, if it imposes one.
     *
     * NULL IS NOT ZERO AND IT IS NOT AN ABSENCE OF OPINION. Trustpilot's 0 is
     * Trustpilot's condition of use: invite everybody or invite nobody, and
     * cherry-picking is a violation they enforce publicly. Google's and
     * Facebook's nulls mean *we* choose — and where our choice lives is
     * `DestinationSettings::defaultThresholdFor()`, reading the registry.
     *
     * The distinction is what stops a later reader citing Facebook's number as
     * a platform rule. No source document states a Facebook selectivity rule at
     * all (decision 310).
     *
     * ⚠️ YELP'S IS NULL, AND THAT IS NOT THE SAME AS YELP HAVING NO OPINION.
     * Trustpilot's 0 says *invite everybody or nobody*; a forced value is how a
     * platform's rule about **which** customers to invite gets enforced. Yelp's
     * rule is not about which customers — it is that we should not invite any of
     * them, and the owner has overruled that at 1160. There is therefore no
     * compliant number to force: 0 would be a lie (it reads as "Yelp requires
     * you invite everyone") and any other value would read as Yelp's rule too.
     * So the threshold is ours to choose, and defaultThreshold() states the
     * choice — which is the honest shape, the same way `ConsentType::
     * TenantAttested` keeps a claim exactly as strong as it is (552).
     */
    public function forcedThreshold(): ?int
    {
        return match ($this) {
            self::Trustpilot => 0,
            // BBB imposes no selectivity rule. Their only businesses-facing
            // article on the subject is titled *"Encourage — don't prohibit —
            // customer complaints and reviews"*, and what it prohibits is
            // non-disparagement clauses, not asking. Read live 2026-08-07.
            self::Google, self::Facebook, self::Yelp, self::Bbb => null,
        };
    }

    /**
     * A starting threshold this platform's own risk dictates, if it dictates one.
     *
     * ⚠️ **NULL MEANS "USE THE PLATFORM DEFAULT", WHICH IS A REGISTRY KEY AND
     * NOT A NUMBER HERE.** This method used to return an `int` for every case
     * and carried Google's and Facebook's 5 — decision 110's figure. The owner
     * moved that figure to 4 at 1142 and made it the tenant's to set at 1143, so
     * it is `reviews.default_invite_threshold`, read through
     * `DestinationSettings::defaultThresholdFor()`; 1142's own words are *"it is
     * a settings row, not a deploy"*. Nulling those three cases is what keeps one
     * source of truth, rather than an enum arm and a registry row that can
     * disagree with whichever the caller happens to read.
     *
     * ⚠️ **BBB IS NULL AND NOT 5, AND 1405 IS THE DECISION THAT SAW THIS
     * COMING.** It landed at 5 on `feat/bbb-destination` under 1404's "match
     * the other open platforms" rule — which was right on that branch, because
     * the other open platforms were 5 there. That branch's own 1405 wrote the
     * warning down before either merged: *"a textual merge of those two
     * produces a file that compiles and is wrong"*, because a 5 sitting beside
     * four destinations at the registry default reads as a deliberate
     * per-platform rule and is not one. It is re-derived rather than carried:
     * BBB has no published penalty for asking and no term forbidding selective
     * invitation, so it has neither Yelp's reason nor Trustpilot's, and 1404's
     * rule pointed at the *current* set of open platforms puts it exactly where
     * Google and Facebook are — on the registry.
     *
     * YELP'S 5 STAYS HERE, AND THE REASON IT IS DIFFERENT IN KIND IS THE WHOLE
     * DISTINCTION. It is the most conservative reading of a ruling that did not
     * name a number: the owner ruled Yelp in and set no threshold for it, and
     * every customer sent there is exposure against 1145's penalty. 5 sends the
     * fewest people to the platform with the highest cost of being wrong, which
     * is `CLAUDE.md` §Operating instructions' first tiebreaker, *"less support
     * surface"*. That is a
     * fact about Yelp, so it belongs beside `forcedThreshold()` — an operator
     * moving the platform default in Ops must not quietly move Yelp with it.
     *
     * ⚠️ **It is a default, not a cap** — a tenant may set 1–5 (1186), and 1
     * means *invite everybody*. Nothing here refuses that; this number is only
     * where they start.
     */
    public function defaultThreshold(): ?int
    {
        return match ($this) {
            self::Yelp => 5,
            self::Trustpilot => 0,
            self::Google, self::Facebook, self::Bbb => null,
        };
    }

    /**
     * Whether provisioning creates a disabled row for this destination.
     *
     * ⚠️ **YELP IS THE ONLY FALSE, AND IT IS `29` §12.1'S REPLACED BUILD-FAILING
     * RULE IN ONE METHOD (1161).** The rule is *"Yelp is enabled only through
     * the confirmed-listing path, and never by seed, default, or provisioning"*.
     * `DestinationSettings::seedDefaults()` iterates every case, so without this
     * the owner's ruling at 1160 would have handed every location of every
     * tenant a Yelp row on the day it merged — including tenants who will never
     * enable it and have never been told what 1145 costs.
     *
     * ⚠️ **This is a predicate about seeding, NOT about whether Yelp may be
     * used.** It is deliberately not called `isEnabled()` or `isAvailable()`:
     * the owner ruled Yelp in, `enable()` accepts it with a confirmed link, and
     * a name implying otherwise would invite somebody to "fix" the ruling back
     * out by flipping one boolean. The prohibition this replaces is gone; what
     * remains is a rule about how the row is born.
     */
    public function isSeededAtProvisioning(): bool
    {
        return $this !== self::Yelp;
    }

    /**     * Whether this destination's link is computed rather than pasted.
     *
     * Google's comes from `location.place_id` at read time, so it cannot drift
     * when a place is re-resolved — and a listing merge changes the place id
     * (see PlaceConfirmation). A stored copy would be a second source of truth
     * with an expiry date on it (decision 308).
     *
     * ⚠️ YELP'S IS PASTED AND CONFIRMED RATHER THAN DERIVED, DELIBERATELY (1162).
     * Yelp publishes no documented write-a-review URL shape we could safely
     * construct, and decision 313 records what happens when a platform quietly
     * retires one: the legacy form keeps working until it does not, and the
     * first report comes from a customer. The owner's own ruling names this
     * mechanism — *"they can manually update the review link"* — and it is
     * `PlaceConfirmation::confirm()`'s pattern (220, 312) in a second place.
     */
    public function linkIsDerived(): bool
    {
        return $this === self::Google;
    }

    /**
     * Hosts a tenant-supplied link for this destination may point at.
     *
     * The SSRF host-allowlist pattern row 2 slice C established in
     * GoogleLinkHosts, for the same reason in a different direction: without it
     * a tenant can point a button labelled "Trustpilot" at any URL, on a page we
     * serve to their customer.
     *
     * An entry beginning with `.` is a dot-anchored suffix — `.trustpilot.com`
     * matches `uk.trustpilot.com` and `www.trustpilot.com` and does not match
     * `nottrustpilot.com` or `trustpilot.com.evil.test`. Everything else is an
     * exact host match. Never a substring: that is the difference between an
     * allowlist and a hope.
     *
     * GOOGLE'S LIST IS EMPTY AND THAT IS THE CORRECT ANSWER, not an unfinished
     * one. Its link is derived, so no pasted host is acceptable — an empty
     * allowlist rejects every one of them.
     *
     * @return list<string>
     */
    public function allowedLinkHosts(): array
    {
        return match ($this) {
            self::Google => [],
            self::Facebook => [
                'facebook.com',
                'www.facebook.com',
                'm.facebook.com',
                'web.facebook.com',
                'fb.com',
                'www.fb.com',
            ],
            self::Trustpilot => [
                'trustpilot.com',
                '.trustpilot.com',
            ],
            // ⚠️ US HOSTS ONLY, AND THE OMISSION IS DELIBERATE. The dot-anchored
            // suffix covers `www.`, `m.` and Yelp's language subdomains
            // (`fr.yelp.com`), which is every host a US listing is served on.
            // Yelp's country domains — `yelp.ca`, `yelp.co.uk`, `yelp.ie` — are
            // separate registrable domains and are NOT covered: a suffix cannot
            // reach them, and enumerating a ccTLD list nobody has read the terms
            // for would be guessing at which countries this product operates in.
            // `App\Support\Identifier`'s PHONE_REGION is US and stated rather
            // than inferred for the same reason (424–427); **the first non-US
            // tenant reopens both together.**
            self::Yelp => [
                'yelp.com',
                '.yelp.com',
            ],
            // BBB consolidated its regional bureaus onto one domain; profiles
            // are served from `www.bbb.org` and the mobile host `m.bbb.org`,
            // both covered by the dot anchor. ⚠️ Canadian bureaus sit on this
            // same domain under a country path, so no second entry is needed —
            // which is the opposite of Yelp, whose country sites are separate
            // registrable domains.
            self::Bbb => [
                'bbb.org',
                '.bbb.org',
            ],
        };
    }

    /**
     * What a customer sees on the button. Outcome language (`29` §2 rule 47):
     * the platform's own name, never our internal key.
     */
    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Facebook => 'Facebook',
            self::Trustpilot => 'Trustpilot',
            self::Yelp => 'Yelp',
            // The initials, not "Better Business Bureau". `29` §2 rule 47 is
            // outcome language — the name the customer would recognise on a
            // button — and BBB is how the organisation brands itself.
            self::Bbb => 'BBB',
        };
    }

    /**
     * The feed sentence when a customer taps through to this platform.
     *
     * ⚠️ EVERY VERB HERE IS LOAD-BEARING. `17` FPR-04b fixes the reporting
     * language as "invited" and "opened Google" — never "left a review" — and
     * `29` §12.1 makes "no click is ever surfaced as a completed review" a
     * build-failing test. We observe a tap on a link and nothing after it, so
     * *opened* is the strongest true verb and *to post a review* names where
     * they went rather than what they did there. A past-tense completion in any
     * of these strings is a claim to a paying owner that no platform gives us
     * the data to support, and a test pins that.
     *
     * FACEBOOK SAYS "RECOMMENDATION" BECAUSE FACEBOOK HAS NO STAR REVIEWS.
     * `24` §2.3.1 records the distinction; it costs one word here and stops the
     * owner-facing vocabulary claiming a rating scale that platform retired.
     */
    public function openedTitle(): string
    {
        return match ($this) {
            self::Google => 'A customer opened Google to post a review',
            self::Facebook => 'A customer opened Facebook to post a recommendation',
            self::Trustpilot => 'A customer opened Trustpilot to post a review',
            self::Yelp => 'A customer opened Yelp to post a review',
            self::Bbb => 'A customer opened BBB to post a review',
        };
    }
}
