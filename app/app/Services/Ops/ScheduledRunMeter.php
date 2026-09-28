<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Console\Commands\WatchPlatformHealth;
use App\Enums\OperatorAlertKind;
use App\Enums\PlatformHealthSignal;
use App\Enums\SignalState;
use App\Providers\AppServiceProvider;
use App\Services\Config\DefaultsRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * How long a scheduled command actually took — decision 6975's residual
 * (7080–7099).
 *
 * ## What was missing, in one sentence
 *
 * Wave 6 gave all thirty-five entries `routes/console.php` then held an argued
 * `withoutOverlapping()` window, and **every one of those numbers is reasoned
 * from what the command does rather than from what it has done**, because
 * nothing in this application has ever recorded a scheduled command's runtime:
 * no scheduler hooks, no framework listeners, `automation_runs` written only by
 * `AutopilotJob`, `platform_health_windows` carrying counters and no duration,
 * `ops:heartbeat` recording only *that* it happened, and
 * `ops:watch-platform-health` watching failures rather than slowness. So the one
 * failure that change can introduce — **a lock expiring under a still-running
 * process, so a second copy starts beside the first** — was the one failure
 * nothing here could report. It would have surfaced as the duplicate's own
 * symptom (6971's second statutory notice, 6970's raced pause) with no line
 * connecting it to a window.
 *
 * ## Two conditions, and why they are one bell
 *
 * ⚠️ **(a) A RUN THAT FINISHED HAVING OUTLIVED ITS OWN WINDOW.** Measured
 * duration ≥ `Event::$expiresAt` minutes. This is the one that fires *before*
 * anything is duplicated on a nightly entry, where the next run is not due for
 * another day and nothing else would ever notice.
 *
 * ⚠️ **(b) A RUN THAT STARTED WHILE A PREVIOUS ONE HAD NOT REPORTED
 * FINISHING.** The same finding one step later, with the second copy already
 * alive. It is what catches an entry whose overrun is invisible to (a) because
 * the process never finishes at all.
 *
 * ⛔ **(b)'s SENTENCE IS DELIBERATELY WEAKER THAN "TWO COPIES ARE RUNNING", AND
 * THE WEAKNESS IS HONEST RATHER THAN CAUTIOUS.** All this class knows is that a
 * previous launch left an open marker and nothing closed it. That is either two
 * live copies, or one copy whose process group died without running
 * `schedule:finish` — 6964's stranded-mutex case, which strands *this* marker by
 * exactly the same mechanism. **Both are the window failing to do what its
 * number says, both want the same action, and neither is distinguishable from
 * here**, so the alert names both rather than picking the dramatic one.
 *
 * ## Where each half is observed, because the framework splits them
 *
 * ⛔ **`ScheduledTaskFinished::$runtime` IS USELESS FOR THIRTY OF THE
 * THIRTY-FIVE ENTRIES AND LOOKS AUTHORITATIVE FOR ALL OF THEM.** Verified in
 * `vendor/` rather than recalled (255): `ScheduleRunCommand::runEvent()` times
 * `$event->run($this->laravel)`, and for a `runInBackground()` entry that call
 * returns the moment the child is *launched* — `Event::run()` skips its own
 * `finish()` when `runInBackground` is set. So the framework's runtime for a
 * six-hour nightly sweep is the twenty milliseconds it took to fork it. **A
 * meter built on that number alone would have reported every background entry as
 * instantaneous, passed every test, and been `sending_health_windows` with a
 * stopwatch on it** (2496–2499).
 *
 * What the background half is measured with instead is the pair
 * `ScheduledTaskFinished` (in `schedule:run`, at the moment of launch) and
 * `ScheduledBackgroundTaskFinished` (in the `schedule:finish` subprocess the
 * shell wrapper runs however the command died). ⚠️ **They are two different
 * processes**, so the start moment goes through the cache, keyed on the event's
 * own mutex name.
 *
 * ⚠️ **`ScheduledBackgroundTaskFinished` CARRIES NO RUNTIME AT ALL** — it is
 * `public Event $task` and nothing else — which is why the pair exists rather
 * than a second read of a framework figure.
 *
 * ## The three holes, stated rather than left to be found
 *
 * ⚠️ **(1) ONCE TWO COPIES ARE GENUINELY OVERLAPPING, THE DURATIONS STOP BEING
 * ATTRIBUTABLE.** The marker is keyed on the mutex name, which is all
 * `schedule:finish` knows, so two live copies of one entry share one key. The
 * marker is therefore **never overwritten while it is open** — the *first*,
 * longest-running copy keeps it and is the one measured, and the second copy's
 * finish finds nothing and records nothing. That biases the record toward the
 * long run, which is the one worth having; it also means a run count can be
 * short by one in exactly the hour something went wrong. **(b) has already rung
 * by then.**
 *
 * ⚠️ **(2) A RUN LONGER THAN ITS MARKER'S TTL IS NOT MEASURED.** The TTL is four
 * times the entry's own window — 6962's constant, for 6962's reason — so a run
 * has to outlive its lock four times over to fall out. Nothing is recorded when
 * that happens, and the honest reason is that a marker that outlived a whole day
 * would blind the entry rather than inform it.
 *
 * ⚠️ **(3) IT MEASURES DURATION AND NOT SUCCESS.** `failures` on
 * {@see PlatformHealthSignal::ScheduledRun} means *overran its window* and
 * never *exited non-zero*, and **that half is unchanged and is deliberate** —
 * 9371's rule, the honest counter is levelled up rather than down. What was
 * wrong with this hole was the sentence that followed it.
 *
 * ⛔ **"A SCHEDULED COMMAND THAT FAILS IN THE BACKGROUND IS STILL REPORTED
 * NOWHERE" WAS TRUE AND IS NOT — CORRECTED 2026-08-25 (9680–9686). BOTH
 * READINGS ARE KEPT AND DATED, BECAUSE THE OLD ONE IS THE MEASUREMENT AND THE
 * NEW ONE IS ONLY THE FIX.** The superseded text ran:
 *
 * > *"because a background entry's exit code is never dispatched to the parent
 * > at all (`ScheduleRunCommand` gates that throw on `! runInBackground`). A
 * > scheduled command that fails in the background is still reported nowhere …
 * > `ScheduleRunCommand::runEvent()` dispatches `ScheduledTaskFinished`
 * > **first**, and only then checks `$event->exitCode != 0 &&
 * > ! $event->runInBackground` and throws. So for a foreground entry the order
 * > is finish, then fail — {@see self::finished()} has already called
 * > {@see self::record()} by the time {@see self::failed()} is reached, and a
 * > command that crashed a hundred milliseconds in is written down as an
 * > ordinary completed run: `total + 1`, `failures + 0`, a duration of ~100ms.
 * > **It does not read as silence on `ops:schedule-runtimes`. It reads as the
 * > fastest, healthiest entry on the platform**, and the faster it dies the
 * > healthier it looks. … ⛔ **AND THE COUNTER IS NOT THE ANSWER** … the parent
 * > never learns a background entry's exit code, so folding crashes into that
 * > column would report a failure rate for the handful of foreground entries
 * > and silence for every background one — a partial denominator, which is the
 * > rule `SendingHealth` (3032) exists to state."*
 *
 * ⛔ **EVERY CLAUSE ABOUT THE PARENT IS TRUE AND THE CONCLUSION DOES NOT
 * FOLLOW, BECAUSE THE CODE IS NOT READ IN THE PARENT.** The shell wrapper
 * `CommandBuilder::buildBackgroundCommand()` emits ends
 * `; schedule:finish "<mutex>" "$?"`, and `ScheduleFinishCommand` calls
 * `Event::finish($app, $code)` — **which assigns `$this->exitCode`** — before
 * dispatching `ScheduledBackgroundTaskFinished`. **Every background entry
 * hands this class its real exit code**, in the `schedule:finish` process,
 * however the child died. There was never a partial denominator to refuse.
 * {@see self::recordExitCode()} is where that is read and
 * {@see PlatformHealthSignal::ScheduledRunFailed} is where it is written, so
 * hole (3) is **closed for the whole schedule** rather than for the foreground
 * handful.
 *
 * ⛔ **THE COVERAGE IS A PROPERTY AND IS DELIBERATELY NOT A COUNT — CORRECTED
 * 2026-08-28 (11261).** This paragraph read *"all thirty-nine background
 * entries"* and *"closed for all forty-four entries"*; the schedule holds
 * **51** (46 background, 5 foreground) as this is written, and it will hold
 * something else by the time it is next read. ⚠️ **A number here reads as a
 * coverage claim and is not one** — both figures were stale while the
 * coverage they described was complete throughout, which is the worst
 * direction for a stale denominator: it invites a reader to go looking for
 * the seven entries nobody is watching, and there are none.
 * ✅ **What makes the coverage total is structural and checkable in one
 * place**: `AppServiceProvider::meterScheduledRuns()` registers four
 * listeners on the framework's OWN scheduler events —
 * `ScheduledTaskStarting`, `ScheduledTaskFinished`,
 * `ScheduledBackgroundTaskFinished`, `ScheduledTaskFailed` — with **no
 * per-entry opt-in and no filter of any kind**, so an entry added to
 * `routes/console.php` tomorrow is heard without anybody editing anything.
 * ⛔ **The thing that would break coverage is therefore a narrowed listener,
 * never a grown schedule**, and {@see self::failedRunCoverage()}'s
 * operator-facing sentence already states it the same way — *"it hears every
 * entry on the schedule"* — with no figure in it.
 *
 * ⚠️ **THE DIAGNOSIS THE OLD PARAGRAPH GAVE FOR THE FOREGROUND FIVE IS EXACT
 * AND STILL DESCRIBES WHAT `record()` DOES**: a crash at 100ms is still written
 * as `total + 1, failures + 0, 100ms` on `ScheduledRun`. What changed is that a
 * second row now says it failed, so the two readings no longer contradict each
 * other silently — and `ops:schedule-runtimes` prints both.
 *
 * ⛔ **"THE BELL IS STILL OWED AND IS STILL DELIBERATELY NOT MINTED HERE" WAS
 * TRUE FOR ONE WAVE AND IS NOT — CORRECTED 2026-08-26 (9945–9959). BOTH
 * READINGS ARE KEPT AND DATED** (4368). The superseded text ran: *"(7023,
 * restated at 9690): it needs an `OperatorAlertKind` case and a row on the
 * alert board, and both of those files belong to another lane this wave. A case
 * whose raiser lands in a different slice is the half-wired shape 7023 refuses.
 * **So what a failed scheduled command reaches today is a counter, a `critical`
 * log line and nothing that pages anybody** — a command that dies fast every
 * five minutes for ever still wakes nobody, and 9690 says exactly what closing
 * that takes."*
 *
 * ✅ **THE CASE, THE RAISER AND THE BOARD ROW LANDED IN ONE COMMIT**, which is
 * what 7023 was holding out for. {@see OperatorAlertKind::ScheduledRunFailed}
 * is rung from {@see self::writeFailedRun()} — so from both shapes, a non-zero
 * exit and a launch that threw — bounded by
 * {@see self::FAILED_RUN_REPEAT_HOURS} rather than by a threshold, because a
 * count of failures is a count of traffic and a stopped nightly sweep produces
 * one row a night against any figure anybody would set (9370–9375, 9690).
 *
 * ⚠️ **WHAT SURVIVES OF THE OLD PARAGRAPH IS ITS LIMIT, AND IT SURVIVES
 * UNCHANGED**: nothing here can ring about a scheduler that is not running,
 * because every one of these is raised from a framework scheduler event. **Cron
 * not firing `schedule:run` produces silence rather than a bell**, and quiet is
 * not evidence that the schedule ran.
 *
 * ## R25 — a bell, never a brake
 *
 * ⛔ **EVERY PUBLIC METHOD HERE RUNS INSIDE `schedule:run`, WHICH MEANS INSIDE
 * THE THING IT IS MEASURING.** A throw would take down the scheduler tick that
 * dispatches the campaign passes, the dunning ticks and the renewal notices —
 * the platform stopped by its own instrumentation, which is the worst version of
 * the failure this feature exists to report. So every entry point is wrapped,
 * `PlatformHealth` already swallows its own writes, and `OperatorAlerts::raise()`
 * already returns rather than throws. **The cost is stated rather than hidden**:
 * a cache store that cannot be reached produces a meter that records nothing,
 * which reads as a platform whose commands all finish instantly.
 *
 * @see AppServiceProvider::meterScheduledRuns() for the wiring
 */
