<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Enums\PlatformHealthSignal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The platform's own counters — the writer and the reader (T176 §3, P23).
 *
 * ## Every write is an atomic increment, and every write is swallowed
 *
 * ⚠️ **THE INCREMENT IDIOM IS `SendingHealth`'s, DELIBERATELY UNCHANGED.**
 * `INSERT … ON CONFLICT DO UPDATE SET col = col + 1`, because
 * `$row->failures++; $row->save()` across two workers in the same hour loses one
 * of the two — and a counter that reads low is a bell that does not ring. What
 * makes it work is the unique index on `(signal, source, window_start)`; without
 * one, `ON CONFLICT` has nothing to conflict on and every observation inserts a
 * fresh row.
 *
 * ⛔ **AND IT NEVER THROWS INTO ITS CALLER — R25, "a bell, never a brake".**
 * These recorders sit inside a payment webhook, inside `AiRouter` (whose own
 * contract is that it never throws) and inside a queued job. **Observing that
 * something went wrong must not be able to make it worse**, so a failed write is
 * logged and dropped. The cost is stated rather than hidden: a database that
 * cannot take these writes produces a permanently zero counter, which reads as a
 * healthy platform — the `sending_health_windows` failure (2496–2499) reached by
 * a different route. What contains it is that the same database failure takes
 * everything else down loudly at the same time.
 *
 * ## The reads are sums over a window, and the heartbeat is a max
 *
 * A rate is `failures / total` over the window, and only for a signal whose
 * happy path also increments `total` — {@see PlatformHealthSignal::countsSuccesses()}
 * is what a caller asks instead of remembering which is which.
 *
 * ## One signal carries a duration as well as a count
 *
 * ⚠️ **{@see self::recordRun()} IS THE ONLY WRITER OF `max_duration_ms`**, and
 * the aggregate is a **maximum** rather than a sum, so the increment idiom above
 * gains a `GREATEST(...)` arm rather than another `+ 1`. Postgres' `GREATEST`
 * ignores nulls, which is what lets the column stay null for the three signals
 * that have no duration and still take a first value from the first run that
 * does — see the adding migration for why null is not zero here.
 *
 * ⚠️ **THE HEARTBEAT READ IS `MAX(last_at)` AND NOT `MAX(window_start)`.** The
 * bucket is only accurate to the hour, so a scheduler that died at 09:05 would
 * read as alive until 10:00 — fifty-five minutes of silence that the whole point
 * of the heartbeat is to notice.
 */
final class PlatformHealth
{
    /**
     * Rows per DELETE — `PruneTrialOriginClaims`' figure and its reasoning
     * (7760-7779). See {@see self::prune()} for the gap this bounds and for why
     * the argument here is milder than the one on `OperatorAlerts::prune()`.
     */
    private const int CHUNK = 500;

    /**
     * Record one observation that failed.
     *
     * ⚠️ **`total` GOES UP TOO, ALWAYS.** A failure is an observation. Counting
     * it only in `failures` would make the rate `failures / successes`, which
     * exceeds 100% the moment a vendor is more than half down — and a rate that
     * can exceed its own maximum is one nobody trusts the next time.
     */
    public function recordFailure(PlatformHealthSignal $signal, string $source): void
    {
        $this->increment($signal, $source, failed: true);
    }

    /**
     * Record one observation that worked.
     *
     * Only meaningful for a signal that counts successes; calling it for one
     * that does not would put a denominator under a count that was never a rate.
     */
    public function recordSuccess(PlatformHealthSignal $signal, string $source): void
    {
        $this->increment($signal, $source, failed: false);
    }

    /**
     * "I am still here."
     *
     * ⛔ **NOTHING READS THIS TO DECIDE ANYTHING — ITS ABSENCE IS THE SIGNAL.**
     * See {@see PlatformHealthSignal::Heartbeat}: a check that only runs while
     * the thing it checks is running cannot report that it stopped.
     */
    public function beat(string $process): void
    {
        $this->increment(PlatformHealthSignal::Heartbeat, $process, failed: false);
    }

