<?php

declare(strict_types=1);

use Cron\CronExpression;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\ManagesAttributes;
use Illuminate\Console\Scheduling\Schedule;

/*
|--------------------------------------------------------------------------
| Convention tests — the scheduler's overlap windows
|--------------------------------------------------------------------------
|
| Ported verbatim from goaiez-review-system on 2026-09-01, the day this
| application's routes/console.php received its schedule (it had carried only
| the heartbeat and one nightly command before that).
|
| ⛔ `withoutOverlapping()` WITH NO ARGUMENT IS A TWENTY-FOUR-HOUR LOCK. A run
| killed without releasing its mutex therefore blocks its successors for a full
| day: one skipped night on a nightly sweep, twenty-four blocked runs on an
| hourly task, and NINETY-SIX on each quarter-hourly sweep. Decisions 6960–6979
| gave every entry an argued window; these lints are what stop the argument
| being tidied away.
|
| ⚠️ WHY THE TABLE IS AN ENUMERATION AND NOT A RULE. A rule alone — "shorter
| than the default" — is satisfied by 1,439, and a rule that derives the number
| from the cadence cannot know how long the work takes. So the table below is
| the authority for each VALUE and the two property lints below bound what any
| value is allowed to be. A new schedule entry reddens this file until somebody
| has decided what a stranded lock may cost it; that is the whole mechanism.
|
| ⛔ THESE FAIL THE BUILD. Do not weaken one to get green.
|
*/

/**
 * The argued overlap window, in minutes, for every entry in `routes/console.php`.
 *
 * The classes, in one line each:
 *
 *   4         `ops:heartbeat`, every minute. Four blocked beats stay inside
 *             the fifteen-minute staleness threshold, so a stranded lock never
 *             pages somebody about a scheduler that is running.
 *   10–15     the five-minute watchers. Two intervals for the two that read
 *             counters; three for `support:poll-mailbox`, the only one that
 *             pages through a vendor's API.
 *   30        every quarter-hourly sweep. Two intervals — one interval races
 *             the next run, and these are the entries where the old default
 *             cost ninety-six runs.
 *   60–120    hourly. Two intervals, except `proof:recompute`, which 6848
 *             argued at sixty and which does no vendor work.
 *   180       nightly sweeps whose iterations are local database work.
 *   360       nightly sweeps whose iterations are a network round trip — and
 *             `billing:send-renewal-reminders`, for a different reason it
 *             states in its own block: the mutex is its only guard.
 *
 * @return array<string, int>
 */
function schedulingOverlapWindows(): array
{
    return [
        // Every minute.
        'ops:heartbeat' => 4,
        'queue:work' => 4, // the clone worker

        // Every five minutes.
        'pixel:watch-canary' => 10,
        'ops:watch-platform-health' => 10,
        'support:poll-mailbox' => 15,

        // Every fifteen minutes.
        'oauth:refresh-tokens' => 30,
        'reviews:reanalyse' => 30,
        'reviews:reinvite' => 30,
        'reviews:remind' => 30,
        'agent:nudge' => 30,
        'gbp:sync' => 30,
        'facebook:sync-reviews' => 30,
        'reviews:retry-stranded-replies' => 30,
        'campaigns:run-due' => 30,
        'messaging:watch-platform-complaint-rate' => 30,

        // Hourly.
        'numbers:health-rollup' => 120,
        'billing:advance-dunning' => 120,
        'billing:reconcile-purchases' => 120,
        'content:release-holds' => 120,
        'campaigns:reconcile-unknown' => 120,
        'disputes:check-deadlines' => 120,
        'proof:recompute' => 60,

        // Nightly, local work.
        'credits:reset-monthly' => 180,
        'tenants:execute-deletions' => 180,
        'credits:run-auto-top-ups' => 180,
        'gbp:revoke-owed-grants' => 180,
        'zernio:meter' => 180,
        'zernio:reconcile' => 180,
        'mail:prune-send-meter' => 180,
        'billing:send-trial-reminders' => 180,
        'audits:prune' => 180,
        'auth:prune-magic-links' => 180,
        'auth:prune-failed-sign-ins' => 180,
        'jobs:prune-failed' => 180,
        'numbers:return-parked' => 180,
        'numbers:recover-rested' => 180,
        'trials:prune-origins' => 180,
        'pixel:prune-rejects' => 180,
        'messaging:prune-sending-health' => 180,
        'trust:advance-first-week-paths' => 180,
        'warehouse:prune' => 180,
        'automation:prune-runs' => 180,
        'fetch:prune-attempts' => 180,
        'review-loss:prune-snapshots' => 180,
        'owner-channel:prune' => 180,
        'x211:detect-overdue' => 180,
        'x103:recommend-sites' => 180,
        'x199:mark-due' => 180,
        'x136:decay-signals' => 180,

        // Nightly, a network round trip per object, per location or per tenant.
        'exports:prune' => 360,
        'storage:prune' => 360,
        'gsc:sync' => 360,
        'visibility:sync-competitors' => 360,
        'content:self-audit' => 360,
        'actuation:measure-site-changes' => 360,
        'actuation:judge-speed-fixes' => 360,
        'billing:send-renewal-reminders' => 360,
        'owners:send-weekly-digest' => 360,
        'owners:send-monthly-site-digest' => 360,
    ];
}

