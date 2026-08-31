<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\SiteChangeVerdict;
use App\Services\Warehouse\SiteVitals;

/**
 * Two windows, and what may honestly be concluded from them — `29` §2 rule 32's
 * *"measure 14–30 days, auto-rollback on regression"*, decided.
 *
 * ⛔ **`InsufficientData` IS A VERDICT OF ITS OWN AND NEVER COLLAPSES INTO
 * `Neutral`** (229, 5523, 5728). `Neutral` says *we measured this for a month
 * and it did not move*; `InsufficientData` says *not enough traffic reached it
 * for the question to have an answer*. **4861 makes the second the expected
 * answer for most tenants for some time**, so a system that reported them all as
 * "neutral — kept" would manufacture a month of evidence it does not have, on
 * every tenant, from the first change.
 *
 * ## Two signals, and one of them may not say a good word
 *
 * ⚠️ **THE PAGE SIGNAL IS THE PAGE'S OWN** — pageviews on the changed path, from
 * the pixel mart. It answers the question rule 32 asks, and it may reach any of
 * the four verdicts.
 *
 * ⛔ **THE SEARCH SIGNAL IS THE WHOLE PROPERTY'S, SO IT MAY REACH `Regressed`
 * AND NEVER `Improved`.** `GoogleSearchConsoleClient::dailyMetrics()` is
 * `dimensions: ['date']` for a site, and 1083/5480 keep that client read-only
 * and its scope where it is — so what it can see is the site, not the page.
 * A site-wide **fall** after we published is worth acting on, because taking our
 * own change back off is the conservative direction on somebody else's website.
 * A site-wide **rise** credited to one page we wrote is `28` §4.3's fabricated
 * win exactly, and 5610/5765 refused the same claim one metric along. So the
 * asymmetry is deliberate and it is asserted rather than described.
 *
 * ## The arithmetic has no float in it
 *
 * The two windows are **different lengths** — fourteen days before, seventeen
 * days after (days 14–30 inclusive) — so totals are not comparable and the
 * comparison is of per-day rates, cross-multiplied:
 *
 * ```
 * bp = (after × baselineDays × 10000) ÷ (before × measuredDays) − 10000
 * ```
 *
 * Positive is more traffic. Integer division throughout, on `SiteVitals`'
 * standing reason: there is no `serialize_precision`, no rounding mode and no
 * float non-associativity anywhere in the path, so the same rows give the same
 * verdict on any machine.
 *
 * ⚠️ **CONVERSIONS ARE NOT A SIGNAL**, though they are recorded. A single local
 * page's conversions over a fortnight are a handful, which is below any floor
 * worth having — so a trigger on them would be an arm that never fires on real
 * data and fires on noise when it does (256, and 3789's one-opt-out lesson).
 */
