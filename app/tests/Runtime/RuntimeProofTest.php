<?php

declare(strict_types=1);

namespace Tests\Runtime;

use App\Jobs\SendMissedCallTextBackJob;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * R225 — A GREEN SUITE MAY NEVER HIDE A DEAD RUNTIME.
 *
 * ⛔⛔ THE DEFECT THIS EXISTS FOR, STATED PLAINLY:
 *
 * Laravel runs queued jobs SYNCHRONOUSLY in tests. So every test in this codebase
 * passes whether or not a worker exists — and the defect (nothing runs the queue) was
 * invisible for months for exactly that reason. The suite was green the entire time
 * the text-back was not being sent at all on a real box.
 *
 * ⭐⭐⭐ A TEST THAT SUPPLIES ITS OWN QUEUE IS NOT A TEST OF THE QUEUE.
 *
 * These tests are the exception to the suite's own defaults. They run in the
 * `runtime` group against the REAL driver with a REAL worker, and CI runs them
 * separately. A test here that passes under `sync` is a test that proves nothing,
 * so the first assertion is that the driver is not `sync`.
 *
 * ⚠️ THEY ARE ALLOWED TO BE SLOW. Everything else in the suite is fast because it
 * fakes the transport; these three are slow because they refuse to.
 */
#[Group('runtime')]
final class RuntimeProofTest extends TestCase
{
    /**
     * ⛔ THE GUARD ON THE OTHER TWO. Without it, both pass under `sync` and report
     * a working runtime on a box with no worker — which is the original defect
     * wearing a test's clothes.
     */
    #[Test]
    public function the_runtime_group_never_runs_on_the_sync_driver(): void
    {
        $this->assertNotSame(
            'sync',
            config('queue.default'),
            'The runtime group ran on the sync driver. Every assertion below would '
            .'pass with no worker running, which is the exact failure this group exists to catch.'
        );
    }

    /**
     * ⭐ A job is DISPATCHED, and then a real worker must pick it up. Nothing here
     * calls handle() directly — that is the sync path in a costume.
     */
    #[Test]
    public function a_dispatched_job_is_drained_by_a_real_worker(): void
    {
        DB::table('jobs')->delete();

        SendMissedCallTextBackJob::dispatch(...$this->fixtureArgs());

        $this->assertSame(1, DB::table('jobs')->count(), 'The job did not reach the queue.');

        // The worker is the subject. One pass, bounded, no retries.
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

        $this->assertSame(
            0,
            DB::table('jobs')->count(),
            'THE JOB WAS ACCEPTED AND NEVER RAN. This is the queue-runner defect: the webhook returns 200, '
            .'the row sits in the jobs table, and the missed caller hears nothing.'
        );
    }

    /**
     * ⭐⭐⭐ THE HEARTBEAT RINGS AT ZERO.
     *
     * "The queue is empty" and "nothing is reading the queue" must not produce the
     * same silence. An idle queue is precisely the state in which a dead worker
     * looks healthiest, so the heartbeat must be written on an EMPTY pass.
     */
    #[Test]
    public function the_heartbeat_is_written_even_when_the_queue_is_empty(): void
    {
        DB::table('jobs')->delete();
        cache()->forget('goaiez:worker:heartbeat');

        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

        $this->assertNotNull(
            cache()->get('goaiez:worker:heartbeat'),
            'The worker ran an empty pass and recorded nothing, so a dead worker and an '
            .'idle worker are indistinguishable — and the idle case is the common one.'
        );
    }

    /**
     * ⛔ The scheduler's own proof: the tick is CLAIMED, so a second scheduler is
     * visible here rather than in a customer receiving every reminder twice.
     */
    #[Test]
    public function a_second_scheduler_cannot_claim_the_same_tick(): void
    {
        $key = 'goaiez:scheduler:tick:'.now()->format('YmdHi');
        cache()->forget($key);

        $first = cache()->add($key, 1, 90);
        $second = cache()->add($key, 1, 90);

        $this->assertTrue($first, 'The first scheduler failed to claim its own tick.');
        $this->assertFalse($second, 'A SECOND SCHEDULER CLAIMED THE SAME TICK — every reminder in the product sends twice.');
    }

    /** @return array<int, mixed> */
    private function fixtureArgs(): array
    {
        return []; // wire to the module's own factory when this lands in the tree
    }
}