final class ScheduledRunMeter
{
    /**
     * ⚠️ **PREFIXED AND SEPARATOR-FLATTENED.** `Event::mutexName()` is
     * `framework/schedule-<sha1>`, and a cache key carrying a directory
     * separator is a file path on the `file` store. The sha1 is kept because it
     * is what both processes can agree on.
     */
    private const string KEY_PREFIX = 'ops:schedule-run-open:';

    /**
     * How long {@see OperatorAlertKind::ScheduledRunFailed} stays quiet about
     * one command after ringing about it — 9690's clause, built at 9945–9959.
     *
     * ## ⛔ Under a day, and the boundary is the whole reason for the figure
     *
     * ⛔ **THIRTY DAYS WAS THE SHAPE THIS WAS COPIED FROM AND IS THE WRONG
     * ANSWER HERE, WHICH 9690 SAID BEFORE THE CODE EXISTED.**
     * `PlatformHealthChecks::CREDENTIAL_REPEAT_DAYS` is thirty because an absent
     * credential is one thing a person pastes once. **A scheduled entry is a
     * clock**: a broken nightly sweep is a fresh failure every night, and a
     * month of quiet after the first would be the bell speaking on night one and
     * on none of the other twenty-nine — silence that reads exactly like the
     * problem having stopped.
     *
     * ⛔ **AND TWENTY-FOUR IS WRONG FOR A REASON THAT ONLY APPLIES TO THIS
     * KIND**, which is why the two siblings' figure is not inherited.
     * `OperatorAlerts::MAIL_PATH_REPEAT_HOURS` and
     * `AutopilotJob::ABANDONED_REPEAT_HOURS` are both a day over raisers with no
     * fixed clock. **The dominant population here is a nightly cron entry that
     * fires at the same wall-clock minute**, so at exactly twenty-four hours
     * {@see OperatorAlerts::rangSince()}'s `>=` catches the second night's
     * failure and the bell rings every *other* night — a coin flip decided by
     * whether tonight's tick started a second earlier or a second later than
     * last night's. **Twenty leaves four hours of slack against that boundary**
     * and is the largest figure that clears it with room for a tick that starts
     * late.
     *
     * ⚠️ **WHAT IT COSTS AT THE OTHER END IS STATED RATHER THAN GLOSSED.** A
     * five-minute entry that is broken all day rings about once every twenty
     * hours rather than once a day, which is a handful of pushes and is well
     * inside `OperatorAlerts::PUSH_BUDGET_PER_KIND` for one subject. ⚠️ **Ten
     * entries broken at once will spend the kind's whole daily allowance**, and
     * that is the intended behaviour rather than an oversight: at ten broken
     * entries the finding is *the scheduler is broadly broken*, and
     * {@see OperatorAlertKind::PagerBudgetSpent} is the bell that says the pager
     * has gone deaf on this kind rather than the problem having gone away.
     *
     * ⚠️ **PUBLIC BECAUSE THE ALERT BOARD PRINTS IT** — 7661's rule, learned
     * from a clamp copied into a screen that then went stale: ask the class that
     * applies the figure, never repeat its arithmetic.
     */
    public const int FAILED_RUN_REPEAT_HOURS = 20;