    /**
     * One run of one scheduled command, and how long it took (7080–7099).
     *
     * ⚠️ **`$overran` IS THE ONLY THING THIS SIGNAL CALLS A FAILURE**, and it is
     * decided by the caller rather than here, because the number it is compared
     * against lives on the schedule entry — `Event::$expiresAt` — and this class
     * has no business reading `routes/console.php`.
     * {@see ScheduledRunMeter} is that caller.
     *
     * ⛔ **AND THAT SENTENCE IS NOW A BOUNDARY RATHER THAN A LIMITATION**
     * (9683). A command that **exited non-zero** is a different question with a
     * different remedy — the command is broken, versus the window is too narrow
     * — and it is counted under
     * {@see PlatformHealthSignal::ScheduledRunFailed} through
     * {@see self::recordFailure()}, from the same caller. **Do not fold the two
     * together**: 9371's rule is that the honest counter is levelled up rather
     * than down, and merging them reads on a diff as removing an inconsistency
     * while making one number answer two questions.
     *
     * @param  string  $command  The artisan command name, so the row joins
     *                           against `routes/console.php` by something a
     *                           person can read.
     */
    public function recordRun(string $command, int $durationMs, bool $overran): void
    {
        $this->increment(
            PlatformHealthSignal::ScheduledRun,
            $command,
            failed: $overran,
            durationMs: max(0, $durationMs),
        );
    }

    /**
     * When `$process` was last seen, or null if it has never been seen at all.
     *
     * ⚠️ **NULL IS DELIBERATELY NOT "SILENT", AND THE WATCH TREATS IT THAT WAY.**
     * A process that has never beaten is a fresh install, a fresh worktree or a
     * test database — alerting on it would page somebody during every deploy of
     * a new environment, which is 511's failure on day one. The heartbeat arms
     * itself on its first beat. **What that costs is real and is stated where an
     * operator will read it**: an installation whose scheduler never ran at all
     * is never alerted by this mechanism, which is exactly what the external
     * uptime monitors in the launch ops annex are for.
     *
     * ## ⛔ THE READ HAD A ONE-DAY HORIZON AND THAT MADE THE SENTENCE ABOVE A
     * DEFECT — CORRECTED 2026-08-26 (9930–9944)
     *
     * ⛔ **THE OLD BODY, KEPT AND DATED** (4368): it carried
     * `->where('window_start', '>=', $this->bucket($now)->subDay())` under the
     * comment *"a heartbeat older than a day is not a heartbeat, and reading the
     * whole table to find one would make this scan grow forever."* **A process
     * dead for more than a day therefore answered null**, which is the same
     * value as *never beaten* — so {@see PlatformHealthChecks::sweepHeartbeats()}
     * skipped it, correctly by its own rule, and
     * `OperatorAlertKind::HeartbeatSilent` **rang hourly for the first day of an
     * outage and then went permanently quiet.** Nothing else watches the queue.
     *
     * ⚠️ **THE FIRST HALF OF THAT COMMENT WAS A JUDGEMENT AND THE SECOND HALF
     * WAS WRONG.** *"Older than a day is not a heartbeat"* is true of the
     * question **is this beating now** and false of the question this method is
     * actually asked, which is **when did it last beat** — and the two are one
     * early return apart. *"Reading the whole table"* was never what this did:
     * the predicate is an equality on both leading columns of the unique index
     * `(signal, source, window_start)`, so the read has always been one index
     * range, and it is now **one index entry** — the newest bucket, descending,
     * limit one. Measured with `EXPLAIN ANALYZE` rather than reasoned: a
     * backward index scan, one row, no heap sort, and it does not grow with the
     * table at all. **The bound was paying for a cost that was not there.**
     *
     * ⚠️ **`last_at` IS TAKEN FROM THE NEWEST BUCKET RATHER THAN AS A `MAX` OVER
     * ALL OF THEM, AND THAT RESTS ON A PROPERTY OF THE ONE WRITER.**
     * {@see self::increment()} stamps `last_at = $now` and
     * `window_start = bucket($now)` in the same statement, so the two move
     * together and the newest bucket necessarily holds the newest stamp. A
     * second writer that set one without the other would break this, which is
     * why there is exactly one.
     *
     * ⛔ **AND THE HORIZON THAT REPLACED IT IS THE PRUNER, WHICH IS WHY
     * {@see self::prune()} NOW KEEPS THE LAST BEAT FOR EVER.** An unbounded read
     * over a table that has forgotten the beat is the same defect on a thirty-day
     * fuse.
     *
     * ⚠️ **THE `$now` PARAMETER IS GONE RATHER THAN IGNORED.** It existed only to
     * place the window, and a parameter a method no longer consults is a promise
     * to a caller that nothing keeps — the sweep passes its own clock everywhere
     * else and would have gone on passing it here.
     */
    public function lastBeat(string $process): ?CarbonImmutable
    {
        $last = DB::table('platform_health_windows')
            ->where('signal', PlatformHealthSignal::Heartbeat->value)
            ->where('source', $process)
            ->orderByDesc('window_start')
            ->limit(1)
            ->value('last_at');

        if (! is_string($last)) {
            return null;
        }

        // Read back as UTC because that is how it was written — a naive string
        // parsed in the application's clock would be reinterpreted rather than
        // converted, which is an error of exactly the size of the offset.
        return CarbonImmutable::parse($last, 'UTC');
    }

