<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\BenchmarkExclusion;
use App\Enums\BenchmarkMetric;
use App\Enums\BenchmarkVertical;
use App\Models\Business;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * L3 — the de-identified network layer, derived from L2, one day at a time.
 *
 * `GOAIEZ_PIXEL_MASTER_BUILD` §5.5's `benchmark_cohort_daily`, and the thing
 * `29` §11.2 row 25 (Stage 10 — Benchmarks & agency) is meant to stand on. The
 * creating migration carries the three rules and how each is held; this class is
 * the ETL.
 *
 * ---------------------------------------------------------------------------
 * ⛔ IF SOMEBODY OBTAINED THE WHOLE OF L3, WHAT COULD THEY LEARN?
 * ---------------------------------------------------------------------------
 * Answered here rather than in a document, because the answer is a property of
 * this code and moves when it does.
 *
 * **They could learn**: that we have customers in a vertical, how many of them
 * reported traffic on a given day (`tenant_count`, never fewer than eight), and
 * three exact order statistics of that day's distribution per metric. Because
 * the percentiles are **nearest-rank**, each published figure *is* one
 * contributing tenant's own number — the median row for `hvac` on a Tuesday is
 * some HVAC customer's exact session count. What is **not** there is any way to
 * say whose: no business id, no name, no domain, no page path, no session or
 * anonymous id, no referrer, no free text of any kind.
 *
 * **What stops them, in code rather than in prose:**
 *
 *  1. `l3_benchmark_cohort_daily` has no tenant column and two CHECK constraints
 *    that reduce its only text columns to closed enum vocabularies, so there is
 *    no column an identifier could be put in even by a hand-written `INSERT`.
 *  2. `l3_benchmark_cohort_daily_k_anonymity_floor` refuses any row whose
 *    distribution is over fewer than eight tenants, so a cohort can never narrow
 *    to a readable one, and this method never writes one to begin with.
 *  3. Nothing but this class writes the table — `Architecture/WarehouseTest`'s
 *    derived-layer lint fails the build on any other file that names it — so
 *    there is one derivation to read rather than a surface to audit.
 *
 * ⚠️ **WHAT IS NOT CLAIMED.** k-anonymity over eight tenants is a weak
 * guarantee against an attacker who *already knows* most of a cohort:
 * differencing over consecutive days as a tenant joins or leaves moves an order
 * statistic by one rank, and somebody holding seven of eight members' own
 * analytics could bound the eighth. Percentiles are what make that weak rather
 * than trivial — a stored **sum** or **mean** would give the eighth member's
 * value by subtraction, which is why neither is stored and neither should be
 * added. `tenant_count` itself discloses our customer count per vertical per
 * day; that is a fact about us and not about an identifiable business, and §5.5
 * asks for it because it is what makes the k claim checkable.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS WALKS TENANTS INSTEAD OF RUNNING ONE CROSS-TENANT QUERY
 * ---------------------------------------------------------------------------
 * ⛔ **BECAUSE ROW-LEVEL SECURITY WOULD ANSWER A CROSS-TENANT `SELECT` WITH
 * ZERO ROWS, AND THAT IS THE CORRECT ANSWER.** Every L2 mart is
 * `ENABLE`+`FORCE` RLS keyed on `app.business_id`, so
 * `SELECT … FROM l2_fact_daily_tenant` with no tenant established returns
 * nothing at all — silently. The version of this class that "worked" would be
 * one that found a way around that, and there is no version of a cross-tenant
 * aggregate worth weakening the tenant boundary for. So it does what every other
 * platform-wide sweep in this codebase does: walks users, uses the
 * `owner_lookup` policy to find the businesses each owns, and reads each one
 * inside `Tenancy::actingAs()`. The reads are per tenant; only the *arithmetic*
 * is cross-tenant, and it happens in PHP over integers.
 *
 * ⚠️ **WHICH IS ALSO WHAT MAKES THE PHI FILTER FALSIFIABLE.** Asked once per
 * business, at the boundary, with an answer that is read fresh from the column
 * — rather than inferred from "a tenant with nothing to say has no L2 rows", which
 * is true, is an outer guard, and would leave the inner one unfalsifiable (398).
 *
 * ---------------------------------------------------------------------------
 * DETERMINISM
 * ---------------------------------------------------------------------------
 * §11 row 7's byte-identical property is a claim about the derived layers and
 * this is one of them, so:
 *
 *  · every value is an integer, and every division is `intdiv()` — no float
 *    enters the derivation at any point, including the percentile rank, which
 *    is `ceil(n·p/100)` written as integer arithmetic rather than `ceil()`;
 *  · the percentile is **discrete** (nearest-rank). `percentile_cont` and every
 *    interpolating variant return `double precision`, which the reproducible-DDL
 *    lint forbids in a derived table for good reason;
 *  · sorting is `sort($values, SORT_NUMERIC)` over ints, which is a total order
 *    with no collation and no locale in it;
 *  · the day is deleted and rebuilt in one transaction, so a re-run is a
 *    function of L2 and not the union of two runs — [[Replayer]]'s rule.
 */