    /**
     * Whether *this* process opened the marker for a mutex, keyed on mutex name.
     *
     * ⚠️ **IN MEMORY AND DELIBERATELY NOT IN THE CACHE.** It is a fact about one
     * `schedule:run` invocation — "did my `add()` win" — and it is read a few
     * microseconds later in the same process. Putting it in the shared store
     * would make two concurrent scheduler ticks overwrite each other's answer.
     *
     * @var array<string, bool>
     */
    private array $weOpened = [];

    /**
     * The start moment, in epoch milliseconds, of a marker that was **already**
     * open when this process launched the entry.
     *
     * @var array<string, int>
     */
    private array $foundOpenMs = [];

    public function __construct(
        private readonly PlatformHealth $health,
        private readonly OperatorAlerts $alerts,
        private readonly Cache $cache,
        private readonly DefaultsRegistry $registry = new DefaultsRegistry,
    ) {}

    public function failedRunRepeatHours(): int
    {
        return $this->registry->int('ops.runs.failed_run_repeat_hours');
    }

    /**
     * The scheduler is about to call `Event::run()`.
     *
     * ⚠️ **THE MARKER IS OPENED HERE AND NOT AT `ScheduledTaskFinished`, WHICH
     * COSTS ONE BRANCH AND BUYS THE FOREGROUND HALF OF (b).** `ScheduledTaskFinished`
     * fires *after* a foreground command has already finished, so a marker
     * opened there could never be seen by an overlapping tick — and an
     * overlapping tick is precisely what a foreground entry produces when the
     * cron line has no `flock` around `schedule:run` (7085). The branch is that
     * this fires before the framework has decided whether the run is skipped;
     * {@see self::finished()} takes the marker back when it was.
     */
    public function starting(Event $task): void
    {
        $this->guard(function () use ($task): void {
            $mutex = $task->mutexName();
            $key = $this->key($mutex);

            // `add()` rather than read-then-write: it is the store's own atomic
            // "only if absent", and it is what makes two scheduler ticks racing
            // in the same second resolve to one opener rather than two.
            if ($this->cache->add($key, $this->nowMs(), $this->markerTtlSeconds($task))) {
                $this->weOpened[$mutex] = true;

                return;
            }

            $this->weOpened[$mutex] = false;

            $open = $this->cache->get($key);

            if (is_int($open)) {
                $this->foundOpenMs[$mutex] = $open;
            }
        });
    }

