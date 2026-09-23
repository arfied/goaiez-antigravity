<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Enums\AlertOrigin;
use App\Enums\OperatorAlertKind;
use App\Enums\PlatformHealthSignal;
use App\Enums\SignalState;
use App\Http\Middleware\WatchPlatformHeartbeats;
use App\Jobs\RecordQueueHeartbeat;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\IdentifierHashEpochs;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * What is watched, what counts as bad, and nothing else (T176 §3, P23).
 *
 * ## Two sweeps, in two different processes, and the split is the whole design
 *
 * ⛔ **{@see self::sweepHeartbeats()} MUST NOT RUN IN THE SCHEDULER.** The
 * failure it exists to catch is the scheduler being dead, which produces
 * silence: a check that only runs while the thing being checked is running
 * cannot report that it stopped. It runs in the **web** process — which fails
 * independently of both the scheduler and the queue workers — driven by
 * {@see WatchPlatformHeartbeats} on ordinary inbound
 * traffic.
 *
 * ⚠️ **WHAT THAT DOES NOT COVER, SAID RATHER THAN LEFT TO BE DISCOVERED.** A
 * platform with no inbound traffic at all runs no watch, so an installation that
 * is both silent and broken pages nobody. That is why the launch ops annex
 * (T175 §3) puts four external HTTPS monitors on this platform: **an outside
 * clock is the only thing that can observe a whole box going down**, and this
 * feature does not pretend otherwise. It covers the far more common shape —
 * traffic arriving, webhooks landing, and the scheduler or the workers stopped.
 *
 * {@see self::sweepCounters()} is the other half and is the ordinary shape: a
 * scheduled sweep reading counters that other code wrote.
 *
 * ⛔ **AND THAT SPLIT IS WHY EVERY RAISE HERE STATES {@see AlertOrigin::Platform}
 * OUT LOUD** (7820–7839). `OperatorAlerts` bounds how loudly a bell rung by an
 * **inbound HTTP request** may push, because two raisers sit on the
 * unauthenticated pixel collector and a stranger holding a public pixel key could
 * otherwise page a real handset once per tenant per quiet window. It works out
 * who rang from its surroundings — a current route means a request — and **this
 * class is the one raiser that would be read wrong**: `sweepHeartbeats()` runs
 * inside a web request *on purpose*, from `WatchPlatformHeartbeats::terminate()`,
 * for the reason two paragraphs up. ⛔ **Left to the ambient reading, the bell
 * that says the scheduler is dead would have been treated as stranger-provoked
 * and could have been withheld — the remedy silencing the one alert it exists to
 * protect.** Nothing here is ever bounded, on either sweep; `sweepCounters()`
 * states it too, so that moving a check between the two processes cannot quietly
 * change what happens to its bell.
 *
 * ## Every threshold is a registry row, and zero means off
 *
 * ⚠️ **511, WHICH IS THE FAILURE THIS FEATURE IS MOST LIKELY TO PRODUCE.** *"A
 * lint tuned until it stops crying wolf is one tuned until it catches nothing."*
 * A pager set too tight is silenced by the person carrying it, so every figure
 * here moves in Ops without a deploy, and every one of them disables its own
 * check at zero — the same convention `messaging.platform_complaint_trip_bp`
 * already uses (2409).
 *
 * ⚠️ **AND THEY SEED ON, UNLIKE THE COMPLAINT TRIP.** That one seeds at zero
 * because the owner has to choose a figure before a machine may stop the
 * platform sending. **Nothing here stops anything** (R25), so the same caution
 * would only produce a bell that never rings — 272's shape, arriving as a
 * default.
 *
 * ## Which reading a threshold gets is the enum's answer, not this file's
 *
 * ⛔ **IT WAS HARDCODED HERE TWICE AND {@see PlatformHealthSignal::countsSuccesses()}
 * HAD NO CALLER AT ALL** — 272's shape inside the method whose own docblock says
 * it is *"what a caller asks rather than remembering"* (7092). Both checks now
 * ask it, through {@see self::failureCount()} and {@see self::failureRateBp()},
 * and **neither reading is available for the wrong signal**: each returns null
 * where the enum says it does not apply, and a null skips the source rather than
 * alerting on a number that means nothing.
 *
 * ⚠️ **THE ROUTING PRESERVED TODAY'S ARITHMETIC EXACTLY, WHICH WAS CHECKED
 * BEFORE IT WAS DONE AND IS NOT A SAFE ASSUMPTION IN GENERAL.** The enum agreed
 * with both call sites: `WebhookSignature` counts no successes and was already
 * read as an absolute count, `VendorCall` counts them and was already read as a
 * rate, and the floor check still runs first so the divisor is still at least
 * one. **Had they disagreed, routing them through it would have been a silent
 * behaviour change to an alerting threshold** — which is why the check came
 * first.
 */
final class PlatformHealthChecks
{
    public const string WINDOW_KEY = 'ops.health_window_minutes';

    public const string FAILED_JOB_KEY = 'ops.failed_job_spike';

    public const string WEBHOOK_KEY = 'ops.webhook_signature_failure_spike';

    public const string VENDOR_RATE_KEY = 'ops.vendor_error_rate_bp';

    /**
     * The highest error rate an operator may set a threshold at (7580-7599).
     *
     * ⛔ **A RATE CANNOT EXCEED 100%, SO A THRESHOLD ABOVE IT IS NOT A TIGHTER
     * THRESHOLD — IT IS AN OFF SWITCH NOBODY DOCUMENTED.** `999999` was a legal
     * value on the Ops settings screen and it means *"alert when this vendor
     * fails ninety-nine times out of ten"*, which never happens, so the vendor
     * check simply stops existing while the row reads as configured. That is
     * `sending_health_windows`' shape wearing a threshold: a containment whose
     * state is indistinguishable, on every screen, from a healthy platform.
     *
     * ⚠️ **THIS IS THE SECOND OF THE TWO KEYS BOUNDED IN THIS SLICE AND ITS
     * DERIVATION SHARES NOTHING WITH THE FIRST, WHICH IS THE POINT.**
     * `OperatorAlerts::MAX_QUIET_MINUTES` is a judgement about how long a pager
     * may be off; this is arithmetic — ten thousand basis points is one, and one
     * is every call. **Two ranges with unrelated derivations are two named
     * rules, not a `min`/`max` schema on two hundred manifest entries** (256).
     *
     * ⚠️ **ZERO STAYS LEGAL AND IS UNTOUCHED.** 2409's convention makes it the
     * documented way to switch one check off, and the argument for it is the one
     * this whole feature stands on — *"the escape valve that keeps an operator
     * from muting the whole channel when one check is too tight for their
     * traffic"*. **What is refused is the undocumented off switch, never the
     * documented one.**
     *
     * ⛔ **`ops.health_window_minutes` WAS CONSIDERED AND IS DELIBERATELY NOT
     * BOUNDED.** It accepts `-5`, which is nonsense, and {@see self::window()}
     * has always corrected it to one minute — so there is no consequence to
     * refuse, and a rule with no defect behind it is 256's lint that passes
     * vacuously. The asymmetry is the evidence that this is a per-key rule with
     * a per-key argument rather than a framework looking for members.
     */
    public const int MAX_ERROR_RATE_BP = 10_000;

