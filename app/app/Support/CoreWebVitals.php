<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\WebVital;
use Carbon\CarbonImmutable;

/**
 * Google's published Core Web Vitals boundaries, with the document they were
 * read out of and the day they were read.
 *
 * ⛔ **DECISION 5724, AND THE REASON IT TOOK A RULING RATHER THAN A LOOKUP.**
 * `28` §4.3 tells the Speed Report to say *"the site was already fast"* when
 * nothing improved measurably, and decision 5610 refused to write that
 * sentence: it is a claim about an **absolute** speed, and the boundaries that
 * would make it true appear nowhere in `docs/`. Said to a tenant whose pages
 * take eight seconds it is a false statement, which is worse than the
 * fabricated win the rule exists to prevent. The owner ruled that Google's own
 * figures are adopted **with the citation and the fetch date stored beside
 * them**, and this class is that ruling.
 *
 * ---------------------------------------------------------------------------
 * ⛔ WHAT THE PAGES SAY, VERBATIM, AND WHEN THEY WERE READ
 * ---------------------------------------------------------------------------
 * All four fetched **2026-08-20**. The `documentUpdatedOn` beside each is the
 * date the page shows for **itself**, which is the thing that moves when Google
 * changes a number — INP replaced FID as a Core Web Vital in March 2024, so
 * these figures have already changed once within this project's lifetime.
 *
 *  - **LCP** — `web.dev/articles/lcp` (last updated 2025-09-04):
 *    *"sites should strive to have Largest Contentful Paint of 2.5 seconds or
 *    less"*, and *"poor values are greater than 4.0 seconds"*.
 *  - **INP** — `web.dev/articles/inp` (last updated 2025-09-02):
 *    *"An INP below or at 200 milliseconds means a page has good
 *    responsiveness."*, and *"An INP above 500 milliseconds means a page has
 *    poor responsiveness."*
 *  - **CLS** — `web.dev/articles/cls` (last updated 2023-04-12):
 *    *"sites should strive to have a CLS score of 0.1 or less"*, and *"Poor
 *    values are greater than 0.25"*.
 *  - **TTFB** — `web.dev/articles/ttfb` (last updated 2025-11-18):
 *    *"Good TTFB values are 0.8 seconds or less, and poor values are greater
 *    than 1.8 seconds."*
 *
 * The percentile is the same page's: *"a good threshold to measure is the 75th
 * percentile of page loads, segmented across mobile and desktop devices"*
 * (`web.dev/articles/vitals`, last updated 2024-10-31) — which is what
 * [[\App\Services\Warehouse\SiteVitals]] already computes, and is worth
 * recording because a p95 measured against a p75 boundary would be a different
 * claim wearing the same words.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ TWO GOOGLE PROPERTIES WORD THE SAME BOUNDARY DIFFERENTLY, AND web.dev WINS
 * ---------------------------------------------------------------------------
 * Search Central's *Understanding Core Web Vitals and Google search results*
 * (`developers.google.com/search/docs/appearance/core-web-vitals`, last updated
 * 2025-12-10, fetched the same day) says *"strive to have an INP of **less
 * than** 200 milliseconds"* and *"a CLS score of **less than** 0.1"* — strictly
 * `<` where web.dev says `≤`. It is the **more recently updated** of the two
 * and it is still not the one followed here, for three reasons: it gives no
 * "poor" boundary at all, it names no percentile, and web.dev is where the
 * metrics are defined rather than where their ranking use is summarised. The
 * difference is one millisecond and one thousandth wide and can only ever move
 * a page **out** of `Good`, so following web.dev is also the reading that
 * claims more — which is precisely why it is written down here rather than
 * quietly chosen.
 *
 * ---------------------------------------------------------------------------
 * ⛔ TTFB IS NOT A CORE WEB VITAL AND IS FLAGGED AS SUCH RATHER THAN DROPPED
 * ---------------------------------------------------------------------------
 * The pixel collects it and the mart stores it, so it needs an answer either
 * way. `web.dev/articles/vitals` lists TTFB among the metrics that are
 * *"useful in diagnosing issues with LCP"* rather than among the Core Web
 * Vitals, and its own page says *"Because TTFB isn't a Core Web Vitals metric,
 * it's not absolutely necessary that sites meet the 'good' TTFB threshold"* and
 * offers its figures *"as a rough guide"*. So its boundaries are recorded —
 * they are published, and inventing one to make the set look complete is the
 * failure 5724 exists to prevent — and [[isClaimable()]] refuses to let them
 * carry a sentence to an owner. A tenant told their site is fast on the
 * strength of a metric Google says is a diagnostic would have been told
 * something Google does not say.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ WHY THIS IS A CLASS AND NOT A REGISTRY ROW
 * ---------------------------------------------------------------------------
 * The tiebreaker is `CLAUDE.md`'s *less support surface*, and the shape is
 * [[\App\Enums\WebVital]]'s own: an Ops-editable boundary is a way to move the
 * line until a tenant's site qualifies as fast, which is the same failure as an
 * Ops-editable sample floor (decision 5723's *"a floor that can be lowered
 * under pressure is a way to manufacture a measurement"*). These figures change
 * when **Google** changes them, and that is a document to re-read rather than a
 * number to choose — so the mechanism that must exist is a way to notice the
 * document has moved, not a way to type over it. That mechanism is
 * [[isStale()]].
 */
final class CoreWebVitals
{
    /**
     * The day every citation in this class was read.
     */
    public const string FETCHED_ON = '2026-08-20';