    /**
     * `Event::run()` has returned — which for a background entry means the child
     * was launched, and for a foreground entry means the work is done.
     */
    public function finished(Event $task, float $runtimeSeconds): void
    {
        $this->guard(function () use ($task, $runtimeSeconds): void {
            $mutex = $task->mutexName();
            $weOpened = $this->weOpened[$mutex] ?? false;
            $foundOpenMs = $this->foundOpenMs[$mutex] ?? null;

            unset($this->weOpened[$mutex], $this->foundOpenMs[$mutex]);

            // ⛔ **A BLOCKED RUN IS NOT A RUN AND MUST NOT BE MEASURED AS ONE.**
            // `ScheduleRunCommand` dispatches `ScheduledTaskFinished` for an
            // entry the mutex refused, with a runtime of a fraction of a
            // millisecond. Recording those would fill `max_duration_ms` with
            // zeroes drawn from the very minutes an entry was wedged — a meter
            // reading healthiest exactly when it should read worst.
            if ($task->skippedBecauseOverlapping) {
                if ($weOpened) {
                    $this->cache->forget($this->key($mutex));
                }

                return;
            }

            if ($foundOpenMs !== null) {
                $this->raiseConcurrentLaunch($task, max(0, $this->nowMs() - $foundOpenMs));
            }

            // The child is still working and its marker stays open until
            // `schedule:finish` closes it in the other process.
            if ($task->runInBackground) {
                return;
            }

            // ⛔ **BEFORE `record()`, BECAUSE `record()` IS THE THING THAT USED
            // TO WRITE THIS RUN DOWN AS HEALTHY.** For a foreground entry
            // `Event::run()` has already called `finish()`, so `exitCode` is
            // assigned by the time this event is dispatched — see
            // {@see self::recordExitCode()}.
            $this->recordExitCode($task);

            if ($weOpened) {
                $this->cache->forget($this->key($mutex));
            }

            $this->record($task, (int) round($runtimeSeconds * 1000));
        });
    }

    /**
     * `schedule:finish` ran for a background entry — the only moment its true
     * duration can be known.
     */
    public function backgroundFinished(Event $task): void
    {
        $this->guard(function () use ($task): void {
            // ⛔ **BEFORE THE MARKER IS READ, AND THE ORDER IS THE WHOLE
            // PROPERTY.** The early return below is holes (1) and (2) — a lost
            // or expired start marker, which costs a *duration*. An exit code
            // costs nothing to know and needs no start moment, so putting this
            // after the return would lose the failure in exactly the runs that
            // went worst: the overlapping second copy and the run that outlived
            // its own marker.
            $this->recordExitCode($task);

            $key = $this->key($task->mutexName());
            $open = $this->cache->get($key);

            // Hole (1) or (2) in the class docblock: the second of two
            // overlapping copies, or a run that outlived its marker. Silent
            // rather than guessed — a duration invented from a missing start is
            // worse than a gap in the record.
            if (! is_int($open)) {
                return;
            }

            $this->cache->forget($key);

            $this->record($task, max(0, $this->nowMs() - $open));
        });
    }

    /**
     * `Event::run()` threw, so no finish of any kind is coming.
     *
     * ⛔ **WITHOUT THIS THE MARKER LEAKS AND THE BELL LIES.** A launch that
     * throws — an unreachable mutex store, a `config:cache` the child cannot
     * read — leaves an open marker that nothing closes, and the next launch
     * would find it and raise (b) about a run that never started. The marker is
     * taken back only when this process opened it.
     */
    public function failed(Event $task): void
    {
        $this->guard(function () use ($task): void {
            $this->recordLaunchFailure($task);

            $mutex = $task->mutexName();

            if ($this->weOpened[$mutex] ?? false) {
                $this->cache->forget($this->key($mutex));
            }

            unset($this->weOpened[$mutex], $this->foundOpenMs[$mutex]);
        });
    }

    /**
     * (a)'s sentence, composed where a test can drive it at any command name
     * and any duration (9274).
     *
     * ⛔ **`public static` ON `ReconcileZernioAccounts::summarise()`'s
     * PRECEDENT, AND FOR ITS REASON.** That is the only summary in this tree
     * written knowing about `operator_alerts.summary`'s 300 characters, and the
     * thing that makes its headroom checkable rather than argued is that a test
     * can call it directly with figures no fixture would produce. A sentence
     * built inline inside a private method can only be measured through
     * whichever event a fixture happened to pick — which is exactly how (b)
     * below came to overrun the column on **every** possible firing with a green
     * suite over it.
     *
     * ⚠️ **THE ACTION IS THE LAST SENTENCE, DELIBERATELY.**
     * {@see OperatorAlerts::clamp()} elides the middle and keeps the closing
     * sentence, so the clause {@see OperatorAlertKind::ScheduledRunOverranLock}
     * calls the whole point of this bell is the one part a cut cannot reach.
     */
    public static function overranSummary(string $command, int $durationMs, int $windowMinutes): string
    {
        return "{$command} ran for ".self::readable($durationMs)
            .', and its overlap lock expires after '.self::readableMinutes($windowMinutes)
            .'. The lock was free while the command was still working, so the next run can start a '
            .'second copy beside it. Widen its window in routes/console.php or make it faster.';
    }

    /**
     * (b)'s sentence — **the one that did not fit** (9274).
     *
     * ## What was wrong with it, in one figure
     *
     * ⛔ **293 CHARACTERS OF FIXED TEXT IN A 300-CHARACTER COLUMN.** That left
     * seven for a command name, a duration and a window put together, and the
     * shortest name on this schedule is eight — so the sentence was over the
     * column on **every possible firing**, from the day it shipped, and what
     * `mb_substr()` took off the end was the whole of *"Widen the window in
     * routes/console.php or make the command faster."*, mid-word. Measured on
     * 2026-08-25 across the forty-three entries the schedule then held: 309
     * characters at the shortest and 340 at the longest. ⚠️ **The figure is
     * dated because it is a measurement and not a coverage claim** — the
     * schedule has grown since, and the sentence was over the column on every
     * one of them.
     *
     * ⚠️ **AND ITS OWN TEST WAS GREEN THROUGHOUT**, because the phrase that test
     * looks for sits around character 215 — comfortably inside what survived.
     * *A test can pass for the wrong reason* (`CLAUDE.md`), in the test written
     * to cover the bell.
     *
     * ## What was dropped to make it fit, and why that one
     *
     * ⛔ **THE WINDOW CLAUSE, BECAUSE A FIGURE SURVIVES AND PROSE DOES NOT.**
     * `window_minutes` reaches `context`, the `critical` log line and the alert
     * board; the sentence reaches a text message and nothing else. Cutting the
     * clause costs an operator a number they can read in three other places and
     * buys back forty-six characters of the clause that is stored nowhere.
     * ⚠️ **The disjunction is NOT shortened away** — the class docblock argues
     * at length that *"two copies are running"* would be a stronger claim than
     * this class can make, and that weakness is the honest half.
     *
     * ⚠️ **THE HEADROOM IS NOW BOUNDED BY CONSTRUCTION RATHER THAN BY THE
     * SCHEDULE.** {@see self::commandName()} clamps to sixty characters and
     * {@see self::markerTtlSeconds()} caps how long a marker can be found open
     * at a day, so the widest this sentence can ever render is 289 — eleven
     * inside the column with no command name on the schedule involved.
     * `ScheduledRunMeterTest` asserts both that ceiling and every real entry.
     */
    public static function concurrentLaunchSummary(string $command, int $openForMs): string
    {
        return "{$command} started again while an earlier run had been open ".self::readable($openForMs)
            .' and had not reported finishing. Two copies may be running now, or the earlier one '
            .'died without releasing its lock. Widen its window in routes/console.php or make it faster.';
    }