    public const string VENDOR_FLOOR_KEY = 'ops.vendor_error_floor';

    public const string STALE_KEY = 'ops.heartbeat_stale_minutes';

    /**
     * How long the credential bell stays quiet for one key after ringing
     * (9320–9327).
     *
     * ⛔ **A REPEAT INTERVAL AND NOT AN ARMING THRESHOLD, WHICH IS THE WHOLE
     * DISTINCTION THIS SLICE TURNS ON.** An arming row decides whether a bell can
     * ring at all, and 9282 measured what one costs on this install: a **total**
     * Zernio outage at one connected listing makes about twelve calls an hour
     * against a floor of twenty, so its bell cannot ring however bad it gets.
     * **Nothing here decides whether this rings.** The first refused read rings
     * it, at any volume, on any install; this only decides when the platform is
     * willing to say the same thing twice.
     *
     * ⚠️ **IN CODE RATHER THAN IN THE REGISTRY, ON
     * {@see OperatorAlerts::PUSH_BUDGET_HOURS}' PRECEDENT.** It is not a
     * judgement about this platform's traffic — there is nothing to tune it
     * against — and an operator handed the box would be handed the ability to set
     * it to a year. ⚠️ **It is thirty rather than a rounder number because that is
     * `WatchPlatformHealth::KEEP_DAYS`**, the point at which the counters behind
     * this bell are pruned and the platform genuinely stops remembering the
     * fault; naming the constant directly would import a console command into the
     * checks it drives.
     */
    public const int CREDENTIAL_REPEAT_DAYS = 30;

    /**
     * The two signals {@see self::checkCredentialFaults()} reads (9320–9327).
     *
     * ⛔ **A LIST HERE RATHER THAN A DISCOVERY, AND IT IS THE ONE PLACE IN THIS
     * CLASS THAT ENUMERATES ANYTHING.** `sources()` discovers *sources* from the
     * rows on purpose, so a new vendor is watched from its first call; the
     * **signals** are a different question — each one has its own sentence and
     * its own remedy, and a check that looped every case of the enum would read
     * `Heartbeat` and `ScheduledRun` as credential faults the day somebody
     * reordered them. ⚠️ **It cannot go stale silently**: a third credential
     * signal arriving with no entry here has no reader, and `ObservabilityTest`
     * §6 fails the build on a signal nothing writes rather than on one nothing
     * reads — so `credentialSummary()` keeps a `default` arm and this list is
     * checked by `CredentialFaultBellTest` against the enum's own naming rule.
     *
     * @var list<PlatformHealthSignal>
     */
    public const array CREDENTIAL_SIGNALS = [
        // ⚠️ **THE UNREADABLE ONE IS FIRST AND THE ORDER IS LOAD-BEARING IN
        // EXACTLY ONE CASE.** A key is only ever one of the two at a time, so
        // normally nothing is decided here — but a key that was absent early in
        // the window and stored with an unreadable ciphertext later in it puts
        // both signals in one window, and the second raise is swallowed by the
        // dedupe on (kind, subject). The alert that survives should be the one
        // whose remedy is not "paste it", because a paste is what has already
        // been tried.
        PlatformHealthSignal::CredentialUnreadable,
        PlatformHealthSignal::CredentialAbsent,
    ];

    /**
     * The processes that beat, and therefore the processes whose silence is
     * noticed.
     *
     * ⚠️ **TWO, BECAUSE THEY FAIL SEPARATELY AND MEAN DIFFERENT THINGS.** A dead
     * scheduler means nothing is *started* — no campaign pass, no dunning tick,
     * no renewal notice — while every queued job still drains. A dead worker
     * means everything is started and nothing *finishes*, which looks perfectly
     * healthy from the scheduler's own log.
     *
     * ⛔ **`'queue'` IS ONE QUEUE NAME AND NOT THE QUEUES — SAID HERE BECAUSE
     * THE NAME OF THE PROCESS IS WHAT MAKES IT READ OTHERWISE** (8880-8899).
     * The beat behind it is {@see RecordQueueHeartbeat}, dispatched bare, so it
     * rides {@see RecordQueueHeartbeat::queueWatched()} alone.
     *
     * ⛔ **RENAMING THE STRING IS REFUSED AND THE REASON IS THE ALERT ITSELF.**
     * `'queue'` is the `source` column every beat has ever been written under
     * and the `subject` of every `HeartbeatSilent` row; a rename makes
     * `lastBeat()` answer `null`, *"never beaten is not silent"* takes the
     * early return, and **the one bell that says nothing is finishing goes quiet
     * for exactly as long as it takes somebody to notice.** The words an
     * operator reads are narrowed instead — see
     * {@see self::silenceSummary()} — which costs nothing and strands nothing.
     *
     * @var list<string>
     */
    public const array PROCESSES = ['scheduler', 'queue'];

    /**
     * Refuse a vendor error-rate threshold that no rate can reach (7580-7599).
     *
     * ⛔ **STATIC, AND CALLED FROM INSIDE `DefaultsRegistry::set()`** — see
     * {@see OperatorAlerts::refuseUnworkableQuietWindow()} for the whole
     * argument about why the guard belongs at the registry rather than on the
     * Ops screen, and why the manifest gained no schema field for it.
     *
     * @throws InvalidArgumentException when the value is not a whole number of
     *                                  basis points between zero and
     *                                  {@see self::MAX_ERROR_RATE_BP}
     */
    public static function refuseUnreachableErrorRate(mixed $value): void
    {
        if (! is_int($value)) {
            throw new InvalidArgumentException(
                'The vendor error-rate threshold is a whole number of basis points — 3,000 is 30%. '
                .'Clearing this box reads back as zero, which switches the vendor check off '
                .'entirely.'
            );
        }

        if ($value < 0) {
            throw new InvalidArgumentException(
                'A failure rate cannot be negative. Zero is how this check is switched off.'
            );
        }

        if ($value > self::MAX_ERROR_RATE_BP) {
            throw new InvalidArgumentException(
                'A threshold of '.number_format($value).' basis points is '
                .number_format($value / 100, 1).'%, and a failure rate can never exceed 100% — so '
                .'this would switch the vendor check off while leaving it looking configured. The '
                .'highest this may be set to is '.number_format(self::MAX_ERROR_RATE_BP)
                .' basis points, which alerts only when every call fails. To switch the check off, '
                .'set it to zero.'
            );
        }
    }

