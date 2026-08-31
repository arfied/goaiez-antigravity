<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Jobs\AutopilotJob;
use App\Models\AutomationRun;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Queue\TimeoutExceededException;
use RuntimeException;

/**
 * A concrete `AutopilotJob` that kills itself the way the worker's timeout does.
 *
 * ⛔ **WHAT IT MODELS, AND WHY IT HAS TO MODEL IT FROM THE INSIDE.**
 * `Worker::registerTimeoutHandler()` installs a `SIGALRM` handler that calls
 * `markJobAsFailedIfItShouldFailOnTimeout()` — which reaches `failed()`, because
 * {@see AutopilotJob::$failOnTimeout} is `true` — and **then** calls `kill()`,
 * which `exit`s the process. So the sequence is: `execute()` is on the stack,
 * `failed()` runs, the process disappears without unwinding. `handle()`'s
 * `catch` and `finally` never run. {@see self::execute()} reproduces exactly
 * that order, from exactly that point on the stack.
 *
 * ⛔ **THE ONE THING NO IN-PROCESS TEST CAN DO IS SKIP A `finally`, AND IT WAS
 * MEASURED RATHER THAN ASSUMED.** `exit` takes PHPUnit with it; a fatal error
 * takes the run with it; and a suspended `Fiber` destroyed while parked **does**
 * unwind and run its `finally` blocks — verified on this PHP before this class
 * was written. So the instant that matters is captured *while it is true*
 * ({@see self::$runsAtKill}) rather than asserted after `dispatch()` returns,
 * and everything `handle()` goes on to do afterwards is an artefact of the test
 * process still being alive.
 *
 * ⚠️ **{@see AbandonedAutopilotProbeJob} IS NOT THIS.** That one models the
 * queue giving up after a spent retry ladder — an ordinary exception, three
 * times — where `handle()` has already closed the row and released whatever it
 * owed. This models the death that skips both. Folding them would put a
 * self-failing `execute()` on the probe `AutomationRunFailureTest` uses for
 * every one of its cases.
 *
 * ⚠️ **A NAMED CLASS, ON 9714's RULE.** `CallQueuedHandler::failed()`
 * `unserialize()`s the payload, and an anonymous class cannot be unserialized —
 * so a probe declared inside a test file reaches `failed()` never and the test
 * goes green having proved nothing.
 */
final class TimedOutAutopilotProbeJob extends AutopilotJob
{
    /**
     * How many times the simulated kill has been performed.
     *
     * ⛔ **ASSERT THIS.** Everything else here is a snapshot, and a snapshot that
     * was never taken is an empty array — which reads exactly like a feature that
     * wrote nothing. Static, because the instance `failed()` runs on is a
     * different object (9714).
     */
    public static int $kills = 0;

    /**
     * Every `automation_runs` row for the tenant at the instant the worker died.
     *
     * @var list<AutomationRun>
     */
    public static array $runsAtKill = [];

    /** The uuid the queue gave this dispatch, read off `$this->job`. */
    public static ?string $uuidAtKill = null;

    public string $automation = 'probe.timed-out';

    /** The idempotency key this dispatch claims, or null to run unkeyed. */
    public ?string $key = null;

    public static function reset(): void
    {
        self::$kills = 0;
        self::$runsAtKill = [];
        self::$uuidAtKill = null;
    }

    public function automationKey(): string
    {
        return $this->automation;
    }

    protected function idempotencyKey(): ?string
    {
        return $this->key;
    }

    /**
     * ⛔ **`true` SO THAT THE `finally` THIS TEST CANNOT SKIP DOES NOTHING.**
     * This is fidelity, not a claim about the automation: a killed process runs
     * no `finally` at all, so the end state must be *claim still held*. Returning
     * `false` here would let the test process hand the key back through a code
     * path that, in the failure being modelled, never executes — and the
     * consequence under test is precisely what happens to the next dispatch for
     * this subject.
     */
    protected function claimIsSpent(): bool
    {
        return true;
    }

    protected function execute(): ?array
    {
        $job = $this->job;

        if ($job === null) {
            throw new RuntimeException(
                'TimedOutAutopilotProbeJob must be dispatched, not called: there is no queue '
                .'job to fail and no payload uuid, which is the whole thing under test.'
            );
        }

        $payload = $job->payload();
        $uuid = (string) $job->uuid();

        // 1. What SIGALRM does first: fail the job. This is the framework's own
        //    route — `Job::failed()` hands the payload, the exception, the uuid
        //    and itself to `CallQueuedHandler::failed()`, which rebuilds the
        //    command by unserializing `$payload['data']['command']` and calls
        //    `failed()` on *that* object. Nothing set on `$this` reaches it.
        app(CallQueuedHandler::class)->failed(
            $payload['data'],
            new TimeoutExceededException('Job has timed out.'),
            $uuid,
            $job,
        );

        // 2. What SIGALRM does next is `exit`. This is the closest a live
        //    process gets: read the world before anything else touches it.
        self::$uuidAtKill = $uuid;
        self::$runsAtKill = AutomationRun::query()->orderBy('id')->get()->all();
        self::$kills++;

        // 3. And the process is gone. Everything from here is the test harness
        //    being alive when production would not be — see the class docblock.
        //
        // ⛔ **DELIBERATELY NOT A `TimeoutExceededException`, AND THE REASON IS
        // A REAL FRAMEWORK BEHAVIOUR RATHER THAN A STYLE CHOICE.**
        // `Job::fail()` does this before it calls `failed()`:
        //
        //     if ($this->shouldRollBackDatabaseTransaction($e)) {
        //         $this->container->make('db')
        //             ->connection(config('queue.failed.database'))
        //             ->rollBack(toLevel: 0);
        //     }
        //
        // and `shouldRollBackDatabaseTransaction()` is true for exactly
        // `TimeoutExceededException` on a `database`/`database-uuids` failer,
        // which is this application's. **Level 0 in the harness is the test's own
        // wrapping transaction**, so rethrowing the real class here makes
        // `SyncQueue` roll the entire fixture back — measured: every
        // `automation_runs` row vanished between the snapshot and the
        // assertions. In production the worker is at level 0 already and the
        // rollback is what *clears the way* for `closeAbandonedRun()`'s write;
        // here it would be the harness eating itself.
        throw new RuntimeException(
            'the worker has exited by now; this exception exists only because a PHP process '
            .'that is still alive has to leave execute() somehow.'
        );
    }

    protected function handoff(): ?array
    {
        return null;
    }
}
