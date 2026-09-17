<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AlertOrigin;
use App\Enums\AutomationRunStatus;
use App\Enums\AutopilotActionType;
use App\Enums\OperatorAlertKind;
use App\Models\AutomationRun;
use App\Models\Location;
use App\Services\ActivityService;
use App\Services\Ops\OperatorAlerts;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use App\Support\MailFailure;
use App\Support\QueueBackoff;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The contract every automation runs under.
 *
 * `29` §2 rule 40: every job idempotent, location-scoped, retried with backoff,
 * and gated on toggles plus kill switches **before any side effect**. Rule 44:
 * every automation implements both execute() and handoff(), in the same class,
 * so the review engine runs with zero GBP API access. Retrofitting either across
 * 142 automations is not work anyone should sign up for, which is why this class
 * exists before the first automation rather than after the tenth.
 *
 * The order in handle() is the whole point, and it is not arbitrary:
 *
 *   1. establish the tenant      — a scheduled job starts with no context at all
 *   2. check the gates           — before anything observable happens
 *   3. claim the idempotency key — the database decides, not this process
 *   4. open the run row
 *   5. execute() or handoff()
 *   6. close the row, always
 *
 * Steps 2 and 3 are before step 5 on purpose. A gate checked after the side
 * effect is not a gate, and an idempotency key claimed after the work is a
 * record of the duplicate rather than a defence against it.
 *
 * @see docs/17-TIER1-EXECUTABLE-TICKETS.md FOUND-05
 */