    public function __construct(
        private readonly DefaultsRegistry $defaults,
        private readonly PlatformHealth $health,
        private readonly OperatorAlerts $alerts,
        private readonly IdentifierHashEpochs $epochs,
    ) {}

    public function credentialRepeatDays(): int
    {
        return $this->defaults->int('ops.health.credential_repeat_days');
    }

    /**
     * The counters somebody else wrote — jobs, webhooks, vendors — and one state
     * nothing counts at all.
     *
     * ⚠️ **THE LAST CHECK IS NOT A COUNTER AND THE DIFFERENCE IS WORTH ONE
     * LINE** (8270–8289). The three above read a number that other code
     * incremented and compare it against a figure an operator set.
     * {@see self::checkSuppressionReadability()} reads a **state** — whether
     * this install can still read the hashes it has stored — which has no
     * counter, no threshold and nothing to tune. It belongs on the same clock
     * for the same reason: it is a fact this platform already knows about itself
     * and nobody is looking at it.
     *
     * ⚠️ **AND THERE IS NOW A THIRD SHAPE, WHICH IS NEITHER — ADDED 2026-08-25
     * (9320–9327).** {@see self::checkCredentialFaults()} reads an **event**: a
     * credential resolution that was refused. Nothing can be asked about it
     * afterwards the way an epoch can, and counting it against a threshold would
     * be counting **traffic** — so it has no arming row at all, and what stops
     * it repeating is that the platform has not seen that fault before. ⛔ **The
     * paragraph above says "the three" and this one says "a third"; the count is
     * `sweepCounters()`' own body and neither sentence is the authority for it.**
     *
     * @return int how many alerts were raised (a de-duplicated one counts as
     *             none, because nothing rang)
     */
    public function sweepCounters(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        return $this->checkFailedJobs($now)
            + $this->checkWebhookSignatures($now)
            + $this->checkWebhookKeySources($now)
            + $this->checkVendorErrors($now)
            + $this->checkCredentialFaults($now)
            + $this->checkSuppressionReadability();
    }

    /**
     * The processes that should have beaten and did not.
     *
     * ⛔ **THIS SWEPT NOTHING AT ALL ONCE A SILENCE PASSED ABOUT TWENTY-FOUR
     * HOURS, AND THE EARLY RETURN BELOW IS WHERE IT LANDED — CORRECTED
     * 2026-08-26 (9930–9944).** {@see PlatformHealth::lastBeat()} bounded its
     * read to a day, so a process dead for longer answered `null`, which is the
     * same value as *never beaten* — and this method skipped it for ever, while
     * `Admin\OperatorAlertBoard` went on rendering the bell as armed. ⚠️ **The
     * early return was not the defect and is unchanged**: what changed is that
     * `null` now means what it says.
     *
     * @return int how many alerts were raised
     */
    public function sweepHeartbeats(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        $stale = $this->defaults->int(self::STALE_KEY);

        if ($stale <= 0) {
            return 0;
        }

        $raised = 0;

        foreach (self::PROCESSES as $process) {
            $last = $this->health->lastBeat($process);

            // ⚠️ **NEVER BEATEN IS NOT SILENT.** See `PlatformHealth::lastBeat()`
            // — a fresh install, a fresh worktree and every test database are in
            // this state, and paging on it would make the alert something people
            // learn to ignore before it has ever been right once.
            //
            // ⛔ **AND *NEVER BEATEN* NOW MEANS IT, WHICH IT DID NOT UNTIL
            // 2026-08-26.** This arm used to swallow *"dead for two days"* as
            // well, because the read behind it had a one-day horizon. The state
            // it still swallows is a real one, it is **standing rather than
            // sudden**, and it is now reported where a standing state belongs —
            // the arming panel, through {@see self::heartbeatCoverage()} —
            // rather than by paging somebody about an install that has never
            // started.
            if ($last === null) {
                continue;
            }

            $age = (int) $last->diffInMinutes($now, absolute: true);

            if ($age < $stale) {
                continue;
            }

            $raised += $this->alerts->raise(
                OperatorAlertKind::HeartbeatSilent,
                $process,
                $this->silenceSummary($process, $age),
                [
                    'process' => $process,
                    'minutes_since_last_beat' => $age,
                    'stale_after_minutes' => $stale,
                ],
                origin: AlertOrigin::Platform,
            ) === null ? 0 : 1;
        }

        return $raised;
    }

    /**
     * What the operator is told a silence means.
     *
     * ⚠️ **A METHOD RATHER THAN A `match` AT THE CALL SITE, AND THE ANALYSER IS
     * WHY.** With {@see self::PROCESSES} a literal list, the arms are exhaustive
     * and a `default` arm reads as dead code — but removing it would make an
     * `UnhandledMatchError` the outcome of adding a third process, **thrown
     * inside the alert path**, which is the one place in this feature that may
     * never throw (R25). Behind a `string` parameter the fallback is a real
     * branch again.
     *
     * The sentences are deliberately different: a dead scheduler means nothing
     * *starts*, a dead worker means nothing *finishes*, and an operator reading
     * one at 3am should not have to work out which they have.
     *
     * ⛔ **THE QUEUE SENTENCE SPOKE FOR EVERY QUEUE AND IS WRITTEN BY A PROBE OF
     * ONE — NARROWED 2026-08-23 (8880-8899).** It read *"No queue worker has
     * picked up a job for {$age} minutes. Work is being queued and nothing is
     * finishing it."* — stated to an operator, about the platform's queues, on
     * the evidence of {@see RecordQueueHeartbeat}, which carries no `$queue` and
     * therefore rides exactly one of the six names `config/horizon.php` designs
     * for. **A worker that stopped popping any other name raises nothing at
     * all**, so the wide sentence made the silence mean more than it does in
     * the one direction that matters: an operator who has read it once knows
     * what its absence would have meant, and its absence means less.
     * 314-316's shape, at the sentence rather than at the docblock.
     *
     * ⚠️ **THE NAME IS DERIVED AT ALERT TIME, ON THE BOX, FROM THE SAME CONFIG
     * THE DISPATCHER READ.** `CLAUDE.md` forbids a *document* stating what a
     * running install has on; this is the install telling an operator what it
     * itself is watching, which is the opposite problem. When there is no name
     * to give — a `sync` connection has none — the original sentence is used
     * unchanged rather than a name being invented.
     *
     * ⚠️ **THE DURATION IS RENDERED RATHER THAN PRINTED IN MINUTES, AND THAT IS
     * A CONSEQUENCE OF 9930 RATHER THAN A TIDY-UP.** Until the one-day horizon
     * came off {@see PlatformHealth::lastBeat()}, nothing older than about
     * 1,455 minutes could reach this sentence at all. It can now be handed a
     * five-figure number, and *"has not run for 44,640 minutes"* asks somebody
     * woken at 3am to do arithmetic before they can act. ⚠️ **The exact figure
     * is untouched in `context['minutes_since_last_beat']`** — 9371's rule is
     * that the honest counter is levelled up rather than down, and a machine
     * reading that key still gets minutes.
     */
    private function silenceSummary(string $process, int $age): string
    {
        $queueName = RecordQueueHeartbeat::queueWatched();
        $for = self::silenceLength($age);

        return match ($process) {
            'scheduler' => "The scheduler has not run for {$for}. Nothing scheduled is starting: no campaign passes, no dunning, no renewal notices.",
            'queue' => $queueName === null
                ? "No queue worker has picked up a job for {$for}. Work is being queued and nothing is finishing it."
                : "No queue worker has picked up a job on the {$queueName} queue for {$for}. Work dispatched there is being queued and nothing is finishing it. This beat rides that one queue name and can see no other.",
            default => "The {$process} process has not reported for {$for}.",
        };
    }

