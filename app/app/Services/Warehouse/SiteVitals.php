<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\DeviceClass;
use App\Enums\SpeedVerdict;
use App\Enums\VitalRating;
use App\Enums\VitalSampleState;
use App\Enums\WebVital;
use App\Models\L2FactPageDaily;
use App\Models\L2FactVitalDaily;
use App\Support\CoreWebVitals;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The only reader of the speed marts — `28` §4.3's *"baseline and the judge"*.
 *
 * ⛔ **IT DECIDES NOTHING.** §4.3's four auto-rollback triggers, the quarantine
 * and the per-tier fixes are `BUILD-PLAN.md` §2.11.3's slice L. This is the
 * aggregate that slice reads, and the boundary is deliberate: a decider built
 * beside its own inputs is a decider whose inputs are shaped to suit it.
 *
 * ---------------------------------------------------------------------------
 * ⛔ IT READS THE MART AND NEVER `l1_events`, AND THAT IS TESTABLE
 * ---------------------------------------------------------------------------
 * `BUILD-PLAN.md` §2.11.4's H bullet asks for exactly this and says how to
 * prove it: pin the assertion to planted mart rows with **no raw events behind
 * them**, so a reader that quietly recomputed from L1 fails. Recomputing would
 * not merely be slow — it would answer a *different question*, because the mart
 * is where the bot filter, the metric allowlist and the hostile-value refusal
 * live, and a second implementation of those is a second set of numbers.
 *
 * ---------------------------------------------------------------------------
 * THE PERCENTILE METHOD, PINNED
 * ---------------------------------------------------------------------------
 * **Nearest rank on the discrete distribution.** The p75 is the smallest bucket
 * `b` whose cumulative count reaches `ceil(0.75 × N)`, where `N` is the total
 * number of samples in the window. That is `percentile_disc(0.75)` over the
 * expanded multiset, and it is chosen over `percentile_cont` for three
 * reasons — none of them taste:
 *
 *  1. **It returns a value that was actually observed.** `percentile_cont`
 *     interpolates between two neighbours, which invents a measurement nobody
 *     recorded.
 *  2. **It is pure integer arithmetic.** `ceil(0.75 × N)` is `(3N + 3) ÷ 4` in
 *     integers; the walk is addition and comparison. There is no float in the
 *     path at all, so `serialize_precision`, `extra_float_digits` and
 *     floating-point non-associativity have nothing to act on — the four
 *     hazards `WarehouseSnapshot`'s docblock enumerates.
 *  3. **It has no tie to break.** A percentile that must choose between two
 *     equally-ranked values needs an ordering rule, and an ordering rule over
 *     text reads `LC_COLLATE`. The sorted multiset determines the value at a
 *     rank uniquely, so there is nothing to choose.
 *
 * ⚠️ **THE ANSWER IS AN UPPER BOUND ON THE TRUE p75, BY UP TO ONE BUCKET
 * WIDTH.** Buckets round *up* (see [[\App\Enums\WebVital]]), so this over-states
 * rather than under-states — `28` §4.3's *"never a fabricated win"* applied to
 * a rounding rule. Both sides of a comparison carry the same bias, so a
 * before-and-after difference is unaffected by it.
 */