    /**
     * A scheduled command exited non-zero — the one thing this class could not
     * previously say (9680–9686).
     *
     * ## Where the code comes from, because the obvious reading is wrong
     *
     * ⛔ **`ScheduleRunCommand` DISCARDS IT FOR THIRTY-NINE OF THE FORTY-FOUR
     * ENTRIES AND THAT IS NOT WHERE WE READ IT.** Its throw is gated on
     * `$event->exitCode != 0 && ! $event->runInBackground`, so no
     * `ScheduledTaskFailed` is ever dispatched for a background entry and the
     * exception handler never sees one. **Both of the child's streams go to
     * `/dev/null` as well** — `Event::getDefaultOutput()`, and the cron line in
     * `.claude/skills/deploying/` redirects the parent's too — so before this
     * method a failed background command left no trace anywhere in this
     * application.
     *
     * ✅ **What the framework does instead is hand the code to a second
     * process.** `CommandBuilder::buildBackgroundCommand()` emits
     * `( <command> > /dev/null 2>&1 ; schedule:finish "<mutex>" "$?" ) &`, and
     * the `;` runs `schedule:finish` however the child died —
     * `ScheduleFinishCommand` then calls `Event::finish($app, $code)`, which
     * **assigns `$this->exitCode`**, and only afterwards dispatches
     * `ScheduledBackgroundTaskFinished`. So {@see self::backgroundFinished()} is
     * handed the real code. ⚠️ **Read in `vendor/` on 2026-08-25 rather than
     * recalled** (255), and pinned by `SchedulingTest` against the live
     * schedule, because if a future Laravel drops either half this method goes
     * silently vacuous and the meter goes back to reading healthy.
     *
     * ⚠️ **THE FOREGROUND FIVE ARE THE SAME FACT ONE STEP EARLIER.**
     * `Event::run()` calls `finish()` itself when `runInBackground` is unset, so
     * `exitCode` is already assigned when `ScheduledTaskFinished` is dispatched
     * — which is *before* `ScheduleRunCommand` throws.
     *
     * ## What it writes, and what it deliberately does not
     *
     * ⚠️ **{@see PlatformHealthSignal::ScheduledRunFailed} AND NOT
     * {@see PlatformHealthSignal::ScheduledRun}'s `failures`**, which means
     * *overran its own window* and goes on meaning exactly that. 9371's rule:
     * the honest counter is levelled up, never down.
     *
     * ⛔ **"AND NO BELL" WAS TRUE FOR ONE WAVE AND IS NOT — CORRECTED
     * 2026-08-26 (9945–9959).** The superseded text ran: *"which is 7023's
     * refusal restated rather than forgotten. A bell needs an
     * `OperatorAlertKind` case and a board row, and both of those files belong
     * to another lane this wave; a case whose raiser lands in a different slice
     * is the half-wired shape 7023 refuses. **So a failed scheduled command
     * reaches a counter and a `critical` log line and nothing that pages
     * anybody**, and 9690 says what the bell would take."* ✅ **It reaches an
     * operator now** — {@see self::writeFailedRun()} rings
     * {@see OperatorAlertKind::ScheduledRunFailed} after writing both, in that
     * order and for a reason stated there.
     *
     * ⚠️ **THE LOG LINE IS THE ONLY IMMEDIATE TRACE FOR EVERY BACKGROUND ENTRY
     * AND IS A DUPLICATE FOR EVERY FOREGROUND ONE.** The foreground half
     * already produces an `error` from `ScheduleRunCommand`'s own handler
     * report; this line is written anyway, because a trace that exists for the
     * foreground handful and not for the background majority is the asymmetry
     * that made this defect invisible in the first place. ⚠️ **The split is
     * named by its property rather than by its two counts** — they were
     * *thirty-nine* and *five* here and the schedule now runs **46 and 5**,
     * and nothing about this argument moves when it grows again (11261).
     *
     * ⚠️ **A ZERO CODE AND A `null` CODE ARE BOTH SILENCE, AND THEY ARE NOT THE
     * SAME SILENCE.** `null` is a run that never reported one — a launch the
     * mutex refused, or the `ScheduledTaskFinished` of a background entry, whose
     * code arrives later in the other process. Neither is a failure and neither
     * may be guessed at.
     */
    private function recordExitCode(Event $task): void
    {
        $exitCode = $task->exitCode;

        if ($exitCode === null || $exitCode === 0) {
            return;
        }

        $this->writeFailedRun($task, $exitCode);
    }

    /**
     * The launch itself threw, so there is no exit code and there never will be
     * one (9684).
     *
     * ⚠️ **A DIFFERENT FAILURE FROM A NON-ZERO EXIT AND RECORDED UNDER THE SAME
     * SIGNAL, DELIBERATELY.** `Event::run()` throwing means the command did not
     * run at all — an unreachable mutex store, a `Process` that could not be
     * started — and the question the counter answers is *"did that scheduled
     * command work"*, to which both answers are no. The `exit_code` on the log
     * line is what tells them apart, and it is `null` here rather than a number
     * nobody produced.
     *
     * ⛔ **THE GUARD IS WHAT STOPS IT DOUBLE-COUNTING THE FOREGROUND FIVE.**
     * `ScheduleRunCommand` dispatches `ScheduledTaskFinished` **and then**
     * throws on a foreground non-zero exit, so both events fire for one run —
     * {@see self::recordExitCode()} has already written it, and `exitCode` is
     * assigned by then, which is exactly the condition this refuses.
     */
    private function recordLaunchFailure(Event $task): void
    {
        if ($task->exitCode !== null) {
            return;
        }

        $this->writeFailedRun($task, null);
    }