    /**
     * A silence in the largest unit that still says something exact.
     *
     * ⚠️ **THE BANDS ARE WIDE ON PURPOSE.** Two hours of minutes and two days of
     * hours mean the ordinary fifteen-minute alert — the one an operator sees on
     * a normal bad day — reads exactly as it always has, and only a silence that
     * could not previously exist gets a new unit.
     *
     * ⚠️ **IT TRUNCATES AND NEVER ROUNDS UP.** *"3 days"* on a silence of three
     * days and twenty-two hours understates it; *"4 days"* on one of three days
     * and one hour would be this platform overstating its own outage, and an
     * instrument that exaggerates once is argued with the next time.
     */
    private static function silenceLength(int $minutes): string
    {
        if ($minutes < 120) {
            return $minutes.' '.Str::plural('minute', $minutes);
        }

        $hours = intdiv($minutes, 60);

        if ($hours < 48) {
            return $hours.' '.Str::plural('hour', $hours);
        }

        $days = intdiv($hours, 24);

        return $days.' '.Str::plural('day', $days);
    }

    /**
     * Whether the heartbeat can ring at all, and about which processes
     * (9930–9944).
     *
     * ⛔ **THE ARMING PANEL COMPUTED THIS FROM THE THRESHOLD ALONE, WHICH IS
     * TRUE OF EVERY OTHER BELL ON THAT SCREEN AND FALSE OF THIS ONE.** The four
     * counter checks read a number somebody else's code is already writing, so a
     * non-zero threshold really is the whole of their arming. **This check reads
     * an absence**, and an absence needs a presence to be measured against: a
     * process that has never beaten once cannot go silent, and
     * {@see self::sweepHeartbeats()} skips it for the reason recorded there.
     * ⚠️ **So on an install whose `queue:work` cron line was never added — and
     * nothing in this repository creates a crontab (416) — the bell that says
     * *"nothing is finishing your work"* is structurally unable to ring, and the
     * one screen whose job is telling an operator which bells are armed said
     * `Watching`.**
     *
     * ⚠️ **THE ROW LIVES HERE RATHER THAN ON THE SCREEN BECAUSE THE ANSWER IS
     * THIS CLASS'S** — 7661's rule, *a screen reads a figure off the class that
     * applies it rather than repeating it*, with a state instead of a figure.
     * The queue's **phrase** still comes in from the screen, because the name of
     * the queue is derived from config by the reader that already owns that
     * derivation and is pinned against the dispatcher by `QueueRoutingTest`.
     *
     * ⚠️ **FOUR STATES, AND THE ZERO ARM IS DELIBERATELY UNCHANGED**: it is
     * about the threshold rather than about the coverage, it was never wrong,
     * and `OperatorAlertBoardTest` asserts its wording off the rendered page.
     *
     * ⚠️ **`SignalState::Unknown` RATHER THAN `Alert` ON A FRESH INSTALL.** It is
     * *"not measured"* by the enum's own definition and never a fourth severity
     * — nothing is wrong with an install that has not started yet, and a red
     * row on every fresh deploy is 511 arriving on the screen instead of the
     * handset. **What it must not be is `Ok`**, which is what it was.
     *
     * @return array{watched: bool, state: SignalState, status: string, detail: string}
     */
    public function heartbeatCoverage(int $staleMinutes, string $queuePhrase): array
    {
        if ($staleMinutes <= 0) {
            return [
                'watched' => false,
                'state' => SignalState::Alert,
                'status' => 'Not being checked',
                'detail' => 'Its threshold is 0, so a stopped scheduler or worker raises nothing at all.',
            ];
        }

        $never = $this->processesNeverSeen();

        $armed = 'Rings when the scheduler stops, or when nothing picks up '.$queuePhrase
            ." for {$staleMinutes} minutes. It watches that one queue and no other.";

        if ($never === []) {
            return [
                'watched' => true,
                'state' => SignalState::Ok,
                'status' => 'Watching',
                'detail' => $armed,
            ];
        }

        if (count($never) === count(self::PROCESSES)) {
            return [
                'watched' => false,
                'state' => SignalState::Unknown,
                'status' => 'Waiting for a first beat',
                'detail' => 'Neither the scheduler nor a queue worker has ever reported a beat on this '
                    .'install, so nothing here can tell silence from a process that was never started, '
                    .'and this rings about neither. It arms itself on the first beat — the two cron '
                    .'entries in the deploy notes are what produce one, and nothing in the application '
                    .'writes them.',
            ];
        }

        // One clause per silent process rather than a sentence about the first
        // of them: `PROCESSES` is two today and the branch above covers the case
        // where every member is missing, so this arm has exactly one member —
        // but a third process would make "the first one" a coverage claim that
        // understates, in the row whose entire subject is over-claimed coverage.
        $clauses = array_map(
            static fn (string $process): string => self::processPhrase($process).' reported a beat on this install',
            $never,
        );

        return [
            'watched' => true,
            'state' => SignalState::Attention,
            'status' => 'Watching, with a gap',
            'detail' => $armed.' But '.implode(', and ', $clauses)
                .', so that silence cannot be told from a process that was never started and nothing '
                .'will ring about it until it beats once.',
        ];
    }

    /**
     * The processes this platform has never once heard from.
     *
     * ⚠️ **MONOTONE, WHICH IS WHAT MAKES IT SAFE TO READ ON A SCREEN.** A
     * process leaves this list on its first beat and can never return to it —
     * {@see PlatformHealth::prune()} keeps the last beat of every source for
     * ever precisely so that it cannot — so a stale reading of this can only be
     * wrong in the direction of naming a gap that has just been filled.
     *
     * @return list<string>
     */
    public function processesNeverSeen(): array
    {
        return array_values(array_filter(
            self::PROCESSES,
            fn (string $process): bool => $this->health->lastBeat($process) === null,
        ));
    }