final readonly class SiteVitals
{
    /**
     * How many measurements a window needs before a percentile may be stated.
     *
     * ⚠️ **BORROWED FROM `28` §4.3 RATHER THAN INVENTED** (decision 5605). That
     * section gives exactly one sample-size figure — *"conversion rate drops
     * >15% **with ≥100 sessions**"* — and gives none for the vitals triggers
     * beside it. Taking the document's own number for the same job is the
     * CLAUDE.md move; picking a rounder-looking one and citing §4.3 for it is
     * the thing CLAUDE.md's "verify against the raw artefact" rule exists to
     * stop.
     *
     * ⛔ **IT IS A CONSTANT AND NOT A REGISTRY ROW, AND THE OWNER MAY WANT TO
     * MOVE IT.** A floor is close to a product promise: it decides how long a
     * new tenant is told "not enough visits yet", and decision 4861 makes that
     * most tenants, for some time. It is a constant because an Ops-editable
     * floor is a support surface that changes what a past report meant, and
     * because CLAUDE.md's tiebreaker is *less support surface*. **Raised for a
     * ruling in `DECISIONS.md`, not guessed at silently.**
     */
    public const int MINIMUM_SAMPLES = 100;

    /**
     * How large a move counts as a move, in basis points.
     *
     * `28` §4.3: *"p75 LCP or INP worsens **>10%** over 7 days vs the 14-day
     * pre-change baseline"*. Below this, the honest report is that nothing
     * changed.
     *
     * ⚠️ **SLICE L SHOULD BIND THIS RATHER THAN TYPE 10% AGAIN.** Decision
     * 5146's lesson, one mart along: four marts once disagreed about what a
     * conversion is, and nothing failed, because two different numbers on one
     * screen read as a rounding question. ⚠️ **It is not the whole of §4.3's
     * trigger set** — that section makes CLS asymmetric (*"CLS worsens at
     * all"*) and pairs the conversion trigger with a session count. Those
     * asymmetries are the decider's and are deliberately not encoded here.
     */
    public const int MATERIAL_CHANGE_BASIS_POINTS = 1_000;

    /**
     * The p75 of one metric, for one device class or for all of them, over a
     * closed day range.
     *
     * ⚠️ **THE TENANT COMES FROM THE SESSION, NOT FROM AN ARGUMENT.** Every
     * query below goes through an Eloquent model carrying `BelongsToTenant`, so
     * the `business_id` predicate is the global scope's and row-level security
     * is underneath it. A method taking a `Business` would be a method somebody
     * could hand another tenant's row to.
     *
     * ⚠️ **`$deviceClass = null` MEANS "EVERY DEVICE CLASS POOLED", WHICH IS A
     * DIFFERENT QUESTION FROM ANY ONE OF THEM.** `28` §4.3 asks for p75 *by*
     * device class because a site can be fast on desktop and slow on phones;
     * the pooled figure is for a headline, never for a comparison.
     */
    public function p75(
        WebVital $metric,
        ?DeviceClass $deviceClass,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): VitalReading {
        /** @var list<object{bucket: int|string, samples: int|string}> $rows */
        $rows = L2FactVitalDaily::query()
            ->where('metric', $metric->value)
            ->when(
                $deviceClass instanceof DeviceClass,
                fn ($query) => $query->where('device_type', $deviceClass?->value),
            )
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->select(['bucket', DB::raw('sum(samples) AS samples')])
            ->get()
            ->all();

        $total = 0;

        foreach ($rows as $row) {
            $total += (int) $row->samples;
        }

        if ($total >= self::MINIMUM_SAMPLES) {
            return new VitalReading(
                $metric,
                $deviceClass,
                VitalSampleState::Measured,
                $total,
                self::nearestRank($rows, $total),
            );
        }

        return new VitalReading(
            $metric,
            $deviceClass,
            $this->hasEverMeasured() ? VitalSampleState::InsufficientData : VitalSampleState::NoMeasurements,
            $total,
        );
    }

    /**
     * Has anything ever been measured for this tenant, on any day, for any
     * metric?
     *
     * ⛔ **THIS IS WHAT KEEPS `BUILD-PLAN.md` §2.11.5 CONFLICT 6's THIRD STATE
     * FROM COLLAPSING.** "No measurements have ever arrived" and "measurements
     * arrive but not enough of this one" are different facts leading to
     * different actions — go and check the script, versus wait.
     *
     * ⚠️ **A PIXEL KEY CANNOT ANSWER THIS AND MUST NOT BE USED TO TRY.**
     * `TenantProvisioner` mints one for every tenant at registration, so its
     * existence says only that somebody signed up — 4961's shape exactly, a
     * column that looks like the answer. The honest separator is a *pixel
     * sighting*, which is slice B's `ActuationTiers` and does not exist yet;
     * until it does, this conflates "no pixel installed" with "nobody has
     * visited", and [[VitalSampleState::NoMeasurements]] says so.
     */
    public function hasEverMeasured(): bool
    {
        return L2FactVitalDaily::query()->exists();
    }

    /**
     * `28` §4.3's fourth trigger's input: errors per thousand pageviews over a
     * closed day range, for one page or for the whole site.
     *
     * ⚠️ **THE DENOMINATOR IS PAGEVIEWS AND THE FLOOR APPLIES TO IT.** An error
     * rate over nine pageviews doubles on one unlucky visitor.
     */
    public function jsErrorRate(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $pagePath = null,
    ): JsErrorRate {
        /** @var object{pageviews: int|string|null, errors: int|string|null} $totals */
        $totals = L2FactPageDaily::query()
            ->when($pagePath !== null, fn ($query) => $query->where('page_path', $pagePath))
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->select([
                DB::raw('coalesce(sum(pageviews), 0) AS pageviews'),
                DB::raw('coalesce(sum(js_errors), 0) AS errors'),
            ])
            ->first();

        $pageviews = (int) ($totals->pageviews ?? 0);
        $errors = (int) ($totals->errors ?? 0);

        if ($pageviews >= self::MINIMUM_SAMPLES) {
            // ⚠️ INTEGER ROUNDING, HALF AWAY FROM ZERO, WITHOUT A FLOAT.
            // `(int) round($errors * 1000 / $pageviews)` is the same answer
            // through a double, and this file has no other float in it.
            return new JsErrorRate(
                VitalSampleState::Measured,
                $pageviews,
                $errors,
                intdiv($errors * 2_000 + $pageviews, 2 * $pageviews),
            );
        }

        return new JsErrorRate(
            $pageviews === 0 && ! L2FactPageDaily::query()->exists()
                ? VitalSampleState::NoMeasurements
                : VitalSampleState::InsufficientData,
            $pageviews,
            $errors,
        );
    }

    /**
     * What may honestly be said about a change, comparing two windows.
     *
     * ⛔ **EVERY ARM THAT IS NOT A MEASURED IMPROVEMENT REFUSES TO CLAIM ONE**,
     * which is the whole of `28` §4.3's reporting rule. A missing baseline is
     * not "no change" and a thin sample is not "no change"; both are their own
     * verdict, and [[SpeedVerdict]] carries the sentence for each.
     *
     * ⚠️ **A `before` OF ZERO IS REAL AND IS HANDLED EXPLICITLY.** CLS is
     * genuinely 0 on a page that never shifts, and a percentage change from
     * zero has no value — so zero-to-zero is `Unchanged` and zero-to-anything
     * is `Worsened`, rather than a division nobody notices until production.
     *
     * ⚠️ **THE ONLY ARM THAT MAY BECOME [[SpeedVerdict::AlreadyFast]] IS THE
     * UNCHANGED ONE** (decision 5724), and it is reached from both of the two
     * places `Unchanged` is produced — the ordinary comparison and the
     * zero-baseline arm, which is where a page with no layout shift at all
     * lands. An improvement stays an improvement and a regression stays a
     * regression, however fast the site is: `28` §4.3's rule is about what
     * **changed**, and a site that got measurably slower has not become a
     * success by still being inside a boundary.
     */
    public function compare(
        WebVital $metric,
        ?DeviceClass $deviceClass,
        CarbonImmutable $baselineFrom,
        CarbonImmutable $baselineTo,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): SpeedComparison {
        $before = $this->p75($metric, $deviceClass, $baselineFrom, $baselineTo);
        $after = $this->p75($metric, $deviceClass, $from, $to);

        if ($before->state === VitalSampleState::NoMeasurements || $after->state === VitalSampleState::NoMeasurements) {
            return new SpeedComparison(SpeedVerdict::NoMeasurements, $before, $after);
        }

        if (! $before->isMeasured() || ! $after->isMeasured()) {
            return new SpeedComparison(SpeedVerdict::InsufficientData, $before, $after);
        }

        $baseline = $before->p75();
        $measured = $after->p75();

        if ($baseline === 0) {
            return new SpeedComparison(
                $measured === 0 ? $this->unchangedOrAlreadyFast($after) : SpeedVerdict::Worsened,
                $before,
                $after,
            );
        }

        // Positive is worse: every metric here is a duration or a shift.
        $changeBasisPoints = intdiv(($measured - $baseline) * 10_000, $baseline);

        $verdict = match (true) {
            $changeBasisPoints >= self::MATERIAL_CHANGE_BASIS_POINTS => SpeedVerdict::Worsened,
            $changeBasisPoints <= -self::MATERIAL_CHANGE_BASIS_POINTS => SpeedVerdict::Improved,
            default => $this->unchangedOrAlreadyFast($after),
        };

        return new SpeedComparison($verdict, $before, $after, $changeBasisPoints);
    }

    /**
     * *"Nothing changed"*, or *"nothing changed and it was already fast"* — the
     * one place in this application that makes a claim about an **absolute**
     * speed.
     *
     * ⛔ **THREE CONDITIONS, AND EVERY ONE OF THEM IS A REFUSAL** (decision
     * 5724). The metric must have a **published** boundary — never one of ours;
     * it must be one Google **calls** a Core Web Vital, which TTFB is not; and
     * the citation must still be inside its re-verification window, because a
     * boundary nobody can vouch for is not a verified figure any more. Any one
     * of them failing returns [[SpeedVerdict::Unchanged]], which is decision
     * 5610's answer and is complete on its own.
     *
     * ⚠️ **THE SAMPLE FLOOR IS ALREADY BEHIND THIS AND IS NOT RE-CHECKED
     * HERE.** Both readings were `Measured` before this method could be
     * reached, because [[compare()]] returns [[SpeedVerdict::InsufficientData]]
     * for a thin window before it computes anything — decision 5728's rule that
     * a lower floor makes the honest answer rarer rather than softer. A second
     * check here would look like belt and braces and would in fact be the
     * outer-guard-refuses-first shape (398): it would make the real one
     * unfalsifiable.
     */
    private function unchangedOrAlreadyFast(VitalReading $after): SpeedVerdict
    {
        if (! CoreWebVitals::isClaimable($after->metric)) {
            return SpeedVerdict::Unchanged;
        }

        return $after->rating() === VitalRating::Good
            ? SpeedVerdict::AlreadyFast
            : SpeedVerdict::Unchanged;
    }

    /**
     * The smallest bucket whose cumulative count reaches `ceil(0.75 × N)`.
     *
     * @param  list<object{bucket: int|string, samples: int|string}>  $rows  ascending by bucket
     */
    private static function nearestRank(array $rows, int $total): int
    {
        // ceil(3N / 4), in integers. `intdiv(3N + 3, 4)` is `ceil(a/b)` written
        // as `intdiv(a + b - 1, b)` — no float, so no rounding mode to depend
        // on.
        $rank = intdiv(3 * $total + 3, 4);

        $cumulative = 0;

        foreach ($rows as $row) {
            $cumulative += (int) $row->samples;

            if ($cumulative >= $rank) {
                return (int) $row->bucket;
            }
        }

        // ⚠️ UNREACHABLE WHILE `$total` IS THE SUM OF THESE ROWS, AND LOUD
        // RATHER THAN A ZERO IF IT EVER IS NOT — a silent 0 here is "instant",
        // which is the one wrong answer nobody would question.
        throw new LogicException(
            'The cumulative sample count never reached the p75 rank, which means the total handed '
            .'to this method is not the total of the rows handed with it.',
        );
    }
}