/**
 * The most runs a stranded lock may block, whatever the cadence.
 *
 * ⚠️ **FOUR IS EXACTLY BINDING ON `ops:heartbeat` AND THAT IS DELIBERATE.** A
 * ceiling nothing reaches is a ceiling nobody has checked; this one reddens the
 * moment that entry's window moves or its cadence tightens.
 */
const SCHEDULING_MAX_BLOCKED_RUNS = 4;

/**
 * Every scheduled event, keyed by the artisan command it runs.
 *
 * @return array<string, Event>
 */
function schedulingEventsByCommand(): array
{
    $events = [];

    foreach (app(Schedule::class)->events() as $event) {
        // This application also schedules one closure — the `scheduler-heartbeat`
        // tick README-RUNTIME §265 requires and `app:deploy-check` reads. It runs
        // no artisan command, takes well under a second, and carries no mutex by
        // design, so the overlap-window lints do not apply to it.
        if ($event instanceof CallbackEvent) {
            continue;
        }

        $matched = [];

        // `'/path/to/php' 'artisan' some:command --flag` — the third token is
        // the signature, and `\S+` stops before any argument.
        expect(preg_match("/'artisan'\s+(\S+)/", (string) $event->command, $matched))
            ->toBe(1, 'A scheduled event does not look like an artisan command: '.$event->command);

        $events[$matched[1]] = $event;
    }

    return $events;
}

/**
 * How many minutes apart two consecutive runs of a cron expression are.
 *
 * Derived from the expression rather than from a second table, so that changing
 * an entry's cadence without revisiting its window reddens the build. A fixed
 * January base date in UTC keeps it clear of any daylight-saving fold.
 */
function schedulingIntervalMinutes(string $expression): int
{
    $cron = new CronExpression($expression);
    $base = new DateTime('2026-01-05 00:00:00', new DateTimeZone('UTC'));

    $first = $cron->getNextRunDate($base, 0, true);
    $second = $cron->getNextRunDate($first, 0, false);

    return intdiv($second->getTimestamp() - $first->getTimestamp(), 60);
}

/*
|--------------------------------------------------------------------------
| The fact the whole slice turns on
|--------------------------------------------------------------------------
*/

test("Laravel's bare overlap window is still a full day", function (): void {
    // Every number in this file is reasoned from `$expiresAt = 1440`. If a
    // future Laravel changes that default, "no window reaches 1,440" stops
    // being the guard it is written as.
    $default = (new ReflectionMethod(ManagesAttributes::class, 'withoutOverlapping'))
        ->getParameters()[0]
        ->getDefaultValue();

    expect($default)->toBe(1440);
});

/*
|--------------------------------------------------------------------------
| No entry may take the default
|--------------------------------------------------------------------------
*/

test('no entry in routes/console.php calls withoutOverlapping bare', function (): void {
    $source = (string) file_get_contents(base_path('routes/console.php'));

    $bare = [];

    foreach (explode("\n", $source) as $index => $line) {
        // Case-insensitive: PHP dispatches `->WithoutOverlapping()` to the same
        // 1,440-minute default.
        if (preg_match('/->withoutOverlapping\(\s*\)/i', $line) === 1) {
            $bare[] = 'line '.($index + 1);
        }
    }

    expect($bare)->toBe([], implode(', ', $bare).
        " call withoutOverlapping() with no argument, which is Laravel's ".
        '1,440-minute default: a run killed without releasing its mutex blocks '.
        'every successor for a full day. Give the entry an argued window and '.
        'add it to schedulingOverlapWindows() in this file (6960).');
});

test('every scheduled entry is guarded and none takes the 1,440-minute default', function (): void {
    foreach (schedulingEventsByCommand() as $command => $event) {
        expect($event->withoutOverlapping)
            ->toBeTrue("{$command} is scheduled without withoutOverlapping(). Every entry in ".
                'routes/console.php has one; if this command genuinely may run twice at once, '.
                'that is an argument to write down rather than an omission (6961).');

        // Strictly less than, so `withoutOverlapping(1440)` is refused as
        // loudly as the bare call.
        expect($event->expiresAt)
            ->toBeLessThan(1440, "{$command} has a 1,440-minute overlap window. That is the ".
                'default this convention exists to keep out of the file (6960).');
    }
});

/*
|--------------------------------------------------------------------------
| The window each entry actually receives, asserted
|--------------------------------------------------------------------------
*/