final readonly class ChangeComparison
{
    /**
     * How large a move counts as a move, in basis points.
     *
     * ⚠️ **BOUND RATHER THAN TYPED AGAIN** — 5617's instruction to slice L,
     * which applies word for word here: *"do not type 10% again rather than
     * binding `SiteVitals::MATERIAL_CHANGE_BASIS_POINTS`"*. `28` §4.3's *">10%"*
     * is the figure, and two copies of it is 5146's four-marts-disagreeing
     * failure waiting to happen.
     */
    public const int MATERIAL_BASIS_POINTS = SiteVitals::MATERIAL_CHANGE_BASIS_POINTS;

    /**
     * How much traffic a baseline window needs before a verdict may be stated.
     *
     * ⛔ **IT IS `SiteVitals::MINIMUM_SAMPLES` AND IT IS NAMED SEPARATELY ON
     * PURPOSE.** 5723 is the owner's ruling that the vitals floor should be 50
     * and 5769 records why nothing implemented it: **that constant already
     * serves two floors** — the vitals sample count and the JS-error
     * denominator — and only the first was ruled on. This is a **third** floor,
     * on a third quantity, so it binds the same figure through a name of its own
     * rather than deepening the knot: whoever splits that constant changes one
     * line here and the split is visible on the diff.
     *
     * ⛔ **IT IS WHAT STOPS ONE ORDINARY WOBBLE QUARANTINING A FIX** (3789's
     * lesson, transplanted). A page with nine visits a fortnight loses "half its
     * traffic" whenever one person does not come back, and a rollback plus a
     * quarantine on that arithmetic is the platform breaking a customer's site
     * for them.
     */
    public const int MINIMUM_BASELINE_SAMPLES = SiteVitals::MINIMUM_SAMPLES;

    private function __construct(
        public SiteChangeVerdict $verdict,
        public SiteChangeVerdict $pageVerdict,
        public SiteChangeVerdict $searchVerdict,
        public ?int $pageBasisPoints,
        public ?int $searchBasisPoints,
    ) {}

    public static function between(ChangeMetrics $baseline, ChangeMetrics $measured): self
    {
        [$pageVerdict, $pageBp] = self::signal(
            $baseline->pageviews,
            $baseline->days,
            $measured->pageviews,
            $measured->days,
            mayImprove: true,
        );

        [$searchVerdict, $searchBp] = self::signal(
            $baseline->searchClicks,
            $baseline->days,
            $measured->searchClicks,
            $measured->days,
            // ⛔ See the class docblock: a site-wide rise is not this page's win.
            mayImprove: false,
        );

        return new self(
            self::combine($pageVerdict, $searchVerdict),
            $pageVerdict,
            $searchVerdict,
            $pageBp,
            $searchBp,
        );
    }

    /**
     * ⛔ **ANY REGRESSION IS THE ANSWER, AND THAT IS THE CONSERVATIVE DIRECTION
     * ON SOMEBODY ELSE'S WEBSITE.** Rule 32's remedy is to put the page back, and
     * the cost of doing that to a change that was in fact fine is one page of
     * ours coming off a site the owner never asked us to write to. The cost of
     * the other error is a change we made, measured as harmful, left on a
     * customer's site because a second signal disagreed.
     *
     * ⛔ **AND `InsufficientData` ONLY WINS WHEN IT IS ALL THERE IS.** One
     * measurable signal is a measurement; two signals of which one is blind is
     * not a reason to say nothing.
     */
    private static function combine(SiteChangeVerdict $page, SiteChangeVerdict $search): SiteChangeVerdict
    {
        $signals = [$page, $search];

        if (in_array(SiteChangeVerdict::Regressed, $signals, true)) {
            return SiteChangeVerdict::Regressed;
        }

        $measured = array_values(array_filter(
            $signals,
            static fn (SiteChangeVerdict $verdict): bool => $verdict !== SiteChangeVerdict::InsufficientData,
        ));

        if ($measured === []) {
            return SiteChangeVerdict::InsufficientData;
        }

        return in_array(SiteChangeVerdict::Improved, $measured, true)
            ? SiteChangeVerdict::Improved
            : SiteChangeVerdict::Neutral;
    }

    /**
     * One signal's reading, as a verdict and the move that produced it.
     *
     * @return array{SiteChangeVerdict, ?int}
     */
    private static function signal(
        ?int $before,
        int $beforeDays,
        ?int $after,
        int $afterDays,
        bool $mayImprove,
    ): array {
        // Null is "we could not see", which is not a number and never a zero.
        if ($before === null || $after === null || $beforeDays < 1 || $afterDays < 1) {
            return [SiteChangeVerdict::InsufficientData, null];
        }

        // ⛔ THE FLOOR IS ON THE BASELINE AND NOT ON THE MEASURED WINDOW. A
        // measured window of zero against a healthy baseline is the regression
        // this whole slice exists to catch; flooring it too would make the worst
        // outcome unreportable.
        if ($before < self::MINIMUM_BASELINE_SAMPLES) {
            return [SiteChangeVerdict::InsufficientData, null];
        }

        $basisPoints = intdiv($after * $beforeDays * 10_000, $before * $afterDays) - 10_000;

        if ($basisPoints <= -self::MATERIAL_BASIS_POINTS) {
            return [SiteChangeVerdict::Regressed, $basisPoints];
        }

        if ($basisPoints >= self::MATERIAL_BASIS_POINTS && $mayImprove) {
            return [SiteChangeVerdict::Improved, $basisPoints];
        }

        return [SiteChangeVerdict::Neutral, $basisPoints];
    }
}