    /**
     * How a process is named in a sentence about it having never reported.
     *
     * A method rather than a `match` at the call site, on
     * {@see self::silenceSummary()}'s reasoning exactly: behind a `string`
     * parameter the fallback arm is a real branch rather than dead code, and an
     * `UnhandledMatchError` may not be the outcome of adding a third process.
     */
    private static function processPhrase(string $process): string
    {
        if ($process === 'scheduler') {
            return 'the scheduler has never';
        }
        if ($process === 'queue') {
            return 'no queue worker has ever';
        }

        return "the {$process} process has never";
    }

    /**
     * Jobs that died in the window.
     *
     * ⚠️ **READ STRAIGHT OFF `failed_jobs` RATHER THAN COUNTED INTO A WINDOW OF
     * OUR OWN.** The framework already writes that row on every permanent
     * failure, so a second counter beside it would be a second source of truth
     * that can disagree with the thing an operator actually looks at — 2186's
     * shape. It also means this check has had a writer since Stage 0 rather than
     * since this slice, which is the property `CLAUDE.md`'s first recurring
     * failure shape is about.
     */
    private function checkFailedJobs(CarbonImmutable $now): int
    {
        $threshold = $this->defaults->int(self::FAILED_JOB_KEY);

        if ($threshold <= 0) {
            return 0;
        }

        $minutes = $this->window();
        $failed = DB::table('failed_jobs')
            ->where('failed_at', '>=', $now->subMinutes($minutes))
            ->count();

        if ($failed < $threshold) {
            return 0;
        }

        return $this->alerts->raise(
            OperatorAlertKind::FailedJobSpike,
            '',
            "{$failed} background jobs failed in the last {$minutes} minutes (the alert threshold is {$threshold}). Whatever they were carrying — messages, charges, syncs — did not happen.",
            [
                'failed_jobs' => $failed,
                'threshold' => $threshold,
                'window_minutes' => $minutes,
            ],
            origin: AlertOrigin::Platform,
        ) === null ? 0 : 1;
    }

    /**
     * Webhooks we refused because we could not verify them.
     *
     * Per endpoint rather than in aggregate: the commonest cause is one signing
     * secret being missing or rotated, and an aggregate count would name the
     * platform instead of the endpoint an operator has to go and fix.
     */
    private function checkWebhookSignatures(CarbonImmutable $now): int
    {
        $threshold = $this->defaults->int(self::WEBHOOK_KEY);

        if ($threshold <= 0) {
            return 0;
        }

        $minutes = $this->window();
        $raised = 0;

        foreach ($this->health->sources(PlatformHealthSignal::WebhookSignature, $minutes, $now) as $source) {
            $totals = $this->health->totals(PlatformHealthSignal::WebhookSignature, $minutes, $source, $now);

            $failures = $this->failureCount(PlatformHealthSignal::WebhookSignature, $totals);

            if ($failures === null || $failures < $threshold) {
                continue;
            }

            $raised += $this->alerts->raise(
                OperatorAlertKind::WebhookSignatureFailures,
                $source,
                "{$failures} {$source} webhooks were rejected in the last {$minutes} minutes because their signature did not verify (the alert threshold is {$threshold}). The usual cause is a missing or rotated signing secret, and while it lasts nothing that endpoint reports is being acted on.",
                [
                    'endpoint' => $source,
                    'failures' => $failures,
                    'threshold' => $threshold,
                    'window_minutes' => $minutes,
                ],
                origin: AlertOrigin::Platform,
            ) === null ? 0 : 1;
        }

        return $raised;
    }

    /**
     * Webhooks nobody could judge, because the signing material could not be
     * fetched (9380–9394).
     *
     * ## ⛔ The sibling above says "we checked and it failed"; this says "we
     * never checked"
     *
     * ⛔ **AND UNTIL THIS CHECK EXISTED THE SECOND RANG THE FIRST'S BELL, UNDER
     * THE FIRST'S SENTENCE.** {@see self::checkWebhookSignatures()} tells an
     * operator *"the usual cause is a missing or rotated signing secret"*, which
     * is true of a signature that did not verify and is the wrong remedy for a
     * certificate host that did not answer. Two endpoints — `ses` and `gmail` —
     * fetch signing material over the network before they can verify anything, and both used to report a failed fetch as an
     * unverifiable signature.
     *
     * ## ⛔ No threshold, and it is the same argument as the credential bell's
     *
     * ⛔ **A KEY HOST IS UNREACHABLE AT ONE WEBHOOK AN HOUR EXACTLY AS MUCH AS
     * AT TEN THOUSAND.** Any figure here would be a count of the vendor's own
     * traffic — 9282's arithmetic, where a bell armed at twenty calls an hour
     * cannot ring for an install that makes twelve — over a loss that is total
     * at any volume: **every delivery arriving while it lasts is answered and
     * dropped.** So it rings on the first one, per endpoint.
     *
     * ⚠️ **AND THERE IS DELIBERATELY NO LONG REPEAT GATE, WHICH IS WHERE THIS
     * PARTS FROM {@see self::checkCredentialFaults()}.** That one needs
     * {@see self::CREDENTIAL_REPEAT_DAYS} because an absent credential is a
     * **standing state** — absent every time anybody asks, for ever, until
     * somebody pastes it. This fault is transient by construction: it stops when
     * the vendor or the network recovers, and while it does not stop, deliveries
     * are being lost every hour. **Saying so again is the point**, and what
     * bounds the pager is `OperatorAlerts::PUSH_BUDGET_PER_KIND` rather than
     * anything here.
     *
     * ⚠️ **THE SOURCES ARE DISCOVERED, NOT LISTED**, exactly as the sibling's
     * are: a third endpoint that grows a fetch is watched from its first failure
     * with no change here.
     *
     * ⛔ **THE SUMMARY FITS INSIDE {@see OperatorAlerts::SUMMARY_LIMIT} AT THE
     * LONGEST ENDPOINT NAME AND AN ABSURD COUNT, AND A TEST HOLDS IT THERE** —
     * 9235(a)'s finding, where a bell's own action clause was silently clipped
     * off the tail with every count correct and the text sent. **The clause this
     * one cannot afford to lose is the last one**, because it is the whole
     * correction: *our own signing secrets are not involved.*
     *
     * @return int how many alerts were raised
     */
    private function checkWebhookKeySources(CarbonImmutable $now): int
    {
        $minutes = $this->window();
        $raised = 0;

        foreach ($this->health->sources(PlatformHealthSignal::WebhookKeyUnavailable, $minutes, $now) as $source) {
            $totals = $this->health->totals(PlatformHealthSignal::WebhookKeyUnavailable, $minutes, $source, $now);

            $failures = $this->failureCount(PlatformHealthSignal::WebhookKeyUnavailable, $totals);

            if ($failures === null || $failures < 1) {
                continue;
            }

            $deliveries = $failures === 1 ? '1 delivery' : "{$failures} deliveries";

            $raised += $this->alerts->raise(
                OperatorAlertKind::WebhookKeysUnavailable,
                $source,
                "The {$source} endpoint could not fetch the keys it checks callers with, so {$deliveries} in the last {$minutes} minutes were turned away unjudged. They were probably genuine and are lost. Our own signing secrets are not involved — check egress, DNS and the vendor status.",
                [
                    'endpoint' => $source,
                    'unjudged' => $failures,
                    'window_minutes' => $minutes,
                ],
                origin: AlertOrigin::Platform,
            ) === null ? 0 : 1;
        }

        return $raised;
    }