    /**
     * What the alert board says about this bell's arming — the check answering
     * for itself (10020–10025).
     *
     * ## ⛔ Why this is not `OperatorAlertBoard::switched()`, which is lane C's
     * finding arriving one arm over
     *
     * ⛔ **`switched()` IS RIGHT FOR A CHECK THAT READS A COUNTER AND WRONG FOR
     * ONE THAT DEPENDS ON SOMETHING HAVING HAPPENED AT ALL** (9930–9944). Every
     * threshold row on that screen reads a counter somebody else's code is
     * already writing, so a non-zero figure genuinely is the whole of its
     * arming. **This bell has no threshold at all**, so it fell through to the
     * generic row — `watched: true`, *"Rings from the work it watches"* — which
     * is a claim that it is armed, made by **default rather than by decision**.
     *
     * ⛔ **AND ON AN INSTALL WHOSE `schedule:run` CRON LINE WAS NEVER ADDED,
     * THAT CLAIM IS FALSE IN THE WORST AVAILABLE DIRECTION.** Every raise here
     * comes from a framework scheduler event, so no cron means no events, means
     * `writeFailedRun()` is unreachable, means this bell has **never been able
     * to ring** — while the screen whose entire job is telling an operator which
     * bells are armed says it is watching. `.claude/skills/deploying/` records
     * that two cron entries are required and that **nothing in this repository
     * writes them** (416), so that is a documented failure mode of this
     * application's own deploy procedure rather than a hypothetical.
     *
     * ⚠️ **AND IT IS SHARPER HERE THAN AT THE THREE NO-THRESHOLD BELLS THIS ROW
     * WAS MODELLED ON.** `PlatformMailUndeliverable`, `AutomationAbandoned` and
     * `PixelArchiveFailed` all hang off work that **product usage** triggers —
     * a sign-in, a tenant's customers, a visitor loading a page — so on an idle
     * install their silence really is the absence of a fault. **The work this
     * one watches is triggered by infrastructure we do not install**, so its
     * silence is equally consistent with a deployment defect that this is the
     * instrument for.
     *
     * ## ⛔ The sentence is NOT lane C's, and the difference is the data rather
     * than the wording
     *
     * ⛔ **"WAITING FOR A FIRST BEAT" IS A DISTINCTION THEIR DATA CAN MAKE AND
     * MINE CANNOT, SO BORROWING IT WOULD BE IMPORTING A CLAIM ALONG WITH A
     * PATTERN.** 9934 made the newest heartbeat row survive `prune()` precisely
     * so that *"dead for five weeks"* stays distinguishable from *"never
     * started"* — **a counter may be pruned, a state may not**. These are
     * counters and they **are** pruned, at
     * {@see WatchPlatformHealth::KEEP_DAYS}. So a platform that ran happily for
     * a year and stopped thirty-one days ago is indistinguishable from a fresh
     * install, and the two readings are **stated** rather than resolved:
     * the sentence reports the observation and names both, and picks neither.
     * ⚠️ **Making the row survive the prune is the other fix and it belongs to
     * whoever owns `PlatformHealth::prune()`** — see 10022.
     *
     * ## ⛔ Both signals, because the worst state writes only one of them
     *
     * ⛔ **A PLATFORM WHOSE EVERY SCHEDULED RUN FAILED HAS ROWS ON
     * {@see PlatformHealthSignal::ScheduledRunFailed} AND NONE ON
     * {@see PlatformHealthSignal::ScheduledRun}.** {@see self::record()} needs a
     * measured duration and a background run whose start marker was lost or
     * expired produces none — which is exactly what
     * `ScheduledExitCodeTest`'s first test asserts. **Asking only about the
     * duration counter would render *"nothing has reported"* over the one state
     * this bell exists to scream about**, so both are asked and either answers.
     *
     * @return array{watched: bool, state: SignalState, status: string, detail: string}
     */
    public function failedRunCoverage(): array
    {
        $armed = 'Rings the first time a scheduled command exits non-zero or cannot be started at '
            .'all, and then not again about that command for '.$this->failedRunRepeatHours()
            .' hours. It has no threshold and no off switch: a nightly sweep that has stopped '
            .'produces one failure a night, which is below any figure anybody could set. It hears '
            .'every entry on the schedule, including the ones whose own output is discarded, where '
            .'a failed command used to leave no trace at all. Nor can it tell a command that did '
            .'its work from one that ran and decided nothing.';

        if ($this->schedulerHasReported()) {
            return [
                'watched' => true,
                'state' => SignalState::Ok,
                'status' => 'Watching',
                'detail' => $armed,
            ];
        }

        // ⚠️ **`watched: false`, ON `heartbeatCoverage()`'s PRECEDENT AND FOR
        // ITS REASON.** The honest answer to *"is this bell armed"* here is no:
        // nothing has reached the code that rings it, and a `true` beside an
        // `Unknown` would be the same over-claim in a second column.
        return [
            'watched' => false,
            'state' => SignalState::Unknown,
            'status' => 'No scheduled command has reported',
            'detail' => 'Nothing has recorded a scheduled command running or failing in the last '
                .WatchPlatformHealth::KEEP_DAYS.' days, so this bell has had nothing to watch and '
                .'has never been able to ring. That is either an install whose scheduler cron line '
                .'was never added — nothing in the application writes one, and the deploy notes '
                .'carry both entries — or a scheduler that stopped at least that long ago, and '
                .'this screen cannot tell those apart because the counters behind it are deleted '
                .'at that age. Check the crontab on the server before reading quiet here as '
                .'nothing having failed. It arms itself on the first run either way.',
        ];
    }

