<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OperatorAlertKind;
use App\Enums\PlatformHealthSignal;
use App\Services\Ops\PlatformHealth;
use App\Services\Ops\ScheduledRunMeter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * What each scheduled command has actually taken, against the window it was
 * given — decision 6975's residual (7080–7099).
 *
 * ⛔ **THIS IS THE INSTRUMENT, AND IT IS DELIBERATELY NOT ON THE SCHEDULE.**
 * 6975's finding is that all thirty-five overlap windows in `routes/console.php`
 * are *arguments* rather than *measurements*. The bell that fires when one of
 * them is wrong is raised by {@see ScheduledRunMeter} at the moment it happens;
 * this is the other half — **the thing somebody runs before arguing a window**,
 * so the next person to widen or narrow one is looking at the same evidence
 * rather than at what the command appears to do.
 *
 * ⚠️ **A SCHEDULED VERSION WOULD PRINT INTO `>> /dev/null`.** The two cron
 * entries in `.claude/skills/deploying/` discard the scheduler's output, and
 * 7029 is this codebase's own record of what that costs: `MeterZernioAccounts`
 * warns at 80% of its ceiling into a stream nobody reads. A report is for a
 * person; a threshold is for the bell.
 *
 * ⚠️ **"USED OF WINDOW" IS A PERCENTAGE OF THE LOCK, NOT OF THE INTERVAL**,
 * because the failure being watched for is a lock expiring under a running
 * process. An entry at 100% has already rung {@see ScheduledRunMeter}'s bell;
 * an entry at 70% is the one worth reading this report to find, and nothing
 * else in this application would ever mention it.
 *
 * ⚠️ **IT SAYS WHICH ENTRIES HAVE NEVER BEEN MEASURED, AND THAT IS THE MOST
 * IMPORTANT LINE ON A FRESH INSTALL.** An empty table reads as a healthy one.
 * On the day this ships every entry is unmeasured, and a report that showed
 * thirty-five blank rows without saying so would be 2496–2499 in a console.
 */
#[AsCommand(name: 'ops:schedule-runtimes')]
final class ReportScheduleRuntimes extends Command
{
    /**
     * ⚠️ **BOUNDED BY THE PRUNE RATHER THAN BY TASTE.**
     * `WatchPlatformHealth::KEEP_DAYS` deletes these buckets at thirty days, so
     * asking for ninety returns thirty days of rows and a misleading heading.
     */
    protected $signature = 'ops:schedule-runtimes {--days=30 : How far back to look, capped at the counter retention}';

    protected $description = 'Show how long each scheduled command has actually taken, against its overlap window';

    public function handle(PlatformHealth $health, Schedule $schedule): int
    {
        $days = max(1, min(WatchPlatformHealth::KEEP_DAYS, (int) $this->option('days')));

        $windows = $this->scheduledWindows($schedule);
        $measured = $health->durationsBySource(PlatformHealthSignal::ScheduledRun, $days);
        $failed = $health->durationsBySource(PlatformHealthSignal::ScheduledRunFailed, $days);

        /** @var list<array{pct: int, cells: list<string>}> $rows */
        $rows = [];
        $unmeasured = [];

        foreach ($windows as $command => $windowMinutes) {
            $seen = $measured[$command] ?? null;

            if ($seen === null || $seen['max_duration_ms'] === null) {
                $unmeasured[] = $command;

                continue;
            }

            $slowestMs = $seen['max_duration_ms'];
            $pct = $windowMinutes === null ? -1 : intdiv($slowestMs * 100, $windowMinutes * 60_000);

            $rows[] = [
                'pct' => $pct,
                'cells' => [
                    $command,
                    $windowMinutes === null ? 'unguarded' : $windowMinutes.'m',
                    (string) $seen['runs'],
                    (string) $seen['overruns'],
                    (string) ($failed[$command]['runs'] ?? 0),
                    $this->readable($slowestMs),
                    $pct < 0 ? '—' : $pct.'%',
                ],
            ];
        }

        // Closest to being wrong first. The reader's question is "which of these
        // windows is about to be the one 6975 warned about", and the alphabet
        // answers a different one.
        usort($rows, static fn (array $a, array $b): int => $b['pct'] <=> $a['pct']);

        if ($rows !== []) {
            $this->table(
                ['Command', 'Window', 'Runs', 'Overruns', 'Failed', 'Slowest', 'Used of window'],
                array_map(static fn (array $row): array => $row['cells'], $rows),
            );
        }

        $this->line("Measured over the last {$days} day(s). Counters are pruned at "
            .WatchPlatformHealth::KEEP_DAYS.' days.');

        $this->reportFailures($failed);
        $this->reportUnmeasured($unmeasured);
        $this->reportOrphans(array_values(array_diff(array_keys($measured), array_keys($windows))));

        return self::SUCCESS;
    }