    /**
     * Totals for one signal over the last `$minutes`, optionally for one source.
     *
     * ⚠️ **THE WINDOW IS WIDENED TO WHOLE BUCKETS AND THAT IS NOT A ROUNDING
     * ERROR.** The rows are hourly, so a "last 60 minutes" read that started
     * mid-hour would either drop the current hour's partial bucket — reporting
     * zero failures during the very minute they are happening — or count part of
     * an hour as all of it. Widening is the honest half: the window covers at
     * least what was asked for, and the alert says how long it actually was.
     *
     * @return array{total: int, failures: int, since: CarbonImmutable}
     */
    public function totals(
        PlatformHealthSignal $signal,
        int $minutes,
        ?string $source = null,
        ?CarbonImmutable $now = null,
    ): array {
        $now ??= CarbonImmutable::now();
        $since = $this->bucket($now->subMinutes(max(1, $minutes)));

        $query = DB::table('platform_health_windows')
            ->where('signal', $signal->value)
            ->where('window_start', '>=', $since);

        if ($source !== null) {
            $query->where('source', $source);
        }

        /** @var object{total: int|string|null, failures: int|string|null}|null $row */
        $row = $query
            ->selectRaw('COALESCE(SUM(total), 0) AS total')
            ->selectRaw('COALESCE(SUM(failures), 0) AS failures')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'failures' => (int) ($row->failures ?? 0),
            'since' => $since,
        ];
    }

    /**
     * Every source that produced a row for this signal inside the window.
     *
     * ⚠️ **DISCOVERED RATHER THAN LISTED, AND THAT IS WHAT MAKES THE VENDOR
     * ALERT OUTLIVE THIS SLICE.** A hardcoded `['anthropic', 'openai']` would be
     * a list that goes stale the day a third provider is wired — and the tell
     * would be silence, which is the one failure mode alerting cannot have.
     * Anything that calls {@see self::recordFailure()} with a new source is
     * watched from its first call, with no change here.
     *
     * @return list<string>
     */
    public function sources(PlatformHealthSignal $signal, int $minutes, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return array_values(
            DB::table('platform_health_windows')
                ->where('signal', $signal->value)
                ->where('window_start', '>=', $this->bucket($now->subMinutes(max(1, $minutes))))
                ->distinct()
                ->orderBy('source')
                ->pluck('source')
                ->map(static fn (mixed $source): string => (string) $source)
                ->all()
        );
    }

    /**
     * Every source of one signal over the last `$days`, with its slowest run.
     *
     * ⚠️ **THE READER IS A PERSON, NOT A THRESHOLD.** `ops:schedule-runtimes`
     * is what calls this, and it is a command somebody runs before arguing a
     * window — 6975's *"the honest state until then is that 6971's 'none of the
     * five is at risk' is an argument rather than a measurement"*, with the
     * measurement attached. Nothing on a clock reads it and nothing decides
     * anything from it; the bell that decides is raised at the moment of the
     * overrun instead, by {@see ScheduledRunMeter}.
     *
     * ⚠️ **`$days` CANNOT USEFULLY EXCEED `WatchPlatformHealth::KEEP_DAYS`**,
     * which prunes this table at thirty days. Asking for ninety is not an error
     * and returns thirty days of rows; saying so is the reader's job.
     *
     * @return array<string, array{runs: int, overruns: int, max_duration_ms: int|null, last_at: CarbonImmutable|null}>
     */
    public function durationsBySource(PlatformHealthSignal $signal, int $days, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        /** @var list<object{source: string, runs: int|string|null, overruns: int|string|null, max_duration_ms: int|string|null, last_at: string|null}> $rows */
        $rows = DB::table('platform_health_windows')
            ->where('signal', $signal->value)
            ->where('window_start', '>=', $this->bucket($now)->subDays(max(1, $days)))
            ->groupBy('source')
            ->orderBy('source')
            ->selectRaw('source')
            ->selectRaw('COALESCE(SUM(total), 0) AS runs')
            ->selectRaw('COALESCE(SUM(failures), 0) AS overruns')
            ->selectRaw('MAX(max_duration_ms) AS max_duration_ms')
            ->selectRaw('MAX(last_at) AS last_at')
            ->get()
            ->all();

        $out = [];

        foreach ($rows as $row) {
            $out[(string) $row->source] = [
                'runs' => (int) ($row->runs ?? 0),
                'overruns' => (int) ($row->overruns ?? 0),
                'max_duration_ms' => $row->max_duration_ms === null ? null : (int) $row->max_duration_ms,
                // As UTC, because that is how it was written — see increment().
                'last_at' => is_string($row->last_at) ? CarbonImmutable::parse($row->last_at, 'UTC') : null,
            ];
        }

        return $out;
    }

    /**
     * Drop buckets past the retention horizon.
     *
     * A range delete on an indexed column, and cheap enough to run on every
     * sweep: on an ordinary run it matches nothing, and once an hour it matches
     * the buckets that have just fallen off the horizon. Without it the table
     * grows without bound, and a table nobody ever cleans is what this is
     * about rather than the disk.
     *
     * ⛔ **THIS SAID "A FEW DOZEN ROWS A DAY" AND IT STOPPED BEING TRUE ON
     * 2026-08-21 — CORRECTED 2026-08-22 (7760-7779).** {@see
     * PlatformHealthSignal::ScheduledRun} joined the signals at 7080-7099 and
     * writes one row per **command** per hour, so the daily figure is in the
     * hundreds rather than the dozens. ⚠️ **The sentence is worth correcting
     * rather than deleting** because `OperatorAlerts::RETENTION_DAYS` quotes it
     * by name as the precedent it is departing from, and a precedent quoted
     * accurately from a stale source is 2505's shape at one remove.
     *
     * ## ⚠️ Chunked, and this is the mildest of the three (7760-7779)
     *
     * ⚠️ **SAID PLAINLY: ON A HEALTHY PLATFORM THIS DELETE NEVER NEEDS A SECOND
     * BATCH.** It runs every five minutes against a thirty-day horizon, so what
     * it matches is one hour's worth. **The run that is not ordinary is the one
     * after a gap**, and the gap is reachable without anything else being wrong:
     * the two signals written from the *web* process — `WebhookSignature` and
     * `VendorCall` — keep accumulating while the scheduler is stopped, so a
     * scheduler down for a season and then restarted makes the first run match
     * the whole season, in one statement, inside a command holding a ten-minute
     * overlap lock.
     *
     * ⚠️ **THAT IS A WEAKER ARGUMENT THAN `OperatorAlerts::prune()`'s AND IS
     * WRITTEN AS ONE**: there the first run that ever matches is a year of rows
     * by construction. Here it needs an outage first. The chunk is the same
     * five lines either way, and the two pruners in this command should not
     * differ in a way nobody can explain.
     *
     * ## ⛔ ONE ROW PER PROCESS IS KEPT FOR EVER, AND IT IS NOT AN OPTIMISATION
     * (9930–9944)
     *
     * ⛔ **A COUNTER MAY BE PRUNED; THE LAST BEAT IS A STATE, AND A PRUNED STATE
     * IS A FACT THE PLATFORM HAS DECIDED TO STOP KNOWING.** Every other row here
     * is an hour's arithmetic that no longer matters. The newest heartbeat row of
     * each process is the **only** thing in this application that can tell *"the
     * queue has been dead for five weeks"* from *"this install has never had a
     * worker"* — and those two need opposite answers, one a page and one a
     * silence. Deleting it turns the first into the second, permanently, on the
     * thirty-first day.
     *
     * ⚠️ **SO THE FIX AT {@see self::lastBeat()} WOULD HAVE LASTED A MONTH
     * WITHOUT THIS.** Widening a horizon is not the same as removing one, and a
     * day widened to thirty days reads on a diff exactly like a fix.
     *
     * ⚠️ **THE COST IS TWO ROWS**, one per member of
     * {@see PlatformHealthChecks::PROCESSES}, and the survivors are **discovered
     * from the rows rather than listed** — the same reasoning as
     * {@see self::sources()}, so a third process added tomorrow keeps its own
     * last beat with no change here. ⚠️ **A survivor is only ever the newest
     * bucket of a source**: everything behind it goes on the ordinary horizon,
     * which is what stops this becoming an exemption for the whole signal.
     *
     * ⚠️ **`whereNotIn` ON THE PRIMARY KEY RATHER THAN A CORRELATED `NOT
     * EXISTS`**, because the delete is compiled through
     * `compileDeleteWithJoinsOrLimit()` into a `ctid in (select … limit N)`
     * subquery over the same unaliased table, and a correlated reference inside
     * it resolves against the inner copy. A short list of ids computed once
     * cannot mean two things.
     *
     * @return int rows removed
     */
    public function prune(int $keepDays, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        $cut = $this->bucket($now)->subDays(max(1, $keepDays));

        $survivors = $this->latestBeatIds();

        $deleted = 0;

        do {
            // Postgres compiles a limited DELETE to
            // `delete from … where ctid in (select … limit N)` — verified in
            // `PostgresGrammar::compileDeleteWithJoinsOrLimit()` rather than
            // remembered — so the bound really is per statement.
            $batch = DB::table('platform_health_windows')
                ->where('window_start', '<', $cut)
                ->whereNotIn('id', $survivors)
                ->limit(self::CHUNK)
                ->delete();

            $deleted += $batch;
        } while ($batch === self::CHUNK);

        return $deleted;
    }

    /**
     * The row id of the most recent beat of every process that has ever beaten.
     *
     * Computed once per prune rather than per chunk: the answer cannot change
     * while the delete runs — nothing here writes a beat — and recomputing it
     * inside the loop would put a distinct scan on every batch.
     *
     * @return list<int>
     */
    private function latestBeatIds(): array
    {
        $ids = [];

        $sources = DB::table('platform_health_windows')
            ->where('signal', PlatformHealthSignal::Heartbeat->value)
            ->distinct()
            ->pluck('source');

        foreach ($sources as $source) {
            $id = DB::table('platform_health_windows')
                ->where('signal', PlatformHealthSignal::Heartbeat->value)
                ->where('source', (string) $source)
                ->orderByDesc('window_start')
                ->limit(1)
                ->value('id');

            if ($id !== null) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /**
     * The atomic increment — see the class docblock for why it is raw SQL and
     * why it swallows.
     */
    private function increment(
        PlatformHealthSignal $signal,
        string $source,
        bool $failed,
        ?int $durationMs = null,
    ): void {
        // ⚠️ **`->utc()` EXPLICITLY, NOT BECAUSE `app.timezone` IS ANYTHING ELSE
        // TODAY.** `window_start` is bucketed in UTC by {@see self::bucket()},
        // and a `last_at` written in whatever the application clock happens to
        // be would put two different clocks in one row — where the difference
        // shows up as a heartbeat that reads hours stale the day somebody moves
        // the setting, in a check whose entire subject is the age of a timestamp.
        $now = CarbonImmutable::now()->utc();

        // Trimmed and clamped to the column, because the source of a source
        // string is a vendor name at a call site and a value longer than the
        // column would throw — inside a payment webhook, on the failure path,
        // for the sake of a counter.
        $source = mb_substr(trim($source), 0, 60);

        if ($source === '') {
            $source = 'unknown';
        }

        try {
            DB::table('platform_health_windows')->upsert(
                [[
                    'signal' => $signal->value,
                    'source' => $source,
                    'window_start' => $this->bucket($now),
                    'total' => 1,
                    'failures' => $failed ? 1 : 0,
                    'max_duration_ms' => $durationMs,
                    'last_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['signal', 'source', 'window_start'],
                // ⚠️ The raw expressions are the whole point: `['total' => 1]`
                // would SET the counter to one on every conflict, which reads as
                // "this has happened exactly once, ever" while looking entirely
                // plausible on a screen.
                [
                    'total' => DB::raw('platform_health_windows.total + 1'),
                    'failures' => DB::raw('platform_health_windows.failures + '.($failed ? '1' : '0')),
                    // ⚠️ **`GREATEST` RATHER THAN AN ASSIGNMENT, AND POSTGRES'
                    // NULL HANDLING IS WHAT MAKES ONE ARM SERVE EVERY SIGNAL.**
                    // Verified against Postgres rather than remembered (255):
                    // `GREATEST` ignores nulls and is null only when every
                    // argument is — so a signal that carries no duration cannot
                    // erase one, and the first duration on a row cannot be
                    // swallowed by the null that was there before it. An
                    // assignment would make the column read "however long the
                    // LAST run took", which is a different and much less useful
                    // question than the one the reader asks.
                    //
                    // ⚠️ **`excluded` RATHER THAN THE VALUE INTERPOLATED INTO
                    // THE STRING.** `excluded` is Postgres' name for the row
                    // this statement proposed to insert, so the figure arrives
                    // as the bound parameter it already was — no number is
                    // concatenated into SQL, and the expression stays a literal
                    // that cannot vary with its input.
                    'max_duration_ms' => DB::raw(
                        'GREATEST(platform_health_windows.max_duration_ms, excluded.max_duration_ms)'
                    ),
                    'last_at' => $now,
                    'updated_at' => $now,
                ],
            );
        } catch (Throwable $e) {
            // ⛔ R25. The caller is a webhook, a model call or a job, and none of
            // them may fail because a counter could not be written.
            Log::warning('platform health counter could not be recorded', [
                'signal' => $signal->value,
                'source' => $source,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * The hour a moment belongs to. Always UTC — see the creating migration.
     */
    private function bucket(CarbonImmutable $at): CarbonImmutable
    {
        return $at->utc()->startOfHour();
    }
}