abstract class AutopilotJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * What a run row says when the location it names has been deleted.
     *
     * A constant rather than two literals because it is written from two
     * places — {@see self::recordMissingLocation()} and the degraded arm of
     * {@see self::recordSkip()} — and read by whoever is reconstructing a
     * week's silence from the database. Two spellings of one condition is how
     * that reconstruction quietly misses half of its rows.
     */
    public const string LOCATION_MISSING = 'location missing';

    /**
     * What a run row says when the process running it was killed mid-flight.
     *
     * A constant for {@see self::LOCATION_MISSING}'s reason — it is written from
     * {@see self::closeAbandonedRun()} and read by whoever is reconstructing why
     * an automation stopped happening for one subject. ⚠️ **It is also the query
     * that answers *"which subjects is this platform still holding a claim
     * on?"*** — `output->>'reason' = 'run abandoned'` beside
     * `output->>'claim_held' = 'true'`, which is the only enumeration of that
     * population there is.
     */
    public const string RUN_ABANDONED = 'run abandoned';

    /**
     * How long this platform waits before saying the same thing about the same
     * automation twice (9700–9719).
     *
     * ⛔ **A REPEAT INTERVAL, NEVER AN ARMING THRESHOLD.** Nothing here decides
     * whether {@see self::failed()} may ring: the first automation to run out of
     * retries rings it, at any volume, on any install. This decides only when the
     * platform is willing to say it again — and it has to, because the fault
     * behind an abandoned automation is usually a **standing** one. A tenant's
     * expired credential is wrong for every dispatch, and `SendReviewInviteJob`
     * is dispatched per customer: with the seeded sixty-minute quiet window as
     * the only memory, a five-day fault is a hundred and twenty texts about one
     * thing somebody already knows, which is decision 511 with a handset
     * attached.
     *
     * ⚠️ **A DAY, AND THE ARGUMENT IS `OperatorAlerts::MAIL_PATH_REPEAT_HOURS`'
     * RATHER THAN `PlatformHealthChecks::CREDENTIAL_REPEAT_DAYS`' THIRTY.** A
     * month of quiet would have spoken on day one of a five-day incident and on
     * none of the other four. ⚠️ **And what makes a day affordable here is
     * narrower than what made it affordable there**: the mail bell's gate drops
     * a fact that lives nowhere but `failed_jobs`, and this one drops a fact that
     * `automation_runs` holds under the tenant it happened to, per attempt, for
     * as long as that table is kept. **The pager is losing a repeat, not a
     * record.**
     *
     * ⛔ **IN CODE RATHER THAN IN THE REGISTRY**, on `OperatorAlerts::PUSH_BUDGET_HOURS`'
     * argument: an operator handed the box is handed the ability to set it to a
     * year, which on a bell with no threshold is the same edit as switching it
     * off.
     *
     * ⚠️ **IT LIVES HERE RATHER THAN ON `OperatorAlerts`, WHICH IS THE OPPOSITE
     * OF WHERE THE MAIL BELL'S SIBLING ENDED UP AND FOR A REASON THAT DOES NOT
     * TRANSFER.** That constant moved to the pager because two build-failing
     * chokepoints — `EmailMeteringTest`'s *"the mail transport is reachable only
     * through the mailer that meters"* and `OutboundTest`'s *"canDeliver() is not
     * the guard"* — forbid any file in `app/` outside `PlatformMailer` from
     * naming `DeliverPlatformMail` at all, and `Admin\OperatorAlertBoard` has to
     * print the figure. **No lint covers this class**, so it stays beside
     * {@see self::$tries} and {@see self::backoff()}, which are the two figures
     * that decide when it is reached — `PlatformHealthChecks::CREDENTIAL_REPEAT_DAYS`'
     * placement, and 7661's rule that a screen reads a figure off the class that
     * applies it.
     */
    public const int ABANDONED_REPEAT_HOURS = 24;

    /**
     * Retried with backoff and jitter.
     *
     * Jitter matters more than the base delay: without it, a provider outage
     * that fails 400 location-scoped jobs at once retries all 400 in lockstep,
     * which is a thundering herd aimed at a service already in trouble.
     */
    public int $tries = 3;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $businessId,
        public readonly ?int $locationId = null,
    ) {}

    /**
     * A stable name for this automation, matching the catalog in `16`.
     */
    abstract public function automationKey(): string;

    /**
     * The full-capability path — provider APIs available.
     *
     * @return array<string, mixed>|null Output recorded on the run row.
     */
    abstract protected function execute(): ?array;

    /**
     * The same outcome without provider access.
     *
     * Not a stub and not a degraded apology: the whole review engine must run
     * with the GBP API disabled, so this path is expected to *work* — typically
     * by producing something the owner can act on by hand.
     *
     * @return array<string, mixed>|null
     */
    abstract protected function handoff(): ?array;

    /**
     * Whether the full path is available right now.
     *
     * Default is optimistic; automations that depend on a specific provider
     * override this and check that provider's health.
     */
    protected function canExecute(): bool
    {
        return true;
    }

    /**
     * A key that identifies this unit of work, or null for "always run".
     *
     * Null is correct for genuinely repeating work — a nightly sweep is meant to
     * run every night. PostgreSQL does not collide nulls in a unique index, so
     * those runs coexist.
     */
    protected function idempotencyKey(): ?string
    {
        return null;
    }

    /**
     * Whether this attempt did the thing the key exists to protect.
     *
     * ⚠️ A KEYED JOB CANNOT BE RETRIED UNLESS IT ANSWERS THIS, AND THAT IS THE
     * WHOLE POINT OF THE METHOD. `handle()`'s catch closes the run as `Failed`
     * and rethrows so the queue retries — but the row it just wrote still holds
     * the unique `idempotency_key`, so the retry's `claimRun()` collides with
     * **its own failed attempt**, returns null, and `handle()` returns before
     * `execute()`. The ladder burns every attempt without running any of them,
     * and the run rows say `failed` three times, which reads exactly like a job
     * that tried and could not succeed.
     *
     * ⚠️ THE DEFAULT IS `true` — KEEP THE CLAIM — AND IT IS THE UNHELPFUL ONE ON
     * PURPOSE. There is no safe default here, which is decision 502's situation:
     * releasing by default means a job that did something irreversible and then
     * threw gets retried and does it **twice**, and on this product the
     * irreversible thing is a message to somebody else's customer. Losing a send
     * is recoverable; sending twice is not. So the base class refuses to guess in
     * the direction that cannot be taken back, and an `ArchitectureTest` lint
     * requires every job that declares an `idempotencyKey()` to answer this
     * explicitly — the build fails rather than the default being inherited
     * silently, which is the failure `SendReviewInviteJob` shipped with.
     *
     * Unkeyed jobs never reach the collision — PostgreSQL does not collide nulls
     * in a unique index — so the release below is a no-op for them and this
     * default costs them nothing.
     */
    protected function claimIsSpent(): bool
    {
        return true;
    }

    /**
     * Hand the key back so a retry can get past `claimRun()`.
     *
     * ⚠️ NULLING THE KEY RATHER THAN DELETING THE ROW. The failed run is the
     * exhaustive record and 364's recovery sweep counts it; deleting it would
     * erase the evidence of the attempt and quietly reset the budget. Nulls do
     * not collide in the unique index, so a released row stops blocking without
     * going anywhere.
     *
     * ⚠️ SCOPED TO THIS AUTOMATION *AND* THIS KEY. Either predicate alone is
     * wrong: without the key it releases every claim the automation holds, and
     * without the automation key it releases another automation's claim that
     * happens to share a string.
     */
    private function releaseUnearnedClaim(): void
    {
        if ($this->idempotencyKey() === null) {
            return;
        }

        AutomationRun::query()
            ->where('automation_key', $this->automationKey())
            ->where('idempotency_key', $this->idempotencyKey())
            ->update(['idempotency_key' => null]);
    }

    /**
     * Per-automation toggle. Checked before any side effect.
     *
     * ⛔ **NOTHING IN `app/` OVERRIDES THIS, SO THE SENTENCE ABOVE DESCRIBES A
     * GATE THAT CANNOT SAY NO — MEASURED 2026-08-23 (8560).** Twenty-six classes
     * extend `AutopilotJob` and every one of them inherits this `true`, so the
     * `recordSkip('toggle off')` branch in {@see self::handle()} is unreachable
     * from this application. The only override in the repository is
     * `tests/Support/ProbeAutopilotJob.php`.
     *
     * ⚠️ **THE ONE-LINE DESCRIPTION IS KEPT RATHER THAN DELETED**, because it is
     * the *contract* a subclass implements against; what was missing is that
     * nobody has. `29` §2 rule 40 asks for the gate and this class provides the
     * hook — the rule is unsatisfied at the call sites, not here.
     *
     * ⛔ **A GREEN TEST STANDS OVER THE DEAD BRANCH AND IS NOT WRONG TO.**
     * `AutopilotJobTest`'s *"a disabled toggle stops the job before any side
     * effect"* passes, and its four-row skip dataset lists `'toggle off'`
     * alongside `'kill switch'`, `'tenant suspended'` and `'tenant paused'` —
     * three gates that are live and correct. **That is what makes the fourth
     * read as live** (398: an outer guard answers first, so nobody notices the
     * inner one never does), and it is `sending_health_windows`' shape — a test
     * passing perfectly against a control nothing drives.
     *
     * ⚠️ **THIS IS NOT "NO AUTOMATION HONOURS A TOGGLE".** `auto_reply`,
     * `send_review_requests`, `update_review_hub` and `full_auto_post_replies`
     * are all read — in subclass {@see self::canExecute()}, which produces a
     * **handoff**: a different `automation_runs.status`, a different row, and a
     * different thing on an operator's screen from a skip. They are not
     * interchangeable, and rule 40 asks for the skip.
     *
     * ⛔ **DECISION 1958 ALREADY REASONED ON TOP OF THIS HOOK** — *"the sole
     * subclass hook that reaches [`recordSkip()`] is `isEnabled()`"* — and
     * refused to route a deliverability refusal through it for reasons that
     * never included *it has no overrides anywhere*. 2660's shape: a ruling with
     * no code reads exactly like a ruling with one.
     *
     * ⛔ **DO NOT OVERRIDE THIS TO "FIX" IT.** Wiring a per-automation toggle
     * changes the behaviour of twenty-six automations at once and needs the
     * ruling at 8560 first. `tests/Feature/ConfirmIsUnbuiltTest.php` reddens
     * when an override appears, and its message says so.
     */
    protected function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [
            $this->jittered(60),
            $this->jittered(300),
            $this->jittered(900),
        ];
    }

    /**
     * The queue has stopped trying, and this is the only place that fact exists.
     *
     * ## ⛔ Why a hook, when `handle()` already writes a `Failed` run row
     *
     * ⛔ **BECAUSE THAT ROW IS AN ATTEMPT AND THIS IS A SURRENDER, AND NOTHING
     * IN THE SCHEMA TELLS THEM APART.** {@see self::handle()}'s `catch` closes
     * the row `Failed` and **rethrows so the queue retries**, so a dispatch that
     * failed once and succeeded on the second attempt leaves a `Failed` row of
     * exactly the same shape as one that died. `automation_runs` carries no
     * attempt number, no correlation between the attempts of one dispatch and no
     * terminal flag — so a sweep, a count or a badge built over
     * `AutomationRunStatus::Failed` would report recovered work as lost work, on
     * a platform where recovering on retry is the ordinary case. **The reader
     * this population was missing could not be built over that column**; what was
     * missing was the fact the column cannot hold, and the framework hands it
     * over here for free.
     *
     * ⛔ **AND THIS IS ALSO THE ONLY REPORT FOR THE ARM THAT WRITES NO ROW AT
     * ALL.** `handle()` writes nothing until `claimRun()` returns: a throw from
     * `Tenancy::set()`, from `killSwitchThrown()`, from the suspension read or
     * from the pause read fails the job with **nothing anywhere in
     * `automation_runs`** — the same total silence as a job that has no base
     * class at all, inside the population that is supposed to be covered.
     * {@see self::recordMissingLocation()} closes one named instance of that
     * (6686); this closes the rest without having to enumerate them.
     *
     * ## ⚠️ What it is not
     *
     * ⛔ **NOT A SECOND RECORD OF THE FAILURE.** No table, no column, no counter:
     * the run row is written where it can be written, `failed_jobs` holds the
     * payload and the trace, and this composes a sentence out of what both
     * already say. **A seeded figure with no reader is a row in a table**
     * (`CLAUDE.md`), and its inverse is a counter minted to feed one bell.
     *
     * ⛔ **NOT A THRESHOLD**, on {@see OperatorAlertKind::AutomationAbandoned}'s
     * own argument: a count of failures is a count of traffic, and one tenant's
     * whole day of automation never reaches `ops.failed_job_spike`'s
     * twenty-five in an hour — which is how a totally stopped mail path sat
     * unread for five days (9370).
     *
     * ## ⚠️ What may never travel out of here
     *
     * ⛔ **NOT `$exception->getMessage()`, ON 9378's RULE AND FOR A WIDER REASON
     * THAN THE MAIL BELL HAD.** That value is rendered on the alert board, spread
     * into a `critical` log line, emailed and **texted**. A `MailNotDeliverable`
     * message is written in this repository; an automation's exception is written
     * by whatever it was talking to, and on this population that is a carrier
     * rejecting a customer's mobile number, a provider quoting a review's text
     * back, or a decoder naming the field it choked on. **The short class name
     * carries the diagnosis a person needs; the message carries somebody else's
     * customer.** The trace is in `failed_jobs`, behind a login, where it belongs.
     *
     * ## ⚠️ Origin is stated rather than detected
     *
     * ⚠️ **`AlertOrigin::Platform`, ON `DeliverPlatformMail::failed()`'s
     * REASONING.** Under `QUEUE_CONNECTION=sync` this runs inside whatever
     * request dispatched the job, so `AlertOrigin::detected()` would read an
     * owner clicking a button as the ringer and bound the push against the
     * per-kind daily budget. ⚠️ **And the bound that is already here is tighter**
     * — {@see self::ABANDONED_REPEAT_HOURS} allows one push per automation per
     * day, where the budget allows ten — so declaring this `Platform` costs
     * nothing a flood could exploit.
     *
     * ## ⛔ Contained, because a bell may never be a brake
     *
     * ⛔ **R25.** Nothing in here may throw into the worker: a queue that failed a
     * job and then died reporting it would lose the `failed_jobs` row this
     * paragraph is relying on. {@see OperatorAlerts::raise()} already returns
     * rather than throws, and the `catch` below covers the two calls in front of
     * it — the container resolution and the `rangSince()` read.
     */
    final public function failed(?Throwable $exception): void
    {
        $this->closeAbandonedRun($exception);

        $this->runAbandonmentHook($exception);

        try {
            $alerts = app(OperatorAlerts::class);

            if ($alerts->rangSince(
                OperatorAlertKind::AutomationAbandoned,
                $this->automationKey(),
                CarbonImmutable::now()->subHours(self::ABANDONED_REPEAT_HOURS),
            )) {
                return;
            }

            $alerts->raise(
                OperatorAlertKind::AutomationAbandoned,
                $this->automationKey(),
                self::abandonedSummary($this->automationKey(), $this->businessId),
                [
                    'automation' => $this->automationKey(),
                    // ⚠️ **OUR OWN ACCOUNT NUMBER, WHICH IS THE ACTIONABLE PART
                    // AND IS NOT PERSONAL DATA.** Four kinds already carry a
                    // business id as their whole subject; this one carries it in
                    // `context` instead, because the subject is what the dedupe
                    // is keyed on and keying it here is what would flood the
                    // pager — see the kind's own docblock.
                    'business_id' => $this->businessId,
                    'location_id' => $this->locationId,
                    // ⛔ **THE CLASS, NEVER THE MESSAGE.** See the paragraph
                    // above; `null` where the queue failed the job without one,
                    // which `Job::fail()` permits.
                    'exception' => $exception === null ? null : $exception::class,
                    'repeat_quiet_hours' => self::ABANDONED_REPEAT_HOURS,
                ],
                origin: AlertOrigin::Platform,
            );
        } catch (Throwable $e) {
            Log::error('an abandoned automation could not be reported to the operator', [
                'automation' => $this->automationKey(),
                'business_id' => $this->businessId,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * Close the run row this dispatch left open when it was killed mid-flight.
     *
     * ## ⛔ Why this exists at all
     *
     * ⛔ **`handle()`'s `catch` AND `finally` DO NOT RUN WHEN A WORKER TIMES
     * OUT.** `Worker::registerTimeoutHandler()` installs a `SIGALRM` handler that
     * calls `markJobAsFailedIfItShouldFailOnTimeout()` and then `kill()`, which
     * `exit`s the process from inside the signal handler — no unwinding, no
     * `finally`. {@see self::$failOnTimeout} is `true` here, so the *failure* is
     * reported (that is why this method can exist), and the row `claimRun()`
     * opened is left at {@see AutomationRunStatus::Running} for ever. Production
     * runs `--timeout=60` and `config('ai.timeout')` defaults to **60**, so every
     * AI-backed automation on this platform is one slow provider response away
     * from this. Decision 9713 found it and deferred it; 9960 is the fix.
     *
     * ## ⚠️ Why it can find the row, when 9713 said nothing could
     *
     * `CallQueuedHandler::failed()` rebuilds the job by `unserialize()`ing the
     * payload (9714), so the `AutomationRun` object is gone — **but it also calls
     * `setJobInstanceIfNecessary()`**, so `$this->job` is the real queue job and
     * `$this->job->uuid()` is the payload uuid that `claimRun()` wrote onto the
     * row. That is the correlation column 9713 said was missing.
     *
     * ⚠️ **THE TUPLE WOULD NOT HAVE DONE.** `business_id + automation_key +
     * idempotency_key` is unique and is readable here, so it looks like it could
     * stand in — and it closes the wrong row twice over. It addresses nothing at
     * all for the six automations that run unkeyed; and where a dispatch was the
     * one that *lost* the claim race, it names the row belonging to the process
     * that is still running, which this method would then close underneath it.
     *
     * ## ⛔ What it deliberately does not do: hand the claim back
     *
     * ⛔ **THE `idempotency_key` STAYS ON THE ROW, AND THAT IS A RULING RATHER
     * THAN AN OMISSION** (9962). The obvious fix is to run the `finally` from
     * here — `if (! $this->claimIsSpent()) { $this->releaseUnearnedClaim(); }` —
     * and it is wrong for a measured reason: **most `claimIsSpent()`
     * implementations read a flag `handle()` set on `$this`**
     * (`SendReviewInviteJob::$claimSpent`, `EscalateUrgentThreadJob::$paged`,
     * `AnalyzeReviewJob::$verdictPersisted`), and on the rebuilt object every one
     * of those is back at its constructor default of `false`. So asking here
     * returns *"nothing happened"* for the exact population where the truth is
     * *"nobody knows"* — and acting on it means a second text to somebody else's
     * customer. `ReviewInviteSender`'s own docblock (7067) settled this axis:
     * the claim goes back *"only when the transport can prove nothing left the
     * machine"*, and a killed process proves nothing about anything.
     *
     * ⚠️ **SO THE COST IS REAL AND IS NOW WRITTEN DOWN INSTEAD OF INVISIBLE.**
     * `output.claim_held` records it per row, {@see AutomationRunStatus::Abandoned}
     * is filterable on the Ops console, and 9963 carries what is owed: the
     * decision is the *subclass's* to make, one at a time, by an author who knows
     * whether re-doing that automation's work is recoverable.
     *
     * ## ⚠️ Contained, and tenanted on purpose
     *
     * ⛔ **R25 AGAIN: NOTHING HERE MAY THROW INTO THE WORKER.** A queue that
     * failed a job and then died writing a row about it would lose the
     * `failed_jobs` entry, which is the record everything else in `failed()`
     * relies on.
     *
     * ⚠️ **`Tenancy::actingAs()` RATHER THAN TRUSTING THE CONTEXT.** On the
     * timeout path this runs inside `handle()`'s own tenancy and the wrapper is
     * redundant; on the retries-exhausted path it runs after a `catch` that may
     * have been reached from anywhere, and on `sync` it runs inside whatever
     * request dispatched the job. `automation_runs` is FORCE ROW LEVEL SECURITY,
     * so an unset tenant matches nothing and a *wrong* one matches somebody
     * else's rows — RLS catches the first and never the second (`CLAUDE.md`).
     * Naming the business is what makes the second impossible.
     */
    private function closeAbandonedRun(?Throwable $exception): void
    {
        $uuid = $this->jobUuid();

        if ($uuid === null) {
            return;
        }

        try {
            Tenancy::actingAs($this->businessId, function () use ($uuid, $exception): void {
                AutomationRun::query()
                    ->where('job_uuid', $uuid)
                    ->where('status', AutomationRunStatus::Running)
                    ->get()
                    ->each(function (AutomationRun $run) use ($exception): void {
                        $run->update([
                            'status' => AutomationRunStatus::Abandoned,
                            // ⛔ **THE CLASS, NEVER THE MESSAGE** — {@see self::failed()}'s
                            // rule. An automation's exception message is written by
                            // whatever it was talking to, and on this population that
                            // is a carrier, an AI provider, a decoder or a mail server.
                            //
                            // ⚠️ **THIS SAID *"IT BINDS HARDER HERE THAN THERE: THIS
                            // COLUMN IS RENDERED TO STAFF"* AND THE COLUMN IS NOT
                            // RENDERED ANYWHERE** — 11331. `Admin\AutomationRuns`
                            // ends its column list at `output` and its own docblock
                            // refuses `error` by name; nothing else in `app/` reads it.
                            // **The exposure is storage rather than rendering**, which
                            // lowers the severity and removes none of the reason: the
                            // row outlives the fault, and `PruneAutomationRuns` deletes
                            // nothing at all until an operator sets a period.
                            //
                            // ✅ **AND `handle()`'s `catch` IS NOW THE SAME RULE.**
                            // This comment used to point at it as a defect owed to
                            // another slice; that slice was wave 43 lane B.
                            'error' => $exception === null ? null : $exception::class,
                            'output' => [
                                'reason' => self::RUN_ABANDONED,
                                // ⚠️ **READ OFF THE ROW, NEVER FROM `idempotencyKey()`.**
                                // The method answers what this dispatch *would* claim; the
                                // column answers what is actually blocking the subject
                                // right now, which is the question an operator has.
                                'claim_held' => $run->idempotency_key !== null,
                            ],
                            'finished_at' => now(),
                        ]);
                    });
            });
        } catch (Throwable $e) {
            Log::error('an abandoned automation\'s run row could not be closed', [
                'automation' => $this->automationKey(),
                'business_id' => $this->businessId,
                'job_uuid' => $uuid,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * The queue payload's own uuid, or null when there is no queue job.
     *
     * ⚠️ **STABLE ACROSS RETRIES AND SHARED WITH `failed_jobs.uuid`.**
     * `Job::uuid()` reads `payload()['uuid']`, a release re-pushes the same
     * payload, and `DatabaseUuidFailedJobProvider` logs that same value — which
     * is what makes `automation_runs.job_uuid` a join to the failure record
     * rather than only a self-reference. It is therefore **not** unique per row:
     * three attempts of one dispatch open three rows carrying it.
     *
     * ⚠️ **NULL IS ORDINARY AND MUST STAY HARMLESS.** `$this->job` is unset when
     * a job's `handle()` is called directly — which several tests in this
     * repository do deliberately — and `FakeJob` has a uuid of its own under
     * `Queue::fake()`. Nothing may depend on this being present.
     */
    private function jobUuid(): ?string
    {
        $uuid = $this->job?->uuid();

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    /**
     * Whatever this automation owes its own subject when the queue gives up.
     *
     * ⛔ **THIS EXISTS BECAUSE {@see self::failed()} IS `final`, AND THE `final`
     * IS THE POINT.** A subclass declaring its own `failed()` shadows the base's
     * entirely — and one already did. `Voice\FetchVoicemailRecordingJob` has had
     * a `failed()` since it shipped, marking the voicemail unavailable so the
     * owner is told something rather than waiting for audio that will never
     * arrive; it is correct and it calls no parent. **So the day the bell landed
     * on the base class, exactly one of the twenty-six automations would have
     * opted out of it silently** — no lint red, no column writerless, no method
     * uncalled, and nothing on any diff to see. That is
     * `docs/FAILURE-SHAPES.md`'s *a hand-typed subset waiting to happen*, and the
     * remedy it names is to make the subset **unrepresentable** rather than merely
     * absent.
     *
     * ⚠️ **IT IS {@see self::handle()}'s OWN SHAPE, RATHER THAN A NEW ONE.** That
     * method is `final` too and hands subclasses {@see self::execute()} and
     * {@see self::handoff()}; this is the same trade at the other end of the
     * lifecycle — the base keeps what must always happen, the subclass gets a
     * named place for what only it knows.
     *
     * ⛔ **AND IT RUNS BEFORE THE BELL, CONTAINED.** The subclass's work is what
     * makes the **product** right for the owner; the bell is what makes it right
     * for us. So the owner's half goes first, and a throw from it is logged
     * rather than allowed to swallow the page — otherwise a broken cleanup would
     * silence the very report that a job has died, which is R25 inverted.
     *
     * ⚠️ **A DEFAULT NO-OP WITH ONE OVERRIDE IS NOT {@see self::isEnabled()}'s
     * SHAPE** (8560). That one is a *gate* that no subclass ever answers `false`
     * to, so a branch of `handle()` is unreachable from this application. This is
     * not a gate: nothing downstream turns on the answer, the base's own work runs
     * either way, and one real override exists today.
     */
    protected function onAbandoned(?Throwable $exception): void
    {
        //
    }

    /**
     * Run {@see self::onAbandoned()} without letting it cost the page.
     */
    private function runAbandonmentHook(?Throwable $exception): void
    {
        try {
            $this->onAbandoned($exception);
        } catch (Throwable $e) {
            Log::error('an abandoned automation\'s own cleanup threw', [
                'automation' => $this->automationKey(),
                'business_id' => $this->businessId,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * One sentence, safe in a text message, opening with the automation.
     *
     * ⚠️ **THE SUBJECT REACHES A HANDSET ONLY BECAUSE THE SUMMARY OPENS WITH
     * IT** — `OperatorAlerts::MIN_OPENING`'s reasoning: the text is
     * `headline().': '.$summary` and nothing else, so `operator_alerts.subject`
     * is invisible on the channel this bell is read on.
     *
     * ⛔ **IT PROMISES NO RUN ROW, WHICH IS THE ONE CLAIM IT MUST NOT MAKE.** A
     * draft closed *"the failed run is recorded against that account"*, and the
     * arm this hook exists to cover most is precisely the one where `claimRun()`
     * was never reached and no row exists. That is 314–316 in a sentence an
     * operator acts on at 3am — sent to a screen to read a row that is not there.
     * **It says where to look, not what will be found.**
     *
     * ⚠️ **AND IT SAYS THE ACCOUNT IS THE FIRST RATHER THAN THE ONLY ONE**,
     * because the dedupe is keyed on the automation: two hundred abandoned
     * invites across forty accounts raise this once, naming one of them.
     */
    private static function abandonedSummary(string $automation, int $businessId): string
    {
        return $automation.' ran out of retries, so work it was doing is abandoned and nothing '
            .'picks it up. First seen on account '.$businessId.'; others hitting the same fault '
            .'are not paged separately. Quiet about this automation for '
            .self::ABANDONED_REPEAT_HOURS.'h. Read that account\'s automation runs.';
    }

    final public function handle(): void
    {
        // A job dispatched from a request inherits its tenant through Context.
        // One dispatched from the scheduler or a console command inherits
        // nothing, so establishing it here is not belt-and-braces — it is the
        // only thing standing between a scheduled automation and
        // TenantNotResolved.
        Tenancy::set($this->businessId);

        if ($this->killSwitchThrown()) {
            $this->recordSkip('kill switch');

            return;
        }

        // ⚠️ THE TENANT'S OWN STOP, AND IT IS A SECOND CHECK RATHER THAN A
        // WIDENING OF THE ONE ABOVE. That one is ours: config-only, global, and
        // deliberately not database-backed, because a kill switch that needs a
        // working database is not much of a kill switch. This one is the
        // owner's, it is per-business, and per-business state can only live in
        // the database — so they cannot be one check, and blurring them would
        // either put a database read in front of our emergency stop or make the
        // owner's stop unhonourable. Decision 820.
        //
        // Second rather than first for the same reason: ours must be answerable
        // when theirs cannot be read at all.
        //
        // ⚠️ AND IT IS A SKIP, NEVER A FAILURE. `recordSkip()` closes the run as
        // `skipped`, which decision 364 deliberately excludes from the recovery
        // budget — so a fortnight of paused analysis is swept up on resume
        // rather than abandoned, and failing here instead would rebuild 356 and
        // 364's stranding bug for every tenant who ever presses the button.
        // ⚠️ OUR STOP FOR CAUSE, AND IT IS A THIRD CHECK RATHER THAN A WIDER
        // SECOND ONE. `28` §9.5's Suspend is not the owner's pause with a
        // different actor: it is applied against a tenant, cleared only by
        // `super_admin`/`ops_admin`, and it must keep answering when the owner
        // has no pause set at all. Folding the two would mean lifting a
        // suspension silently started an account its owner had paused.
        //
        // ⚠️ BEFORE THE PAUSE, AND THE ORDER DECIDES WHAT `automation_runs`
        // SAYS. A tenant can be both, and the operative reason is ours — an
        // operator reading a skip row needs to see the condition they can do
        // something about, and "tenant paused" on an account under a compliance
        // hold sends them to the owner instead of to the finding.
        //
        // A skip, never a failure, for the pause's reason exactly (823): a
        // suspension clears when a human decides it does, and 364's recovery
        // sweep counts attempts — so failing here would abandon everything
        // submitted during a hold that might last weeks.
        if (app(TenantSuspension::class)->isCurrentTenantSuspended()) {
            $this->recordSkip('tenant suspended');

            return;
        }

        if (app(TenantPause::class)->isCurrentTenantPaused()) {
            $this->recordSkip('tenant paused');

            return;
        }

        if (! $this->isEnabled()) {
            $this->recordSkip('toggle off');

            return;
        }

        $run = $this->claimRun();


        if ($run === null) {
            // The key was already claimed. Not an error — the answer to "has
            // this run?" is yes, and the side effect belongs to that run.
            return;
        }

        try {
            $handedOff = ! $this->canExecute();

            $output = $handedOff ? $this->handoff() : $this->execute();

            $run->update([
                'status' => $handedOff
                    ? AutomationRunStatus::HandedOff
                    : AutomationRunStatus::Succeeded,
                'output' => $output,
                'finished_at' => now(),
            ]);

            $this->recordActivity();
        } catch (Throwable $e) {
            Log::error('Exception in AutopilotJob: '.$e->getMessage(), ['exception' => $e]);
            $run->update([
                'status' => AutomationRunStatus::Failed,
                // ⛔ **THE CLASS, NEVER THE MESSAGE** — {@see self::failed()}'s
                // rule and {@see self::closeAbandonedRun()}'s, which the two
                // writers of this one column disagreed about for a fortnight.
                // That method's own comment named this line: *"the `catch` in
                // `handle()` writes the message and is not a precedent to
                // follow; it predates 9378 and is another slice's to answer."*
                // This is that slice.
                //
                // ⛔ **AND THE LIVE OCCUPANT IS THE MAIL PATH.** Five reach
                // paths end here carrying a Symfony
                // `UnexpectedResponseException`, whose `getMessage()` is the
                // server's verbatim reply — on this deployment `554 Message
                // rejected: Email address is not verified`, followed by the
                // identity that failed. Four of the five name the account
                // holder's own address ({@see \App\Services\Trust\FirstWeekPath},
                // {@see \App\Services\Content\Publishing},
                // {@see \App\Jobs\Voice\NotifyOwnerOfVoicemailJob},
                // {@see \App\Jobs\EscalateUrgentThreadJob}); the fifth is
                // `ReviewInviteSender` on a `sync` queue, and that one names a
                // **customer's**.
                //
                // ⚠️ **NOT A FLAT CLASS NAME EITHER, AND A TEST IS WHY**
                // (11334). The first draft of this repair wrote `$e::class` for
                // every arm, and `UrgentEscalationTest`'s *"a page that reached
                // nobody is a failed run and not a successful one"* reddened:
                // it reads this column for
                // `MailQuota::ceilingKeyFor('smtp')`, because on a
                // {@see \App\Exceptions\MailNotDeliverable} the message is
                // **ours** and names the registry row an operator has to set.
                // **A discipline that cannot vary across its three arms is not
                // watching them** — which is the shape this repair exists to
                // remove, reproduced by the repair.
                //
                // ⚠️ **AND THE SMTP CODE IS CARRIED WHERE THERE IS ONE.** It is
                // three digits by construction, cannot hold an address, and is
                // the one field that separates a `421` from a `554`.
                'error' => MailFailure::runError($e),
                'finished_at' => now(),
            ]);

            // Rethrow so the queue retries with backoff. The row is already
            // closed as failed, so a run that never comes back is still visible
            // rather than sitting at "running" forever.
            throw $e;
        } finally {
            // ⚠️ A `finally`, NOT A LINE IN THE `catch`, AND DECISION 351 IS WHY.
            // That decision released on two named branches, a thrown exception
            // was a third nobody listed, and the claim was kept — so the work was
            // buried with every later dispatch a no-op against the unique index.
            // 357 moved it here for exactly this reason. The condition is a
            // question about what happened, which cannot miss a branch, rather
            // than an enumeration of the branches somebody thought of.
            //
            // On the success path `claimIsSpent()` is true and this does nothing,
            // which is what stops a redelivery paying twice.
            if (! $this->claimIsSpent()) {
                $this->releaseUnearnedClaim();
            }
        }
    }

    /**
     * Open the run row, or return null if there is nothing to open it for.
     *
     * The unique constraint is the arbiter rather than a preceding SELECT: two
     * workers checking "does a row exist?" simultaneously both see no, and both
     * proceed. Letting the insert fail is the only version without that race.
     *
     * ⚠️ TWO CONSTRAINTS ANSWER HERE, NOT ONE, AND THE SECOND WAS UNCAUGHT
     * UNTIL 6740. `location_id` is a foreign key, so a job carrying an id no
     * row has threw `SQLSTATE[23503]` straight out of `handle()`: the queue
     * burned all three attempts and **no run row existed at all** — not failed,
     * not skipped, nowhere. `automation_runs` is this codebase's exhaustive
     * record of what a job did (1738, 1855, *"the only trace"*), and the one
     * class of failure it could not describe was the one where the subject had
     * gone. Decision 6686 found it; {@see self::recordMissingLocation()} is the
     * answer and 6741 argues its shape.
     *
     * ⚠️ THE SAME ARGUMENT AS THE UNIQUE KEY, DELIBERATELY. A preceding
     * `Location::whereKey(...)->exists()` would be a check-then-insert race
     * against a concurrent deletion, and this method already refuses that trade
     * two paragraphs up. The database is the arbiter for both.
     */
    private function claimRun(): ?AutomationRun
    {
        try {
            // Wrapped in a transaction so the violation is contained. Catching
            // it bare works only when this job is the outermost operation:
            // PostgreSQL aborts the *whole* transaction on a constraint
            // violation, so inside one — a caller that dispatched
            // synchronously within DB::transaction(), or the test harness,
            // which wraps every test — the catch would succeed while leaving
            // every subsequent query failing with 25P02.
            //
            // Nested, Laravel issues a SAVEPOINT and rolls back to it, so the
            // outer transaction survives intact.
            return DB::transaction(fn (): AutomationRun => AutomationRun::create([
                'location_id' => $this->locationId,
                'automation_key' => $this->automationKey(),
                'idempotency_key' => $this->idempotencyKey(),
                // ⛔ **THE ONLY THING THAT SURVIVES THIS PROCESS.** See
                // {@see self::closeAbandonedRun()}: a killed worker's `failed()`
                // runs on a different object, and this is the one value on the
                // row that the rebuilt object can still produce.
                'job_uuid' => $this->jobUuid(),
                'status' => AutomationRunStatus::Running,
                'input' => $this->input(),
                'started_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            Log::info('Autopilot run skipped: already claimed', [
                'automation' => $this->automationKey(),
                'business_id' => $this->businessId,
            ]);

            return null;
        } catch (QueryException $e) {
            // `UniqueConstraintViolationException` extends this one, so the
            // order of the two catches is load-bearing rather than stylistic.
            if (! self::causedByAMissingLocation($e)) {
                throw $e;
            }

            $this->recordMissingLocation();

            return null;
        }
    }

    /**
     * Whether this violation is the location foreign key and not another one.
     *
     * ⚠️ BOTH HALVES ARE NEEDED AND THE SECOND IS THE ONE THAT MATTERS.
     * `automation_runs` has two foreign keys, and 23503 alone would also match
     * `automation_runs_business_id_foreign` — a job for a business that has
     * been deleted. That one must keep throwing: there is no degraded row to
     * write, because the tenant column is the one RLS checks and the one the
     * failing key names, so nothing can be recorded for it anywhere. Widening
     * this predicate would turn a loud, correct failure into a second silent
     * one, which is the defect being closed rather than a fix for it.
     *
     * ⚠️ THE CONSTRAINT NAME IS A STRING AND STRINGS GO STALE. What stops that
     * being silent is that the covering test asserts the *row*, not the
     * predicate: rename the constraint and *"a job whose location has gone
     * still leaves a record"* goes red naming this method. There is no other
     * tripwire, which is why the test is the load-bearing one for 6686.
     */
    private static function causedByAMissingLocation(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23503'
            && str_contains($e->getMessage(), 'automation_runs_location_id_foreign');
    }

    /**
     * The record an attempt leaves when the location it was for has gone.
     *
     * ⚠️ `location_id` IS NULL AND THE REQUESTED ID IS IN `input`, WHICH IS A
     * RULING RATHER THAN A WORKAROUND (6741). The column is a foreign key —
     * `cascadeOnDelete`, so it cannot point at a row that is not there and a
     * run row *does not survive* its location being deleted in any case. What
     * survives is `input.location_id`: the id the job was **handed**, recorded
     * as a fact about the dispatch rather than as a pointer. That is 6688's
     * distinction, applied to the location instead of the reply.
     *
     * ⛔ `idempotency_key` IS NULL AND THAT IS 356/357's BUG AVOIDED, NOT AN
     * OVERSIGHT. The claim was refused by the database, so this row holds
     * none; writing the key here would make a later dispatch collide with this
     * failed attempt and return before `execute()` for ever.
     *
     * ⚠️ `Failed` RATHER THAN `Skipped`, AND THE ENUM'S OWN DOCBLOCK IS WHY.
     * `Skipped` is *"the system behaving correctly"* — a toggle, a kill switch,
     * work already done — and 364 deliberately excludes it from the recovery
     * budget so a sweeper keeps coming back. A job dispatched for a location
     * that does not exist is neither: nobody should come back to it, and
     * somebody should look at whatever dispatched it.
     *
     * ⚠️ IT DOES NOT RETHROW, SO THE QUEUE DOES NOT RETRY, AND THE COST IS
     * NAMED IN 6742. A vanished location does not come back, so three attempts
     * would be three identical rows in a table that grows plus a `failed_jobs`
     * entry whose message is a Postgres constraint string. The log line below
     * is what replaces it.
     *
     * ⚠️ NOTHING REACHES THE ACTIVITY FEED. `activityAction()`'s own docblock
     * names the automation *"whose only honest title is 'checked something and
     * found nothing'"* as the one that makes the feed worse; this is an
     * internal anomaly about a row that is gone, not an action taken for an
     * owner. `automation_runs` is the record that is meant to be exhaustive.
     */
    private function recordMissingLocation(): void
    {
        Log::warning('Autopilot run abandoned: location no longer exists', [
            'automation' => $this->automationKey(),
            'business_id' => $this->businessId,
            'location_id' => $this->locationId,
        ]);

        AutomationRun::create([
            'location_id' => null,
            'automation_key' => $this->automationKey(),
            'idempotency_key' => null,
            'status' => AutomationRunStatus::Failed,
            'input' => $this->input(),
            'output' => ['reason' => self::LOCATION_MISSING],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    /**
     * What the owner sees when this automation succeeds, or null for silence.
     *
     * `29` §2 rule 42 requires every automated action to reach the feed within
     * 60 seconds. Most automations should therefore return something — but
     * "every action is visible" and "the feed is worth reading" are in tension,
     * and an automation whose only honest title is "checked something and found
     * nothing" makes the feed worse. Those return null and remain visible in
     * automation_runs, which is the record that is meant to be exhaustive.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return AutopilotActionType::AutomationCompleted;
    }

    /**
     * Metadata for the feed item. Never customer content.
     *
     * ⚠️ THIS SAID "it reaches a broadcast payload, and from there browser
     * memory and devtools", AND IT DOES NOT. `ActivityRecorded::broadcastWith()`
     * returns an explicit four-key allowlist — `id`, `action`, `title`,
     * `needs_owner` — and a test pins those keys, so metadata never goes on the
     * wire at all. The rule is unchanged and the reason for it is not: this
     * payload must be safe **if** it is ever broadcast, the feed is owner-visible
     * on every staff screen, and the audit log beside it is the record an
     * erasure request has to be able to honour. Stating the mechanism wrongly is
     * worse than omitting it, because it invites the inverse mistake — "it is
     * not broadcast, so metadata is safe" — and three other docblocks cited this
     * one as their authority.
     *
     * @return array<string, mixed>
     */
    protected function activityMetadata(): array
    {
        return ['automation' => $this->automationKey()];
    }

    private function recordActivity(): void
    {
        $action = $this->activityAction();

        if ($action === null) {
            return;
        }

        app(ActivityService::class)->record(
            $action,
            $this->locationId,
            $this->activityMetadata(),
        );
    }

    /**
     * The record a run leaves when a gate stopped it.
     *
     * ⚠️ IT CARRIES `input`, AND UNTIL 6743 IT DID NOT — WHICH IS 6690(c).
     * A run skipped for a kill switch, a suspension, a tenant pause or a
     * toggle wrote `location_id`, `automation_key`, `status` and a reason, and
     * **nothing saying what it was about**. Skips are the common case rather
     * than the rare one — every disabled automation and every paused tenant
     * produces them continuously — so the largest population of rows in this
     * table was the one that could not be attributed to a subject.
     *
     * ⚠️ IT IS `input()` VERBATIM RATHER THAN A NEW BAG, WHICH IS THE WHOLE
     * DESIGN. A skipped row and a claimed row are now read identically, so the
     * fourteen automations that already override `input()` to name the review,
     * reply, conversation, campaign or voicemail they act on get that
     * identifier on their skip rows with no further edit — and nothing here
     * invents a shape that would need a migration to correct. The overrides
     * are pure functions of constructor properties, so calling them on a path
     * that runs when the tenant is paused cannot itself fail.
     *
     * ⛔ AND IT DEGRADES `location_id` FOR THE SAME REASON `claimRun()` DOES.
     * Every gate above runs *before* the claim, so a kill-switched job for a
     * vanished location reaches this insert and hit the identical 23503 —
     * 6686's defect on the commoner of the two paths. That is not a race: it
     * is what a kill switch plus a deleted location does every time.
     */
    private function recordSkip(string $reason): void
    {
        $attributes = [
            'location_id' => $this->locationId,
            'automation_key' => $this->automationKey(),
            'status' => AutomationRunStatus::Skipped,
            'input' => $this->input(),
            'output' => ['reason' => $reason],
            'started_at' => now(),
            'finished_at' => now(),
        ];

        try {
            DB::transaction(fn (): AutomationRun => AutomationRun::create($attributes));
        } catch (QueryException $e) {
            if (! self::causedByAMissingLocation($e)) {
                throw $e;
            }

            Log::warning('Autopilot run skipped for a location that no longer exists', [
                'automation' => $this->automationKey(),
                'business_id' => $this->businessId,
                'location_id' => $this->locationId,
                'reason' => $reason,
            ]);

            // ⚠️ THE SECOND KEY IS NOT DECORATION. Without it a reader meets a
            // null column beside a non-null `input.location_id` and has to
            // infer which of the two is the mistake. The gate that stopped the
            // run stays the `reason`, because that is what happened first and
            // it is what an operator can act on; the note is the same string
            // this automation's `failed` rows carry, so one query over
            // `output` finds both populations.
            AutomationRun::create([
                ...$attributes,
                'location_id' => null,
                'output' => ['reason' => $reason, 'note' => self::LOCATION_MISSING],
            ]);
        }
    }

    /**
     * Platform-wide and per-business stop.
     *
     * Deliberately reads config rather than the database: a kill switch that
     * needs a working database to be honoured is not much of a kill switch.
     */
    private function killSwitchThrown(): bool
    {
        return self::killSwitchThrownFor($this->automationKey());
    }

    /**
     * The same question, asked without an instance.
     *
     * A scheduled sweeper that dispatches jobs wants to know before it enumerates
     * anything — the jobs would each refuse individually, correctly, but they
     * would each open a `skipped` run row first, so a killed automation would
     * still write a row per candidate every time the schedule fired. Static and
     * shared rather than a second config read in the command, because two
     * readings of one kill switch is how a kill switch ends up half honoured.
     */
    public static function killSwitchThrownFor(string $automationKey): bool
    {
        if (config('autopilot.kill_switch') === true) {
            return true;
        }

        $disabled = config('autopilot.disabled_automations', []);

        return is_array($disabled) && in_array($automationKey, $disabled, true);
    }

    /**
     * What the run row records about why this job was dispatched.
     *
     * Protected rather than private so an automation can add the identifier of
     * the thing it acted on. `automation_runs` is the exhaustive record, and a
     * sweeper that has to bound its own retries needs to count this automation's
     * previous attempts *for one row* — which is not answerable from a business
     * id and a location id.
     *
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        return array_filter([
            'business_id' => $this->businessId,
            'location_id' => $this->locationId,
        ], static fn (mixed $v): bool => $v !== null);
    }

    private function jittered(int $seconds): int
    {
        // +/- 25%. Enough to spread a synchronised failure without making the
        // backoff curve meaningless.
        //
        // ⚠️ **THE FORMULA MOVED TO {@see QueueBackoff} AND THE CALL STAYED**
        // (6267). The three actuation jobs do not extend this class and needed
        // the same rule; a copy of two numbers is the copy that drifts.
        return QueueBackoff::jittered($seconds);
    }

    protected function location(): ?Location
    {
        return $this->locationId === null ? null : Location::find($this->locationId);
    }
}