    /**
     * Every entry on the live schedule, and the window it was given.
     *
     * ⚠️ **READ OFF THE `Schedule` OBJECT RATHER THAN OFF A TABLE OF OURS**, so
     * this report cannot disagree with `routes/console.php`. It is the same
     * source `SchedulingTest` asserts against and the same one
     * {@see ScheduledRunMeter} compares a duration to.
     *
     * @return array<string, int|null>
     */
    private function scheduledWindows(Schedule $schedule): array
    {
        $windows = [];

        foreach ($schedule->events() as $event) {
            $windows[$this->commandName($event)] = $event->withoutOverlapping
                ? max(1, $event->expiresAt)
                : null;
        }

        return $windows;
    }

    private function commandName(Event $event): string
    {
        $matched = [];

        if (preg_match("/'artisan'\s+(\S+)/", (string) $event->command, $matched) === 1) {
            return $matched[1];
        }

        return mb_substr($event->getSummaryForDisplay(), 0, 60);
    }

    /**
     * The commands that exited non-zero, said outside the table (9687).
     *
     * ⛔ **A LINE OF ITS OWN RATHER THAN THE COLUMN ALONE, AND THE REASON IS
     * WHICH ENTRIES THE TABLE CANNOT HOLD.** A row only appears above when the
     * entry has a **measured duration**, and the two failure shapes that
     * produce none are the two worst: a launch that threw before the command
     * started, and a background run whose start marker was lost or expired
     * (`ScheduledRunMeter`'s holes (1) and (2)). Reported only as a column,
     * those would be counted in the database, printed nowhere, and land in the
     * *"never had a run measured"* warning below — which reads as a quiet entry
     * rather than a broken one.
     *
     * ⚠️ **`error()` RATHER THAN `warn()`, WHICH IS THE DIFFERENCE BETWEEN THE
     * TWO WARNINGS BELOW AND THIS.** Those say a measurement is missing; this
     * says a command did not work.
     *
     * ⛔ **"ONE OF ONLY TWO PLACES THIS PLATFORM SAYS SO AT ALL … AND NEITHER IS
     * A BELL (9690)" WAS TRUE FOR ONE WAVE AND IS NOT — CORRECTED 2026-08-26
     * (9945–9959).** {@see OperatorAlertKind::ScheduledRunFailed}
     * is rung from {@see ScheduledRunMeter} at the moment of the failure, so a
     * failed entry now reaches an operator without anybody typing this command.
     *
     * ⚠️ **WHICH IS WHY THIS LINE IS NOT REDUNDANT AND ITS SENTENCE CHANGED
     * RATHER THAN ITS EXISTENCE.** The bell says *one* command failed, once per
     * `ScheduledRunMeter::FAILED_RUN_REPEAT_HOURS`; this says **how many times,
     * for every entry, over the whole retention window** — the difference
     * between *"that broke last night"* and *"that has broken every night for
     * three weeks"*, which is the fact a bell bounded by a repeat interval
     * structurally cannot carry.
     *
     * @param  array<string, array{runs: int, overruns: int, max_duration_ms: int|null, last_at: CarbonImmutable|null}>  $failed
     */
    private function reportFailures(array $failed): void
    {
        if ($failed === []) {
            return;
        }

        $named = [];

        foreach ($failed as $command => $seen) {
            $named[] = $command.' ('.$seen['runs'].')';
        }

        sort($named);

        $this->error(count($named).' scheduled command(s) exited non-zero or failed to launch in this '
            .'window, with how many times: '.implode(', ', $named).'. An operator was paged the '
            .'first time each of these failed and then not again about that command for '
            .app(ScheduledRunMeter::class)->failedRunRepeatHours().' hours, so these counts are larger than '
            .'the number of bells; the critical log lines from ScheduledRunMeter carry each one.');
    }

    /**
     * @param  list<string>  $unmeasured
     */
    private function reportUnmeasured(array $unmeasured): void
    {
        if ($unmeasured === []) {
            return;
        }

        sort($unmeasured);

        $this->warn(count($unmeasured).' scheduled command(s) have NEVER had a run measured, so their '
            .'overlap windows are still arguments rather than measurements (6975): '
            .implode(', ', $unmeasured));
    }

    /**
     * A command with measurements and no schedule entry — renamed, or dropped.
     *
     * ⚠️ **6961's SECOND ARM, IN A REPORT RATHER THAN A LINT.** A row that
     * outlives its entry would otherwise sit here for thirty days looking like
     * evidence about something that no longer runs.
     *
     * @param  list<string>  $orphans
     */
    private function reportOrphans(array $orphans): void
    {
        if ($orphans === []) {
            return;
        }

        sort($orphans);

        $this->warn('Measured but not on the schedule — renamed or removed within the retention window: '
            .implode(', ', $orphans));
    }

    private function readable(int $ms): string
    {
        if ($ms < 1_000) {
            return $ms.'ms';
        }

        $seconds = intdiv($ms, 1_000);

        if ($seconds < 120) {
            return $seconds.'s';
        }

        $minutes = intdiv($seconds, 60);

        return $minutes < 120
            ? $minutes.'m'
            : intdiv($minutes, 60).'h '.($minutes % 60).'m';
    }
}