    /**
     * Whether anything on this install has ever reported a scheduled run, over
     * the whole window the counters are kept for.
     *
     * ⚠️ **THE HORIZON IS THE RETENTION AND NOT A JUDGEMENT.** Asking over a
     * shorter window would make a working platform's row flicker to *"nothing
     * has reported"* during any quiet stretch; asking over a longer one returns
     * the same answer, because {@see WatchPlatformHealth::KEEP_DAYS} is where
     * the rows stop existing.
     *
     * ## ⛔ Why this is `totals()` and not `durationsBySource()`, measured
     *
     * ⛔ **IT SHIPPED AS `durationsBySource()` FOR AN HOUR BECAUSE REUSING THE
     * METHOD `ReportScheduleRuntimes` ALREADY USES LOOKED LIKE THE DISCIPLINED
     * CHOICE, AND IT COSTS EIGHT MILLISECONDS TO ANSWER A BOOLEAN.** Measured
     * with `EXPLAIN (ANALYZE, BUFFERS)` against the shape the live schedule
     * actually produces — 44 sources, hourly buckets, thirty days, **31,680
     * rows** — on 2026-08-26, five runs, medians:
     *
     * - `GROUP BY source` ({@see PlatformHealth::durationsBySource()}) — **8.420 ms**
     * - `SELECT DISTINCT source` ({@see PlatformHealth::sources()}) — **3.610 ms**
     * - single-row `SUM` ({@see PlatformHealth::totals()}) — **2.746 ms**
     * - `… LIMIT 1` — **0.008 ms**
     *
     * ⚠️ **`totals()` IS THE CHEAPEST READ THIS CLASS CAN MAKE THROUGH
     * {@see PlatformHealth}'s PUBLIC SURFACE**, and it is a third of what
     * shipped. **It is not the right answer and the right answer is the last
     * row of that table**: an existence question wants an index probe, not an
     * aggregate over every bucket in the retention window. ⛔ **A
     * `hasRecorded()` on `PlatformHealth` is what that takes and this class may
     * not add one** — that file belongs to another lane this wave, and a second
     * `DB::table('platform_health_windows')` in here would be a second place
     * that knows the table's shape (8460). **Raised at 10023 rather than taken.**
     *
     * ⚠️ **`total` RATHER THAN THE ROW COUNT, AND IT IS EXACT.**
     * `PlatformHealth::increment()` adds one to `total` on **every**
     * observation, failures included — which is why
     * `ScheduledExitCodeTest` asserts `['total' => 1, 'failures' => 1]` for a
     * single failed run — so a non-zero total and a row existing are the same
     * fact.
     */
    private function schedulerHasReported(): bool
    {
        $minutes = WatchPlatformHealth::KEEP_DAYS * 24 * 60;

        foreach ([PlatformHealthSignal::ScheduledRun, PlatformHealthSignal::ScheduledRunFailed] as $signal) {
            if ($this->health->totals($signal, $minutes)['total'] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * What an operator is told about one failed scheduled command, composed
     * where a test can drive it at any command name and any code (9274).
     *
     * ⛔ **`public static` ON {@see self::overranSummary()}'s PRECEDENT AND FOR
     * ITS REASON.** The sibling sentence in this class was over
     * `operator_alerts.summary`'s three hundred characters on **every possible
     * firing**, from the day it shipped, with a green test over it — because a
     * sentence built inline can only be measured through whichever event a
     * fixture happened to pick. Composed here, a test calls it directly with the
     * widest values the callers can produce.
     *
     * ⚠️ **THE CEILING IS BOUNDED BY CONSTRUCTION AND NOT BY THE SCHEDULE.**
     * {@see self::commandName()} clamps to sixty characters, which is also
     * `operator_alerts.subject`'s width, and the widest an `int` can render is
     * twenty — so the longest this can ever be is well inside the column and
     * `ScheduledRunBellTest` asserts that rather than asserting a phrase.
     *
     * ⚠️ **THE ACTION IS THE LAST SENTENCE, DELIBERATELY.**
     * {@see OperatorAlerts::clamp()} elides the middle and keeps the closing
     * sentence, so *run it by hand* is the part a cut cannot reach.
     *
     * ⛔ **TWO ARMS, BECAUSE A `null` CODE IS A DIFFERENT FINDING AND NOT A
     * MISSING FIGURE** (9684). A number means the command ran and refused; no
     * number means `Event::run()` threw and the command never started at all —
     * an unreachable mutex store, a process that could not be forked — and the
     * two send somebody to different places. Rendering the second as *"exited
     * with code "* and nothing would be inventing a code nobody produced.
     */
    public static function failedRunSummary(string $command, ?int $exitCode): string
    {
        if ($exitCode === null) {
            return "{$command} could not be started by the scheduler at all, so the work it does did "
                .'not happen. There is no exit code and no output, because it never ran. '
                .'Run it by hand on the server to see whether it starts.';
        }

        return "{$command} exited with code {$exitCode} on the schedule, so the work it does did not "
            .'happen this run. Its own output went to /dev/null, so nothing else recorded why. '
            .'Run it by hand on the server to read the error.';
    }

    /**
     * The counter, the log line and the bell — in that order, which is a
     * property rather than a layout (9945–9959).
     *
     * ⛔ **THE RECORD IS WRITTEN BEFORE THE BELL IS RUNG, AND NEVER THE OTHER
     * WAY ROUND.** {@see OperatorAlerts::rangSince()} is a database read and
     * this whole method runs inside {@see self::guard()}, so a query that throws
     * would be swallowed — and if it sat first, a failed scheduled command would
     * lose its counter row *and* its `critical` line to a fault in the thing
     * that was only ever meant to page somebody about it. **A bell may never be
     * a brake** (R25), and the cheapest way to mean that here is an ordering.
     *
     * ⛔ **THE GATE IS ON THE BELL AND NOT ON THE RECORD, WHICH IS THE HALF A
     * TIDY EARLY RETURN WOULD DESTROY.** Every failure inside the quiet window
     * is still counted and still logged; only the push is withheld. Moving the
     * `rangSince()` check to the top of this method reads on a diff as removing
     * duplicated work and would make `ops:schedule-runtimes` under-report a
     * command that is failing every five minutes as having failed once.
     *
     * ⚠️ **THE ORIGIN IS DETECTED RATHER THAN STATED**, on the sibling raise in
     * {@see self::record()}: every entry point of this class runs inside
     * `schedule:run`, where there is no route, so
     * `AlertOrigin::detected()` already answers `Platform`.
     */
    private function writeFailedRun(Event $task, ?int $exitCode): void
    {
        $command = $this->commandName($task);

        $this->health->recordFailure(PlatformHealthSignal::ScheduledRunFailed, $command);

        Log::critical('a scheduled command did not complete', [
            'command' => $command,
            'exit_code' => $exitCode,
            'in_background' => $task->runInBackground,
        ]);

        // ⛔ **A STANDING STATE NEEDS A LONGER MEMORY THAN THE PAGER'S QUIET
        // WINDOW**, and a shorter one than a credential's — see
        // {@see self::FAILED_RUN_REPEAT_HOURS}. `ops.alert_quiet_minutes` is one
        // number for the whole pager and cannot be stretched to cover this; a
        // month cannot be inherited from the credential bell without silencing a
        // broken nightly sweep on twenty-nine nights out of thirty.
        if ($this->alerts->rangSince(
            OperatorAlertKind::ScheduledRunFailed,
            $command,
            CarbonImmutable::now()->subHours($this->failedRunRepeatHours()),
        )) {
            return;
        }

        $this->alerts->raise(
            OperatorAlertKind::ScheduledRunFailed,
            $command,
            self::failedRunSummary($command, $exitCode),
            [
                'command' => $command,
                // ⚠️ **`null` TRAVELS RATHER THAN BEING OMITTED**, because it is
                // the one thing that tells a launch that threw from a command
                // that ran and refused (9684) — and `context` is what an
                // incident review reads after the counter window has rolled.
                'exit_code' => $exitCode,
                'in_background' => $task->runInBackground,
                'condition' => $exitCode === null ? 'launch_threw' : 'exited_non_zero',
            ],
        );
    }

    /**
     * Write the run down, and ring if it outlived its own lock.
     */
    private function record(Event $task, int $durationMs): void
    {
        $command = $this->commandName($task);
        $windowMinutes = $this->windowMinutes($task);

        $overran = $windowMinutes !== null && $durationMs >= $windowMinutes * 60_000;

        $this->health->recordRun($command, $durationMs, $overran);

        if (! $overran) {
            return;
        }

        $this->alerts->raise(
            OperatorAlertKind::ScheduledRunOverranLock,
            $command,
            self::overranSummary($command, $durationMs, $windowMinutes),
            [
                'command' => $command,
                'duration_ms' => $durationMs,
                'window_minutes' => $windowMinutes,
                'condition' => 'completed_over_window',
            ],
        );
    }

    /**
     * (b): a launch proceeded while a previous one was still marked open.
     */
    private function raiseConcurrentLaunch(Event $task, int $openForMs): void
    {
        $command = $this->commandName($task);
        $windowMinutes = $this->windowMinutes($task);

        $this->alerts->raise(
            OperatorAlertKind::ScheduledRunOverranLock,
            $command,
            self::concurrentLaunchSummary($command, $openForMs),
            [
                'command' => $command,
                'earlier_run_open_ms' => $openForMs,
                'window_minutes' => $windowMinutes,
                'condition' => 'launched_over_open_run',
            ],
        );
    }

    /**
     * The entry's overlap window in minutes, or null when it has no lock to
     * outlive.
     *
     * ⚠️ **ASKED OF THE EVENT RATHER THAN OF A TABLE OF OURS.** The number is
     * the one written in `routes/console.php` and argued in 6960–6979, so there
     * is no second copy of it to drift — which is the whole reason this bell
     * needs no registry threshold and has no off switch beyond the alert
     * address.
     */
    private function windowMinutes(Event $task): ?int
    {
        if (! $task->withoutOverlapping) {
            return null;
        }

        return max(1, $task->expiresAt);
    }

    /**
     * ⚠️ **FOUR TIMES THE WINDOW, WHICH IS 6962's CONSTANT AND 6962's REASON.**
     * *"No lock outlives four runs of the thing it guards."* A marker has to
     * survive a run that is overrunning its lock — that is the measurement — and
     * must not survive so long that one lost run blinds the entry for a day.
     * Floored at an hour so a four-minute entry is not measured through a
     * sixteen-minute keyhole, and capped at a day because past that the marker
     * is telling a story about yesterday.
     */
    private function markerTtlSeconds(Event $task): int
    {
        $minutes = min(1_440, max(60, ($this->windowMinutes($task) ?? 60) * 4));

        return $minutes * 60;
    }

    /**
     * The artisan command name, which is what joins a row to
     * `routes/console.php`.
     *
     * ⚠️ **THE SAME REGEX `SchedulingTest::schedulingEventsByCommand()` USES**,
     * deliberately: the meter's `source` and the lint's key have to be the same
     * string or `ops:schedule-runtimes` joins nothing. The fallback is for a
     * `Schedule::call()` closure, which this file has none of today —
     * a summary of ours, never anything a person typed.
     */
    private function commandName(Event $task): string
    {
        $matched = [];

        if (preg_match("/'artisan'\s+(\S+)/", (string) $task->command, $matched) === 1) {
            return $matched[1];
        }

        return mb_substr($task->getSummaryForDisplay(), 0, 60);
    }

    private function key(string $mutexName): string
    {
        return self::KEY_PREFIX.str_replace(['\\', '/'], ':', $mutexName);
    }

    /**
     * ⚠️ **MILLISECONDS FROM CARBON RATHER THAN FROM `microtime()`**, so that a
     * test travelling in time moves this too — a meter whose clock ignores
     * `travel()` can only ever be tested against real elapsed wall time, which
     * is how a duration test comes to assert nothing.
     */
    private function nowMs(): int
    {
        return CarbonImmutable::now()->getTimestampMs();
    }

    /**
     * A duration an operator reads on a phone at 3am.
     */
    private static function readable(int $ms): string
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

    private static function readableMinutes(int $minutes): string
    {
        return self::readable($minutes * 60_000);
    }

    /**
     * R25's outermost net — see the class docblock.
     */
    private function guard(callable $work): void
    {
        try {
            $work();
        } catch (Throwable $e) {
            Log::warning('scheduled run duration could not be recorded', [
                'exception' => $e::class,
            ]);
        }
    }
}