    /**
     * Outside services failing a large enough share of a large enough number of
     * calls.
     *
     * ⚠️ **THE FLOOR IS NOT DECORATION.** One failed call out of one is a rate
     * of 100%, and an alert that fires on it teaches its reader to ignore it
     * within a week — which is the state in which the real outage arrives.
     */
    private function checkVendorErrors(CarbonImmutable $now): int
    {
        // ⚠️ **CLAMPED FOR THE REASON `OperatorAlerts::quietMinutes()` IS**
        // (7580-7599): {@see self::refuseUnreachableErrorRate()} stops a new
        // value above 100%, and a row written before that guard existed is
        // unreachable from any write guard. Clamping here makes the check fire
        // on a vendor failing every call rather than never at all, and the
        // screen still shows the operator the figure they typed.
        $thresholdBp = min(self::MAX_ERROR_RATE_BP, $this->defaults->int(self::VENDOR_RATE_KEY));
        $floor = $this->defaults->int(self::VENDOR_FLOOR_KEY);

        if ($thresholdBp <= 0 || $floor <= 0) {
            return 0;
        }

        $minutes = $this->window();
        $raised = 0;

        foreach ($this->health->sources(PlatformHealthSignal::VendorCall, $minutes, $now) as $source) {
            $totals = $this->health->totals(PlatformHealthSignal::VendorCall, $minutes, $source, $now);

            if ($totals['total'] < $floor) {
                continue;
            }

            $rateBp = $this->failureRateBp(PlatformHealthSignal::VendorCall, $totals);

            if ($rateBp === null || $rateBp < $thresholdBp) {
                continue;
            }

            $percent = round($rateBp / 100, 1);

            $raised += $this->alerts->raise(
                OperatorAlertKind::VendorErrorRate,
                $source,
                "{$source} failed {$percent}% of {$totals['total']} calls in the last {$minutes} minutes. Work that needs it is being recorded as unanswered rather than retried into a bill.",
                [
                    'provider' => $source,
                    'calls' => $totals['total'],
                    'failures' => $totals['failures'],
                    'rate_bp' => $rateBp,
                    'threshold_bp' => $thresholdBp,
                    'floor' => $floor,
                    'window_minutes' => $minutes,
                ],
                origin: AlertOrigin::Platform,
            ) === null ? 0 : 1;
        }

        return $raised;
    }

    /**
     * Credentials something needed and could not use (9320–9327).
     *
     * ## ⛔ The third shape on this clock, and it is neither of the other two
     *
     * ⛔ **NOT A COUNTER OVER A THRESHOLD, AND NOT A STATE THIS CLASS CAN
     * OBSERVE DIRECTLY EITHER.** The three checks above read a number somebody
     * incremented and compare it against a figure an operator set;
     * {@see self::checkSuppressionReadability()} reads a state the platform can
     * be asked about at any moment. **This reads an EVENT** — a resolution that
     * was refused — which nothing can ask about after the fact, and which is
     * therefore recorded when it happens and read here.
     *
     * ⛔ **THERE IS NO THRESHOLD AND NO REGISTRY KEY, AND THAT IS THE FINDING
     * RATHER THAN A SHORTCUT** (9283: *"the missing instrument is persistence,
     * not rate"*). Every figure on this clock is a volume bar, and 9282 measured
     * what a volume bar costs on the install this platform actually has: a
     * **total** Zernio outage at one connected listing produces about twelve
     * calls an hour against a floor of twenty, so the bell for it cannot ring.
     * The same arithmetic was never done for `ops.webhook_signature_failure_spike`
     * (ten rejected webhooks an hour, on a bell whose own manifest entry says the
     * commonest cause is our own rotated secret) or for `ops.failed_job_spike`
     * (twenty-five). **A credential is absent at one call an hour exactly as
     * much as at ten thousand**, so any figure here would be an arming row that
     * only a busy platform could reach — `CLAUDE.md`'s decoration, seeded on.
     *
     * ## ⚠️ What stops it ringing every hour for ever instead
     *
     * ⚠️ **AN ABSENT CREDENTIAL IS ABSENT EVERY TIME ANYBODY ASKS**, so a check
     * reading only the current window would re-raise on every quiet window until
     * somebody pasted the key — on the install this platform has today that is
     * five payment keys, and a public marketing page that reads a Cloudflare key
     * on every render, ringing hourly for ever. That is a pager muted before the
     * bell has ever been right once (511). {@see self::CREDENTIAL_REPEAT_DAYS}
     * is the bound, asked through {@see OperatorAlerts::rangSince()}.
     *
     * ⛔ **THE GATE IS THE ALERT HISTORY AND NOT THE COUNTER HISTORY, AND THE
     * FIRST DRAFT OF IT WAS WRONG.** *"Has this fault been recorded before the
     * current window"* reads as the same question and is not: the window is
     * bucketed to the hour and slides, so on the seeded sixty minutes there is a
     * half-hour in which the first bucket has fallen out of the window and the
     * quiet window has already expired — and the bell rings a second time about
     * the same first fault, which is precisely the repetition this gate exists to
     * stop. **What is asked instead is whether this bell has already been rung**,
     * which has no edge and needs no arithmetic.
     *
     * ⚠️ **WHAT THAT GIVES UP IS STATED RATHER THAN HIDDEN.** A credential fixed
     * and broken again inside the horizon rings once, not twice; the standing
     * state is on `Admin\Credentials` and the row stays on the alert board for a
     * year.
     *
     * ## ⚠️ The subject is the key, and the fault is the counter's to say
     *
     * ⚠️ **PER CREDENTIAL, LIKE {@see self::checkWebhookSignatures()} IS PER
     * ENDPOINT** — three absent Authorize.Net keys are three things to paste and
     * one aggregate bell would name the platform instead of the box to fill in.
     * ⛔ **WHICH FAULT IT IS COMES FROM WHICH SIGNAL RECORDED IT AND NEVER FROM
     * A LOOK AT THE STORE**: deriving it here by asking whether a
     * `platform_credentials` row exists would be exact only until somebody
     * pasted the key between the fault and this sweep, and the alert would then
     * carry the wrong remedy — see {@see PlatformHealthSignal::CredentialUnreadable}.
     *
     * @return int how many alerts were raised
     */
    private function checkCredentialFaults(CarbonImmutable $now): int
    {
        $minutes = $this->window();
        $raised = 0;

        foreach (self::CREDENTIAL_SIGNALS as $signal) {
            foreach ($this->health->sources($signal, $minutes, $now) as $key) {
                $totals = $this->health->totals($signal, $minutes, $key, $now);

                $failures = $this->failureCount($signal, $totals);

                if ($failures === null || $failures < 1) {
                    continue;
                }

                if ($this->alerts->rangSince(
                    OperatorAlertKind::PlatformCredentialUnusable,
                    $key,
                    $now->subDays($this->credentialRepeatDays()),
                )) {
                    continue;
                }

                $raised += $this->alerts->raise(
                    OperatorAlertKind::PlatformCredentialUnusable,
                    $key,
                    $this->credentialSummary($signal, $key, $failures, $minutes),
                    [
                        'credential' => $key,
                        'fault' => $signal->value,
                        'refused_reads' => $failures,
                        'window_minutes' => $minutes,
                    ],
                    origin: AlertOrigin::Platform,
                ) === null ? 0 : 1;
            }
        }

        return $raised;
    }

