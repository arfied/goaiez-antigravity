<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a speed comparison is allowed to say to an owner.
 *
 * `28` §4.3's reporting rule is the copy authority for every string below:
 * *"the Speed Report says only what changed for real users. If nothing improved
 * measurably, the report says the site was already fast — never a fabricated
 * win."*
 *
 * ⛔ **THE LITERAL SENTENCE IN THAT RULE WAS REFUSED AND IS NOW EARNED — BOTH
 * READINGS KEPT AND DATED** (decisions 5610, then 5724 on 2026-08-20). 5610
 * refused *"your site was already fast"* because it is a claim about an
 * **absolute** speed and this application had no verified threshold to make it
 * against: the Core Web Vitals "good" boundaries are Google's published
 * figures, they appeared nowhere in `docs/`, and CLAUDE.md's rule is that a
 * vendor threshold is verified against the raw artefact or not written at all.
 * Said to a tenant whose pages take eight seconds it is a **false statement**,
 * which is worse than the fabricated win the rule exists to prevent.
 *
 * ⚠️ **THE REFUSAL IS KEPT IN WRITING BECAUSE ITS CONDITION IS WHAT MAKES THE
 * CLAIM SAFE, AND THE CONDITION CAN COME BACK.** The owner ruled that Google's
 * published thresholds are adopted *with the citation and the fetch date stored
 * beside them*, so [[self::AlreadyFast]] exists — and it is spoken **only** when
 * [[\App\Support\CoreWebVitals]] can still vouch for the boundary it was
 * measured against. A stale citation, a metric with no published boundary, or a
 * metric Google does not call a Core Web Vital all put the report straight back
 * to [[self::Unchanged]], which is 5610's answer and remains the honest one.
 *
 * ⚠️ **NO FIGURE APPEARS IN ANY OF THESE STRINGS.** `28` §7's banned-jargon list
 * and `ClaimLawTest`'s claim law between them say why: a percentage typed into a
 * sentence is a claim nobody can move, and the person reading this one is the
 * customer.
 */
enum SpeedVerdict: string
{
    case Improved = 'improved';
    case Worsened = 'worsened';

    /**
     * Nothing moved measurably, and this application will not say how fast the
     * site is.
     *
     * ⚠️ **THIS IS THE ARM A STALE CITATION FALLS BACK INTO**, and it is a
     * complete, honest answer rather than a degraded one — it is exactly what
     * decision 5610 shipped.
     */
    case Unchanged = 'unchanged';

    /**
     * Nothing moved measurably **and** the site is already inside Google's
     * published "good" boundary for the metric.
     *
     * ⛔ **NOT A WIN, AND [[isWin()]] SAYS SO.** `28` §4.3 forbids reporting a
     * win that did not happen, and nothing improved here: this is a statement
     * about where the site already stood, made because the alternative — a bare
     * "nothing changed" to a site that is genuinely fast — reads as a failure to
     * a person who is doing well.
     *
     * ⛔ **IT MAY ONLY BE REACHED THROUGH
     * [[\App\Support\CoreWebVitals::isClaimable()]]**, which requires a
     * published boundary, a metric Google calls a Core Web Vital, and a citation
     * inside its re-verification window. TTFB fails the second of those and is
     * not a mistake in the mart — see that class's docblock.
     */
    case AlreadyFast = 'already_fast';

    /**
     * One side of the comparison is too thin to state — [[VitalSampleState]]'s
     * own rule, carried through the comparison rather than resolved into a
     * "no change".
     */
    case InsufficientData = 'insufficient_data';

    /**
     * Nothing has ever been measured for this business.
     */
    case NoMeasurements = 'no_measurements';

    /**
     * The sentence an owner is shown, in the outcome language `22` requires.
     */
    public function sentence(): string
    {
        return match ($this) {
            self::Improved => 'Your pages are loading faster for real visitors than they were before.',
            self::Worsened => 'Your pages are loading slower for real visitors than they were before.',
            self::Unchanged => 'Nothing we changed made a measurable difference to how fast your pages load for real visitors.',
            self::AlreadyFast => 'Nothing we changed made a measurable difference — your pages were already loading fast for real visitors.',
            self::InsufficientData => 'Not enough visits yet to say whether anything changed.',
            self::NoMeasurements => 'We are not receiving speed measurements from your website yet.',
        };
    }

    /**
     * Does this verdict claim an improvement?
     *
     * ⚠️ **HERE SO THAT NOBODY HAS TO ASK IT WITH A COMPARISON.** The one thing
     * `28` §4.3 forbids outright is reporting a win that did not happen, and a
     * caller writing `$verdict !== SpeedVerdict::Worsened` has written exactly
     * that: it treats "not enough data" and "nothing changed" as wins.
     *
     * ⛔ **AND [[self::AlreadyFast]] IS NOT A WIN EITHER, WHICH IS THE ONE
     * ANSWER HERE SOMEBODY WILL WANT TO CHANGE.** It is the friendliest sentence
     * this enum can say, so it reads like a success — but nothing improved, and
     * a report that counted it as an improvement would be claiming credit for a
     * site that was already fast before anybody touched it. That is `28` §4.3's
     * fabricated win with a compliment attached.
     */
    public function isWin(): bool
    {
        return $this === self::Improved;
    }
}