    /**
     * How long a reading of these pages is trusted for.
     *
     * ⛔ **A YEAR IS OURS AND IT IS A REFUSAL RATHER THAN A REMINDER.** Google
     * publishes no schedule for changing a threshold; it changed the metric set
     * itself in March 2024. Nothing in this application can detect that from
     * the outside, so the honest fallback is to stop making the absolute claim
     * once the reading is old enough that nobody can vouch for it — a stale
     * citation withholds [[\App\Enums\SpeedVerdict::AlreadyFast]] and the report
     * falls back to saying only what changed, which is exactly where decision
     * 5610 left it.
     *
     * ⚠️ **AND IT IS DELIBERATELY LOUD AS WELL AS FAIL-CLOSED.** A silent
     * degradation is a product that quietly stops telling anybody anything, so
     * `CoreWebVitalThresholdsTest`'s *"the Core Web Vitals citation has not gone
     * stale"* fails the build on the same day and for the same reason, naming
     * the URLs to re-read. The build going red **is** the notification that the
     * claim has switched off; the two are one condition, not two tripwires.
     *
     * ⚠️ **THIS SAID `WarehouseTest` UNTIL 2026-08-25 AND THE CLAIM WAS NEVER
     * THERE** (9780). It is a fat-fingered file name rather than a missing test
     * — the assertion has always existed, verbatim, one directory over — and
     * `CitationTest` passed over it for a week because `WarehouseTest` **is** a
     * real file. That is the defect this file's fourth lint was written for, and
     * it was its own first occupant.
     */
    public const int REVERIFY_AFTER_DAYS = 365;

    /**
     * The published boundaries for one metric, or `null` where there are none.
     *
     * ⛔ **`null` IS A REAL ANSWER AND MUST STAY ONE.** A metric added to
     * [[WebVital]] with no published boundary arrives here as `null`, makes no
     * claim, and reddens nothing — which is the fail-closed direction. Writing
     * this as an exhaustive `match` over the enum would make the next metric a
     * runtime error, and the fix under time pressure is to invent a figure for
     * it.
     */
    public static function for(WebVital $metric): ?VitalThreshold
    {
        return self::all()[$metric->value] ?? null;
    }

    /**
     * May this metric carry an absolute-speed sentence to an owner?
     *
     * A published boundary is necessary and is not sufficient: the metric must
     * be one Google calls a Core Web Vital, and the citation must still be
     * inside its re-verification window.
     */
    public static function isClaimable(WebVital $metric, ?CarbonImmutable $asOf = null): bool
    {
        $threshold = self::for($metric);

        return $threshold instanceof VitalThreshold
            && $threshold->isCoreWebVital
            && ! self::isStale($asOf);
    }

    /**
     * Has the reading of these pages gone stale?
     *
     * ⚠️ **THE CLOCK IS AN ARGUMENT SO THAT BOTH SIDES OF THE BOUNDARY CAN BE
     * DRIVEN** without travelling global time, which a suite that also asserts
     * warehouse day ranges should not have to do.
     */
    public static function isStale(?CarbonImmutable $asOf = null): bool
    {
        return ($asOf ?? CarbonImmutable::now())->greaterThan(self::staleOn());
    }

    /**
     * The day the citations stop being trusted.
     */
    public static function staleOn(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::FETCHED_ON)->addDays(self::REVERIFY_AFTER_DAYS);
    }

    /**
     * @return array<string, VitalThreshold>
     */
    public static function all(): array
    {
        return [
            // 2.5 s and 4.0 s, in the mart's milliseconds.
            WebVital::Lcp->value => new VitalThreshold(
                metric: WebVital::Lcp,
                goodAtOrBelow: 2_500,
                poorAbove: 4_000,
                isCoreWebVital: true,
                sourceUrl: 'https://web.dev/articles/lcp',
                sourceQuote: 'sites should strive to have Largest Contentful Paint of 2.5 seconds or less'
                    .' … poor values are greater than 4.0 seconds',
                documentUpdatedOn: '2025-09-04',
                fetchedOn: self::FETCHED_ON,
            ),

            WebVital::Inp->value => new VitalThreshold(
                metric: WebVital::Inp,
                goodAtOrBelow: 200,
                poorAbove: 500,
                isCoreWebVital: true,
                sourceUrl: 'https://web.dev/articles/inp',
                sourceQuote: 'An INP below or at 200 milliseconds means a page has good responsiveness.'
                    .' … An INP above 500 milliseconds means a page has poor responsiveness.',
                documentUpdatedOn: '2025-09-02',
                fetchedOn: self::FETCHED_ON,
            ),

            // 0.1 and 0.25, in the mart's thousandths — CLS is the one metric
            // whose stored unit is not the unit the page prints, and
            // `WebVital::unitScale()` is where that 1,000 comes from.
            WebVital::Cls->value => new VitalThreshold(
                metric: WebVital::Cls,
                goodAtOrBelow: 100,
                poorAbove: 250,
                isCoreWebVital: true,
                sourceUrl: 'https://web.dev/articles/cls',
                sourceQuote: 'sites should strive to have a CLS score of 0.1 or less'
                    .' … Poor values are greater than 0.25',
                documentUpdatedOn: '2023-04-12',
                fetchedOn: self::FETCHED_ON,
            ),

            // ⛔ PUBLISHED, RECORDED, AND NOT CLAIMABLE — see this class's
            // docblock. `isCoreWebVital: false` is what stops it.
            WebVital::Ttfb->value => new VitalThreshold(
                metric: WebVital::Ttfb,
                goodAtOrBelow: 800,
                poorAbove: 1_800,
                isCoreWebVital: false,
                sourceUrl: 'https://web.dev/articles/ttfb',
                sourceQuote: 'Good TTFB values are 0.8 seconds or less, and poor values are greater'
                    .' than 1.8 seconds.',
                documentUpdatedOn: '2025-11-18',
                fetchedOn: self::FETCHED_ON,
            ),
        ];
    }
}