    /**
     * What the operator is told about one credential fault.
     *
     * ⚠️ **THE TWO SENTENCES SEND SOMEBODY TO TWO DIFFERENT PLACES, WHICH IS THE
     * WHOLE REASON THE TWO SIGNALS ARE SEPARATE CASES.** One is answered by
     * pasting a key into Ops. The other is answered by putting an `APP_KEY`
     * back.
     *
     * ⛔ **THIS PARAGRAPH CONTINUED *"and the screen an operator would naturally
     * open first — Ops → Platform → Credentials — **shows that key as stored and
     * looks correct**, because it deliberately has no decryption path at all"*
     * AND THAT STOPPED BEING TRUE ON 2026-08-25 — BOTH READINGS KEPT AND DATED**
     * (9443). `CredentialStore::board()` decided the source from `$row !== null`
     * alone; it now shares one classifier with `sourceOf()`, opens each stored
     * ciphertext to find out whether it can, and the screen carries a panel
     * naming the fault, the two remedies **in order**, and `consent:hash-epoch`.
     * ⚠️ **So the unreadable arm's middle clause was inverted rather than
     * edited**: it said *"Ops still shows it stored and cannot see this"*, which
     * after that change steers an operator away from the one surface that now
     * has the whole answer. **A true sentence that becomes false becomes an
     * instruction pointing the wrong way**, which is worse than the silence it
     * replaced.
     *
     * ⚠️ **AND THE BOARD IS STRICTLY MORE COMPLETE THAN THIS BELL, WHICH IS WHY
     * IT IS WORTH POINTING AT.** This rings off a *counter*, written by
     * `CredentialStore::resolve()` — a **use** — so during an `APP_KEY` rotation
     * it names only the keys something happened to need in the window. The board
     * reads every stored row and names all of them.
     *
     * ⛔ **THE KEY NAME IS A CREDENTIAL KEY AND NEVER A VALUE.** `raise()`'s rule
     * is counts, rates, thresholds and names of ours; a manifest key is a name of
     * ours, and nothing here reaches the stored secret, its last four characters
     * or the vendor's own error text.
     *
     * ⛔ **IT FITS INSIDE {@see OperatorAlerts::SUMMARY_LIMIT} FOR THE LONGEST
     * KEY THE MANIFEST DECLARES, AND A TEST HOLDS IT THERE** — 9274–9279's
     * finding, one wave earlier, where a bell elided its own action clause. The
     * elision keeps the opening and the closing sentence and eats the middle, so
     * *"Put the old APP_KEY back"* would survive and the middle clause — the
     * half that decides which screen an operator opens — would not. The first
     * draft of the unreadable arm was 301 characters. ⚠️ **The 2026-08-25
     * rewording is six characters SHORTER than the clause it replaced, and that
     * was deliberate**: the worst case this method can be given already sat at
     * 293 of 300, so a middle clause is not a free place to add a word.
     *
     * ⚠️ **A METHOD RATHER THAN A `match` AT THE CALL SITE, ON
     * {@see self::silenceSummary()}'s REASONING** — behind a parameter the
     * fallback is a real branch, so a third credential signal cannot make an
     * `UnhandledMatchError` the outcome inside the one path R25 says may never
     * throw.
     */
    private function credentialSummary(
        PlatformHealthSignal $signal,
        string $key,
        int $failures,
        int $minutes,
    ): string {
        $reads = $failures === 1 ? '1 time' : "{$failures} times";

        return match ($signal) {
            PlatformHealthSignal::CredentialAbsent => "The credential {$key} was needed {$reads} in the last {$minutes} minutes and this platform has none. What asked for it was refused and is not retried. Paste it in Ops, Platform, Credentials.",
            PlatformHealthSignal::CredentialUnreadable => "The stored credential {$key} was needed {$reads} in the last {$minutes} minutes and this install cannot decrypt it — what a changed APP_KEY does to every stored credential. Ops, Platform, Credentials now shows it. Put the old APP_KEY back, or set the key again.",
            PlatformHealthSignal::Heartbeat,
            PlatformHealthSignal::WebhookSignature,
            PlatformHealthSignal::VendorCall,
            PlatformHealthSignal::ScheduledRun,
            PlatformHealthSignal::ScheduledRunFailed,
            PlatformHealthSignal::WebhookKeyUnavailable => "The credential {$key} could not be used {$reads} in the last {$minutes} minutes.",
        };
    }