final readonly class NetworkBenchmarks
{
    /**
     * The k in k-anonymity. Eight contributing tenants.
     *
     * ⛔ **A FLOOR, NOT A DEFAULT.** `benchmarks.min_cohort` may raise it and may
     * not lower it — {@see self::minimumCohort()} — and the same number is a
     * CHECK constraint on the table, so the three places that could disagree
     * are compared by `Architecture/WarehouseTest` on every run.
     *
     * ⚠️ **ONE NUMBER, ONE UNIT, AND IT WAS WORTH CHECKING** (decision 5942).
     * `29` §11.2 row 25's gate is *"k≥8 suppression"* and row 19's is
     * *"cohort ≥8"*, which are two rows about two features — benchmarks, and the
     * self-learning allocator. `28` §5.4 settles that they are the same rule:
     * *"Cross-tenant learning uses aggregate scores only (cohort ≥8, **mirroring
     * the existing benchmark rule**)"*. The unit is contributing **tenants** in
     * both — §5.5's column is `tenant_count`, and §12.1's gate sentence is
     * *"cohort of 7 suppressed"*. Whoever builds row 19 binds this constant
     * rather than typing 8 again: four marts once disagreed about what a
     * conversion is (5146), and two layers disagreeing about what k is would be
     * the same defect with a privacy consequence.
     */
    public const int K_ANONYMITY_FLOOR = 8;

    /**
     * §7.3's `benchmark_min_cohort`, dotted like every other key in the manifest.
     */
    public const string MIN_COHORT_KEY = 'benchmarks.min_cohort';

    public function __construct(
        private DefaultsRegistry $registry,
    ) {}

    /**
     * Rebuild every cohort for one UTC day.
     */
    public function derive(CarbonImmutable $day): BenchmarkRun
    {
        $minimum = $this->minimumCohort();
        $utcDay = $day->utc()->startOfDay();

        /** @var array<string, array<string, list<int>>> $samples vertical => metric => values */
        $samples = [];

        $contributing = 0;
        $unrecognised = 0;
        $silent = 0;

        foreach ($this->businessIds() as $businessId) {
            $sample = Tenancy::actingAs(
                $businessId,
                fn (): BenchmarkExclusion|array => $this->sample($businessId, $utcDay),
            );

            if ($sample instanceof BenchmarkExclusion) {
                // ⚠️ NO `default` ARM, DELIBERATELY. A fourth reason has to be
                // counted deliberately rather than silently reported to an
                // operator as a tenant with nothing to say.
                match ($sample) {
                    BenchmarkExclusion::UnrecognisedVertical => $unrecognised++,
                    BenchmarkExclusion::NoMeasurements => $silent++,
                };

                continue;
            }

            $contributing++;

            foreach ($sample['values'] as $metric => $value) {
                $samples[$sample['vertical']][$metric][] = $value;
            }
        }

        [$published, $suppressed] = $this->publish($utcDay, $samples, $minimum);

        return new BenchmarkRun(
            day: $utcDay,
            minimumCohort: $minimum,
            published: $published,
            suppressed: $suppressed,
            contributing: $contributing,
            withoutRecognisedVertical: $unrecognised,
            withoutMeasurements: $silent,
            digest: WarehouseSnapshot::networkDigest(),
        );
    }

    /**
     * The k this run will apply.
     *
     * ⛔ **AN OPERATOR MAY RAISE IT AND MAY NOT LOWER IT, AND LOWERING IT STOPS
     * THE RUN RATHER THAN BEING QUIETLY IGNORED.** `max(8, $configured)` was the
     * first draft and is worse: the Ops screen would read 5, the derivation
     * would apply 8, and the settings screen would be describing a rule the
     * system does not follow. A privacy floor with two visible values is the
     * failure, not the low number. Refusing is also fail-closed in the direction
     * that matters — nothing is published at all.
     */
    public function minimumCohort(): int
    {
        $configured = $this->registry->int(self::MIN_COHORT_KEY);

        if ($configured < self::K_ANONYMITY_FLOOR) {
            throw new LogicException(
                'benchmarks.min_cohort is set to '.$configured.', below the k-anonymity floor of '
                .self::K_ANONYMITY_FLOOR.'. `GOAIEZ_PIXEL_MASTER_BUILD` §5.5 and `29` §12.1 both '
                .'fix that floor at eight contributing tenants, and the table itself refuses a row '
                .'below it, so no cohort could be written anyway. Raise the setting back to '
                .self::K_ANONYMITY_FLOOR.' or above; nothing has been published.'
            );
        }

        return $configured;
    }

    /**
     * One business's contribution, or why it has none.
     * ⛔ **A COVERED-ENTITY EXCLUSION STOOD AT THE TOP OF THIS METHOD AND IS
     * GONE — THE OWNER OVERRODE `29` §2 RULE 24 AND ITS §12.1 L3-EXCLUSION LINE
     * ON 2026-08-30** (12532, 12539). It asked `PhiExclusion::isCoveredEntity()`
     * and returned `BenchmarkExclusion::CoveredEntity`.
     *
     * ⚠️ **THE K-ANONYMITY FLOOR IS UNTOUCHED AND WAS CHECKED BEFORE THIS WAS
     * REMOVED**, because a guard doing two jobs is how this slice's first two
     * couplings were found. It is not one: the floor is a database CHECK
     * (`l3_benchmark_cohort_daily_k_anonymity_floor`) plus
     * {@see self::MIN_COHORT_KEY}, and neither reads a tenant's
     * classification. **`29` §12.1's k-anonymity line stands.**
     *
     * @return array{vertical: string, values: array<string, int>}|BenchmarkExclusion
     */
    private function sample(int $businessId, CarbonImmutable $day): BenchmarkExclusion|array
    {
        // ⚠️ `tryFrom` IS THE "NO FREE TEXT" GATE OF RULE 1, IN CODE. See
        // BenchmarkVertical: the column is a plain string and an unrecognised
        // value excludes the tenant rather than opening a bucket named after
        // whatever was typed into it.
        $vertical = BenchmarkVertical::tryFrom(
            (string) (Business::query()->whereKey($businessId)->value('vertical') ?? '')
        );

        if ($vertical === null) {
            return BenchmarkExclusion::UnrecognisedVertical;
        }

        $row = DB::table('l2_fact_daily_tenant')
            ->where('business_id', $businessId)
            ->where('day', $day->toDateString())
            ->first();

        if ($row === null) {
            return BenchmarkExclusion::NoMeasurements;
        }

        $values = [];

        foreach (BenchmarkMetric::cases() as $metric) {
            $value = $metric->valueFor(
                sessions: (int) $row->sessions,
                engagedSessions: (int) $row->engaged_sessions,
                conversions: (int) $row->conversions,
                pageviews: (int) $row->pageviews,
            );

            if ($value !== null) {
                $values[$metric->value] = $value;
            }
        }

        return $values === []
            ? BenchmarkExclusion::NoMeasurements
            : ['vertical' => $vertical->value, 'values' => $values];
    }

    /**
     * Write the day, suppressing every cohort below k.
     *
     * ⚠️ **DELETE-THEN-INSERT IN ONE TRANSACTION**, on [[Replayer]]'s rule: an
     * upsert would leave behind a cohort that no longer meets k — the row a
     * suppression is supposed to remove — so the rebuild would be the union of
     * two runs rather than a rebuild. This is the mechanism by which a cohort
     * that *shrinks* below k on a re-derivation disappears.
     *
     * @param  array<string, array<string, list<int>>>  $samples
     * @return array{int, int} published, suppressed
     */
    private function publish(CarbonImmutable $day, array $samples, int $minimum): array
    {
        $rows = [];
        $suppressed = 0;

        foreach ($samples as $vertical => $metrics) {
            foreach ($metrics as $metric => $values) {
                if (count($values) < $minimum) {
                    $suppressed++;

                    continue;
                }

                sort($values, SORT_NUMERIC);

                $rows[] = [
                    'day' => $day->toDateString(),
                    'vertical' => $vertical,
                    'metric' => $metric,
                    'tenant_count' => count($values),
                    'p25' => self::percentile($values, 25),
                    'p50' => self::percentile($values, 50),
                    'p75' => self::percentile($values, 75),
                ];
            }
        }

        DB::transaction(function () use ($day, $rows): void {
            DB::table('l3_benchmark_cohort_daily')->where('day', $day->toDateString())->delete();

            if ($rows !== []) {
                DB::table('l3_benchmark_cohort_daily')->insert($rows);
            }
        });

        return [count($rows), $suppressed];
    }

    /**
     * The nearest-rank percentile of an ascending list.
     *
     * ⛔ **DISCRETE, AND `ceil()` IS WRITTEN AS INTEGER ARITHMETIC.** The rank is
     * `ceil(n · p / 100)`, and `intdiv($n * $percent + 99, 100)` is that same
     * value without a float ever existing. The three fractions here happen to be
     * exactly representable, so `(int) ceil(…)` would agree today — which is
     * precisely the kind of "agrees until it does not" this gate exists to
     * refuse, and the integer form costs nothing.
     *
     * ⚠️ **THE RESULT IS A MEMBER OF THE SET**, which is a disclosure property
     * as much as an arithmetic one — see the class docblock.
     *
     * @param  list<int>  $ascending  non-empty
     */
    private static function percentile(array $ascending, int $percent): int
    {
        $rank = intdiv(count($ascending) * $percent + 99, 100);

        return $ascending[max(1, $rank) - 1];
    }

    /**
     * Every business on the platform, through the `owner_lookup` policy.
     *
     * The house pattern for a platform-wide sweep — `ResetMonthlyCredits`,
     * `PruneStoredObjects` and `RunAutoTopUps` all do exactly this. `businesses`
     * is `FORCE` row-level security keyed on its own id, so there is no query
     * that lists them all; what exists is a `SELECT`-only policy admitting the
     * businesses a named user owns, and walking users is how the platform sees
     * the whole set without any layer being weakened.
     *
     * ⚠️ **ORDERED BY ID SO THE WALK IS STABLE.** The published bytes do not
     * depend on it — the cohorts are sorted before a percentile is taken — but
     * the *counts* this run reports would otherwise vary by nothing at all,
     * which makes a difference between two runs unreadable.
     *
     * @return list<int>
     */
    private function businessIds(): array
    {
        $ids = [];

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$ids): void {
                foreach ($users as $user) {
                    $userId = (int) $user->getKey();

                    Tenancy::setUser($userId);

                    // withoutGlobalScopes because the scope calls
                    // Tenancy::idOrFail() and no tenant is established yet — the
                    // circularity `ResolveTenant` documents. The database still
                    // restricts this to businesses owned by the user just set.
                    foreach (Business::withoutGlobalScopes()->where('owner_user_id', $userId)->orderBy('id')->pluck('id') as $businessId) {
                        $ids[] = (int) $businessId;
                    }
                }
            });

        return $ids;
    }
}
