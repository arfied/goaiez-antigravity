<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Warehouse\BenchmarkRun;
use App\Services\Warehouse\NetworkBenchmarks;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use LogicException;
use Throwable;

/**
 * Rebuild L3 — `GOAIEZ_PIXEL_MASTER_BUILD` §5.5's network benchmarks — for a
 * range of UTC days.
 *
 * ⛔ **NOT SCHEDULED, AND FOR A DIFFERENT REASON THAN `warehouse:replay`'S.**
 * That command refuses a schedule on principle: a replay rewrites a range, and a
 * timer rewriting the warehouse unwatched will one day overwrite good rows with
 * bad ones. This one is unscheduled for a plainer reason — **the layer it reads
 * is not on a timer either.** `l2_fact_daily_tenant` is written only by
 * `warehouse:replay`, which is an operator action, so a nightly L3 job would
 * derive from whatever L2 an operator last happened to build (decision 5948). A
 * cadence for L3 is a cadence for L2 first, and that is a decision about every
 * tenant's warehouse rather than about this command.
 *
 * ⚠️ **AND ON ANY REAL DEPLOYMENT TODAY IT PUBLISHES NOTHING.** Every business's
 * `vertical` is `null` because nothing writes that column — see
 * [[\App\Enums\BenchmarkVertical]] — so every business is excluded before its
 * measurements are read. The output says so, with a count, rather than printing
 * a cheerful zero.
 */
#[Signature('warehouse:benchmark {--from= : First UTC day, YYYY-MM-DD} {--to= : Last UTC day, YYYY-MM-DD, inclusive}')]
#[Description('Rebuild the de-identified L3 network benchmarks from L2 for a day range.')]
final class DeriveNetworkBenchmarks extends Command
{
    public function handle(NetworkBenchmarks $benchmarks): int
    {
        try {
            $from = CarbonImmutable::parse((string) ($this->option('from') ?: 'yesterday'))->utc()->startOfDay();
            $to = CarbonImmutable::parse((string) ($this->option('to') ?: $from->toDateString()))->utc()->startOfDay();
        } catch (Throwable $e) {
            $this->error('Could not read the range: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($to->lessThan($from)) {
            $this->error('The range ends before it starts. --to is inclusive and must not precede --from.');

            return self::FAILURE;
        }

        try {
            for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
                $this->report($benchmarks->derive($day));
            }
        } catch (LogicException $e) {
            // ⛔ THE k FLOOR REFUSAL LANDS HERE AND MUST READ AS A REFUSAL RATHER
            // THAN A CRASH. NetworkBenchmarks::minimumCohort() throws when an
            // operator has set `benchmarks.min_cohort` below eight, and the
            // sentence it throws names the setting and says nothing was
            // published — which is the whole of what an operator needs.
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            // Never leave a security context established after a console
            // command: the PostgreSQL session variable outlives this process's
            // connection under any pooler, and a worker inheriting it would
            // start as whichever tenant the walk happened to touch last.
            Tenancy::forgetAll();
        }

        return self::SUCCESS;
    }

    private function report(BenchmarkRun $run): void
    {
        $this->line(sprintf(
            '%s  k=%d  %d cohorts published, %d suppressed  from %d contributing %s  sha256:%s',
            $run->day->toDateString(),
            $run->minimumCohort,
            $run->published,
            $run->suppressed,
            $run->contributing,
            str('business')->plural($run->contributing)->toString(),
            substr($run->digest, 0, 16),
        ));

        // ⚠️ THE EXCLUSIONS ARE PRINTED WHENEVER THEY ARE NON-ZERO, AND THE
        // MIDDLE ONE IS THE WHOLE INSTALL BASE TODAY. "Nothing published" and
        // "nothing to publish" are different facts, and a command that reported
        // only the first would leave a layer that is inert by construction
        // looking like a layer that is working.
        if ($run->withoutRecognisedVertical > 0) {
            $this->line(
                '  '.$run->withoutRecognisedVertical.' excluded: no recognised vertical. '
                .'`businesses.vertical` has no writer outside the factory (decision 5940), so on a '
                .'real deployment this is every business and L3 stays empty until something sets it.'
            );
        }

        if ($run->withoutMeasurements > 0) {
            $this->line('  '.$run->withoutMeasurements.' skipped: no L2 row for the day. `warehouse:replay` is what builds one.');
        }
    }
}
