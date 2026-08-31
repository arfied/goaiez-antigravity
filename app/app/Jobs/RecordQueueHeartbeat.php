<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Ops\PlatformHealth;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * "A worker is popping the queue this beat rides" — the only claim a queued job
 * can make that nothing else can (P23).
 *
 * ⛔ **THAT SENTENCE READ "A WORKER IS ALIVE" UNTIL 2026-08-23 AND IT IS WIDER
 * THAN ANYTHING THIS JOB CAN PROVE — CORRECTED (8880-8899).** This class carries
 * no `$queue` and no `$connection` and is dispatched bare, so it lands on
 * `queue.connections.{queue.default}.queue` — **one** of the six names
 * `config/horizon.php` designs for. A worker that stopped popping any of the
 * other five is invisible to it, permanently, and **nothing at all is raised**:
 * not an amber row, not a per-queue tick, while `Admin\OperatorAlertBoard` goes
 * on rendering the bell as armed. {@see self::queueWatched()} is what the alert
 * and the arming panel now both say out loud.
 *
 * ⚠️ **THE OLD SENTENCE IS KEPT BECAUSE THE HAZARD IT UNDERSTATED IS
 * UNCHANGED** (4368's rule): the beat still proves the one thing nothing else
 * can, and the correction is to its *scope*, not to its value.
 *
 * ⛔ **THE POINT IS THAT IT RUNS AT ALL.** The scheduler dispatching it proves
 * nothing about the queue: `dispatch()` writes a row and returns, happily, on a
 * platform where every worker has been dead for a week. The beat is written
 * *inside* `handle()`, so the timestamp exists if and only if a worker actually
 * picked the job up — and the scheduler's own heartbeat, written by the command
 * that dispatched this, is what tells the two failures apart.
 *
 * NOT AN `AutopilotJob`, on `DeliverPlatformMail`'s reasoning: there is no
 * tenant, no automation toggle and no handoff path. It is one write with no
 * arguments.
 *
 * ⚠️ **NO RETRY LADDER, DELIBERATELY.** A beat that failed a minute ago is
 * worthless a minute later — the next one is already due — and a queue of
 * retried heartbeats piling up during an outage is a second incident on top of
 * the first. `tries = 1`; the next tick is the retry.
 */
final class RecordQueueHeartbeat implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * See the class docblock: the next tick is the retry.
     */
    public int $tries = 1;

    /**
     * The one queue name this beat rides, or `null` when the selected connection
     * declares none.
     *
     * ⛔ **THIS ROUTES NOTHING AND MUST NOT BE READ AS ROUTING.** It reports the
     * name the *dispatcher* already picks for a bare `dispatch()` — the same two
     * config reads `Illuminate\Queue\QueueManager` does — so that the alert
     * sentence and the arming panel can name it instead of speaking in the
     * plural about queues this job cannot see. Giving this class a `$queue`
     * would strand the beat itself, and
     * `tests/Feature/Architecture/QueueRoutingTest.php` reddens on the attempt.
     *
     * ⚠️ **ONE DERIVATION, TWO READERS, DELIBERATELY** (7760-7779's lesson).
     * `PlatformHealthChecks` and `Admin\OperatorAlertBoard` both call this; a
     * second copy of the two reads is a second answer, and the two would then
     * disagree about which queue the platform is watching.
     *
     * ⚠️ **`null` IS A REAL ANSWER AND NOT A FAILURE.** A `sync` connection has
     * no queue at all — it is what the suite runs on — and both readers fall
     * back to a sentence that names no name rather than inventing `default`.
     */
    public static function queueWatched(): ?string
    {
        $driver = config('queue.default');

        if (! is_string($driver)) {
            return null;
        }

        $name = config('queue.connections.'.$driver.'.queue');

        return is_string($name) && $name !== '' ? $name : null;
    }

    public function handle(PlatformHealth $health): void
    {
        $health->beat('queue');
    }
}