test('every scheduled entry has an argued window and receives exactly it', function (): void {
    $windows = schedulingOverlapWindows();
    $events = schedulingEventsByCommand();

    // Adding a command to routes/console.php without deciding what a stranded
    // lock may cost it reddens here, naming the command.
    $unargued = array_values(array_diff(array_keys($events), array_keys($windows)));

    expect($unargued)->toBe([], implode(', ', $unargued).
        ' is scheduled with no argued overlap window. Decide what a stranded '.
        'lock may cost this command — see the class notes on '.
        'schedulingOverlapWindows() — and add it there (6961).');

    // A removed command leaves a row here that would otherwise sit for ever
    // asserting nothing.
    $stale = array_values(array_diff(array_keys($windows), array_keys($events)));

    expect($stale)->toBe([], implode(', ', $stale).
        ' has an argued overlap window in this file and is not on the schedule. '
        .'Remove the row rather than leaving it to assert nothing (6961).');

    foreach ($windows as $command => $minutes) {
        expect($events[$command]->expiresAt)
            ->toBe($minutes, "{$command} guards its overlap with {$events[$command]->expiresAt} ".
                "minutes; {$minutes} is what was argued for it (6960–6979). Changing it means ".
                'changing both, which is the point.');
    }
});

/*
|--------------------------------------------------------------------------
| The property the values have to satisfy
|--------------------------------------------------------------------------
*/

test('no stranded lock blocks more than four runs of the thing it guards', function (): void {
    foreach (schedulingEventsByCommand() as $command => $event) {
        $interval = schedulingIntervalMinutes((string) $event->expression);

        expect($interval)->toBeGreaterThan(0, "{$command}'s cron expression yields no interval.");

        $blocked = (int) ceil($event->expiresAt / $interval);

        expect($blocked)
            ->toBeLessThanOrEqual(SCHEDULING_MAX_BLOCKED_RUNS,
                "{$command} runs every {$interval} minutes behind a {$event->expiresAt}-minute ".
                "lock, so one stranded mutex costs {$blocked} skipped runs. The ceiling is ".
                SCHEDULING_MAX_BLOCKED_RUNS.'. Either shorten the window or, if the command '.
                'genuinely needs that long to run, say so where the cadence is argued (6962).');
    }
});

/*
|--------------------------------------------------------------------------
| The exit code — whether this platform can find out that a command failed
|--------------------------------------------------------------------------
|
| Every entry that runs in the background sends everything the command prints
| to `/dev/null`, and the scheduler throws away its exit code. `ScheduledRunMeter`
| reads the code back off the one path the framework leaves open — the
| `schedule:finish` subprocess the shell wrapper runs — and the two lints below
| are the premises that path rests on.
|
*/

test('every background entry still hands its exit code to schedule:finish', function (): void {
    // `CommandBuilder::buildBackgroundCommand()` emits
    // `( <command> > /dev/null 2>&1 ; schedule:finish "<mutex>" "$?" ) &`
    // — the `;` runs it however the child died, and `"$?"` is the child's
    // real exit status.
    $background = [];

    foreach (schedulingEventsByCommand() as $command => $event) {
        if (! $event->runInBackground) {
            continue;
        }

        $background[] = $command;

        $built = (string) $event->buildCommand();

        // `str_contains()` into `toBeTrue()` rather than `toContain()`, because
        // Pest reads every argument to `toContain()` as another needle.
        expect(str_contains($built, 'schedule:finish'))
            ->toBeTrue("{$command} runs in the background and its built command does not invoke ".
                'schedule:finish, so nothing in this application would ever learn its exit '.
                'code (9681).');

        expect(str_contains($built, '"$?"'))
            ->toBeTrue("{$command} invokes schedule:finish without passing the child's exit ".
                'status, so every run of it reports success whatever the command returned (9681).');
    }

    // The anti-vacuity floor: a schedule with no background entries at all
    // would satisfy every assertion above by matching nothing.
    expect(count($background))->toBeGreaterThan(0,
        'No entry in routes/console.php runs in the background, so the assertions above matched '.
        'nothing. If that is a deliberate change, ScheduledRunMeter::recordExitCode()\'s whole '.
        'background argument needs revisiting rather than this floor lowering (9681).');
});

test("the scheduler still throws away a background entry's exit code", function (): void {
    // `ScheduleRunCommand::runEvent()` throws — and therefore dispatches
    // `ScheduledTaskFailed` — only when `$event->exitCode != 0 && ! $event->runInBackground`.
    // That gate is why a failed background command was invisible, and it is
    // also why `ScheduledRunMeter::failed()` may treat a null exit code as
    // "the launch itself threw". Source rather than behaviour, because this
    // harness cannot execute a scheduled event at all.
    $source = (string) file_get_contents(base_path(
        'vendor/laravel/framework/src/Illuminate/Console/Scheduling/ScheduleRunCommand.php'
    ));

    expect(str_contains($source, '$event->exitCode != 0 && ! $event->runInBackground'))
        ->toBeTrue('ScheduleRunCommand no longer gates its failure throw on ! runInBackground. '.
            'Read what it does now: if it throws for a background entry, ScheduledTaskFailed '.
            'fires in the parent with a null exit code, and ScheduledRunMeter would count that '.
            'run twice — once as a launch failure and once, in schedule:finish, as a non-zero '.
            'exit (9682).');
});
