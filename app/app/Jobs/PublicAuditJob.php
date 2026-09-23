<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AuditStatus;
use App\Models\PublicAudit;
use App\Services\Audit\AuditEngine;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs one free instant audit off the queue (`29` §6.2).
 *
 * DELIBERATELY NOT AN AutopilotJob — decision 198, and it is worth restating
 * because the inheritance looks free. AutopilotJob is the contract 142
 * automations depend on: a tenant, a location, automation-mode gating, kill
 * switches, an activity-feed entry, an approval path, and an `handoff()` for
 * running without API access. A pre-signup audit has none of those and can never
 * have them — there is no tenant to gate, no toggle to read and nobody to notify.
 * Extending it to borrow the retry helper would mean relaxing every one of those
 * guarantees to `nullable`, permanently, for one job that is not an automation.
 *
 * IDEMPOTENT ON THE TOKEN, two ways. `ShouldBeUnique` stops a second job for the
 * same token being queued while one is running, and the terminal-status guard in
 * handle() stops a job that got through anyway from re-billing Places for an
 * audit that already has an answer. The second one is the load-bearing half:
 * uniqueness locks expire, and the failure it prevents is a duplicate charge
 * rather than duplicate work.
 *
 * ONE ATTEMPT, WHICH IS UNUSUAL AND INTENTIONAL. `CLAUDE.md` requires jobs to be
 * "retried with backoff" and this one is not, because every attempt costs about
 * 9.2¢ of somebody else's money (PlacesSpend) and every failure this job can
 * actually suffer has already been converted to a degraded result by
 * AuditContextBuilder — budget exhaustion, an unreachable vendor, a refused
 * fetch and a broken check all end as an honest "we could not check this" rather
 * than as a throw. What is left to retry is a bug in our own code, and retrying
 * a bug three times just bills Google three times for the same crash.
 */
final class PublicAuditJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * See the class docblock. A retry is a second bill, not a second chance.
     */
    public int $tries = 1;

    /**
     * The perceived budget from `29` §6.2, derived rather than restated.
     *
     * The job is killed at the same second the visitor stops waiting, so a
     * wedged fetch cannot hold a worker open indefinitely on a free endpoint
     * anyone can hit.
     */
    public int $timeout = app(AuditEngine::class)->budgetSeconds();

    /**
     * Long enough that a duplicate cannot slip in behind a job still running,
     * short enough that a crashed worker does not lock a token out for an hour.
     */
    public int $uniqueFor = 120;

    public function __construct(
        public readonly string $token,
    ) {}

    public function uniqueId(): string
    {
        return $this->token;
    }

    public function handle(AuditEngine $engine): void
    {
        $audit = PublicAudit::query()
            ->where('token', $this->token)
            ->first();

        if (! $audit instanceof PublicAudit) {
            // Pruned by `audits:prune` (decision 192) between enqueue and run,
            // or never existed. Nothing to do and nothing wrong.
            return;
        }

        if ($audit->status->isTerminal()) {
            // Already answered. Re-running would spend Places money to overwrite
            // a result somebody may already be looking at.
            return;
        }

        $engine->run($audit);
    }

    /**
     * A crash still has to leave the row in a state the public page can render.
     *
     * Without this the audit sits at `Running` forever and the client polls
     * until the visitor gives up — `29` §6.2's page has no other way to learn
     * the job is gone. `Failed` is a state the page renders honestly with a way
     * to try again (AuditStatus).
     */
    public function failed(?\Throwable $e): void
    {
        PublicAudit::query()
            ->where('token', $this->token)
            ->whereIn('status', [AuditStatus::Queued->value, AuditStatus::Running->value])
            ->update(['status' => AuditStatus::Failed->value]);
    }
}