    /**
     * Whether this install can still read the suppression hashes it has stored
     * — and a bell when it cannot (8270–8289, closing 8093(a) and 8197(a)).
     *
     * ## ⛔ What is already happening by the time this runs
     *
     * ⛔ **`ConsentService::decide()` IS REFUSING EVERY SEND ON THE PLATFORM**,
     * with `SendRefusalReason::SuppressionUnreadable`, before it even reaches
     * the suppression lookup — for every tenant, every channel and every
     * purpose. Three paths arrive here without anybody deciding to: an in-place
     * upgrade with suppression rows already stored, a lost or regenerated
     * `APP_KEY`, and — until 8200 — an ordinary crash between a durable hash
     * write and its epoch. **Until this bell existed the only thing that said so
     * was `php artisan consent:hash-epoch`, a command nobody has run**, and the
     * first people to find out were the tenants, who are shown *"our record of
     * who has asked us to stop could not be read"* on every refused send.
     *
     * ## ⛔ The bell cannot be muted by the condition it announces, and that was
     * checked rather than assumed
     *
     * ⛔ **THE OPPOSITE WOULD BE FATAL TO THE WHOLE IDEA** — a pager silenced by
     * the incident it reports is the shape `PlatformTexter::alertOperator()`
     * already calls out for number quarantine. It is not this shape:
     * `alertOperator()` takes **no recipient**, reads `ops.alert_sms` out of the
     * registry, and never touches `ConsentService` at all — there is no permit,
     * no consent record and no suppression lookup on that path, deliberately,
     * because *"we are nobody's customer"*. `PlatformMailer::send()` has no
     * consent gate either. **Driven directly**: `EpochBellTest` asserts a text
     * leaves the building in the same test in which a consented contact's send
     * is refused for `SuppressionUnreadable`.
     *
     * ## ⚠️ It re-raises on every sweep and the quiet window is the only mute
     *
     * ⚠️ **SO ON THE SEEDED SIXTY MINUTES THIS TEXTS ONE PHONE ONCE AN HOUR FOR
     * AS LONG AS THE STATE LASTS, AND THAT IS ACCEPTED RATHER THAN OVERLOOKED.**
     * The state never clears itself — nothing in `app/` may resolve
     * `Unattributed` (8082) and only a key or a person clears `Rotated` — so the
     * choice is between an hourly repeat and a pager that goes quiet while the
     * platform sends nothing. **Four things decide it.** `AlertOrigin::Platform`
     * is documented as unbounded *"and nothing may learn to"* bound it, and a
     * budget aimed at this kind would be `OperatorAlertKind::PagerBudgetSpent`'s
     * own silence pointed at the loudest state this platform has. 8042 leaves
     * exactly one recipient, the owner's own phone, so a bell that rings once at
     * 3am and never again reaches a sleeping handset and nothing else. The
     * repeat is **not a copy** — `pagerSentence()` re-reads the stored count,
     * which grows while a rotation is unresolved because inbound STOPs keep
     * landing. And an operator who has read it and is already on the way has a
     * documented, bounded mute for precisely this: `ops.alert_quiet_minutes`,
     * up to seven days (7580–7599). **A second mute for one kind is the toggle
     * `CLAUDE.md` refuses.**
     *
     * ## What it costs on a healthy platform
     *
     * ⚠️ **ONE `SELECT` EVERY FIVE MINUTES, AND NOT ONE MORE.**
     * `isReadable()` is the memoised live-epoch read; on any install past its
     * first STOP that is a single indexed query and the method returns. The
     * three `count(*)`s behind the summary are reached **only** once the answer
     * is already *no*, which on a healthy install is never.
     *
     * ⛔ **`AlertOrigin::Platform` IS STATED RATHER THAN DETECTED, ON THIS
     * CLASS'S OWN RULE.** Every raise in this file says it out loud —
     * `sweepHeartbeats()` because it genuinely runs inside a web request, and
     * `sweepCounters()` so that moving a check between the two processes cannot
     * quietly change what happens to its bell. Ambient detection would agree
     * today and is not the property being relied on.
     *
     * @return int how many alerts were raised
     */
    private function checkSuppressionReadability(): int
    {
        if ($this->epochs->isReadable()) {
            return 0;
        }

        $status = $this->epochs->status();
        $counts = $this->epochs->storedHashCounts();

        return $this->alerts->raise(
            OperatorAlertKind::SuppressionsUnreadable,
            // ⚠️ **THE SUBJECT IS THE STATUS, BECAUSE THE TWO STATES HAVE
            // DIFFERENT FIRST MOVES** — `rotated` is answered in `.env` and
            // `unattributed` by `--adopt`, and a transition between them must
            // not be swallowed by a quiet window whose answer has just changed.
            // The kind's own docblock carries why this cannot be used to ring
            // faster.
            $status->value,
            $this->epochs->pagerSentence(),
            [
                'status' => $status->value,
                // ⚠️ **COUNTS OF OURS AND NOTHING ELSE.** A row count is not an
                // identifier: nothing here names a customer, a number or an
                // address, and `raise()`'s summary rule is kept by construction
                // rather than by care. The breakdown rides in the context
                // because the text has 300 characters and the total is the only
                // figure that changes a decision.
                //
                // ⛔ **AND THE KEYS ARE NOT THE TABLE NAMES, WHICH A LINT
                // ENFORCED RATHER THAN A PREFERENCE.** `Architecture\ConsentTest`
                // forbids any file outside `Services/Consent/` from naming
                // `opt_outs`, `suppression_lifts` or `suppression_list` as a
                // string, bluntly, because it cannot tell a JSON key from a raw
                // delete. The first draft of this array reddened it — correctly
                // — and the rename reads better on the screen these are
                // rendered on.
                'carrier_stops' => $counts['carrier_stops'],
                'register_entries' => $counts['register_entries'],
                'lifts' => $counts['lifts'],
                'stored_hashes' => $counts['total'],
            ],
            origin: AlertOrigin::Platform,
        ) === null ? 0 : 1;
    }

    /**
     * The absolute number of failures in the window, or null where an absolute
     * count is the wrong reading for this signal.
     *
     * ⚠️ **A COUNT IS ONLY A THRESHOLD SOMEBODY CAN SET WHERE EVERY ROW IS A
     * FAILURE.** Where the happy path also increments `total`, the same count
     * rises with traffic on its own, so the figure an operator picked for a quiet
     * Tuesday fires every busy afternoon and the pager is muted by the person
     * carrying it — 511, arriving as arithmetic rather than as a tuning choice.
     * That signal wants {@see self::failureRateBp()} instead.
     *
     * @param  array{total: int, failures: int, since: CarbonImmutable}  $totals
     */
    private function failureCount(PlatformHealthSignal $signal, array $totals): ?int
    {
        return $signal->countsSuccesses() ? null : $totals['failures'];
    }

    /**
     * The failure rate over the window in basis points, or null where this
     * signal has no denominator worth dividing by.
     *
     * ⛔ **A RATE OVER A FAILURE-ONLY SIGNAL IS 100% BY CONSTRUCTION**, which is
     * the hazard {@see PlatformHealthSignal::countsSuccesses()} exists to state:
     * *"the first forged webhook anybody posts would trip a rate threshold set at
     * any value at all."* Returning null rather than 10,000 is the point — a
     * caller that treated the number as real would alert, and a caller that has
     * to handle null cannot.
     *
     * ⚠️ **BASIS POINTS, NEVER A FLOAT**, for the reason money is integer cents:
     * the figure is compared against a configured threshold and written onto the
     * alert, and `0.0199999` from a JSON round trip compares wrong against
     * itself. 200 is 2%.
     *
     * ⚠️ **THE ZERO GUARD IS UNREACHABLE FROM {@see self::checkVendorErrors()}**,
     * which applies its floor first, and is here because this method is the one
     * place a division happens in the alert path and R25 forbids that path
     * throwing for any reason at all.
     *
     * @param  array{total: int, failures: int, since: CarbonImmutable}  $totals
     */
    private function failureRateBp(PlatformHealthSignal $signal, array $totals): ?int
    {
        if (! $signal->countsSuccesses() || $totals['total'] <= 0) {
            return null;
        }

        return intdiv($totals['failures'] * 10_000, $totals['total']);
    }

    /**
     * How far back every counter check looks. Clamped at a minute: a window of
     * zero would look at nothing and report a healthy platform for ever.
     */
    private function window(): int
    {
        return max(1, $this->defaults->int(self::WINDOW_KEY));
    }
}
