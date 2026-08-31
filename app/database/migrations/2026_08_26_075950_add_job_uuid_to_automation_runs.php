<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give a run row a name its own process's corpse can be identified by.
 *
 * ⛔ **A JOB KILLED AT THE WORKER TIMEOUT NEVER UNWINDS `handle()`, SO NOTHING
 * CLOSES ITS ROW — AND UNTIL THIS COLUMN NOTHING COULD FIND IT.** `AutopilotJob`
 * sets `public bool $failOnTimeout = true`, so `failed()` **does** run on a
 * SIGALRM kill (`Worker::registerTimeoutHandler()` calls
 * `markJobAsFailedIfItShouldFailOnTimeout()` before `kill()`) — but
 * `CallQueuedHandler::failed()` rebuilds the job by `unserialize()`ing the
 * payload, so the `AutomationRun` that `claimRun()` returned is on an object
 * that no longer exists. Decision 9713 named the gap and deferred the column;
 * this is the column.
 *
 * ⚠️ **THE VALUE IS THE QUEUE PAYLOAD'S OWN UUID, WHICH IS ALSO
 * `failed_jobs.uuid`** — `Job::uuid()` reads `payload()['uuid']`, and
 * `DatabaseUuidFailedJobProvider` logs that same value. So this is not only a
 * self-join for the abandonment close: it is the first thing in this schema
 * that lets an operator take a `failed_jobs` row and find the tenant, the
 * location and the subject the dead job was working on. `string` rather than
 * `uuid` deliberately — `failed_jobs.uuid` is a `varchar` and a join across two
 * different column types is the kind of thing that works until somebody adds an
 * index.
 *
 * ⚠️ **NULLABLE, AND EVERY ROW THIS APPLICATION HAS ALREADY WRITTEN STAYS
 * NULL.** There is no backfill and there cannot be one: `automation_runs` is
 * FORCE ROW LEVEL SECURITY, a migration establishes no tenant, so an `UPDATE`
 * here would match zero rows and report success (`CLAUDE.md` §Convention
 * tests). Nothing needs one either — the uuid of a dispatch that has already
 * died is not recoverable from anywhere. **Rows stranded at `running` before
 * this deploy stay stranded**; see decision 9967.
 *
 * ⚠️ **NOT UNIQUE.** A retried job keeps one payload and therefore one uuid
 * across all three attempts, and each attempt opens its own run row — so the
 * uuid is one-to-many by design. What is unique at any instant is the *running*
 * row for a uuid, because attempts are sequential, and that is the predicate
 * `AutopilotJob::closeAbandonedRun()` uses.
 *
 * ⚠️ **NO RLS STATEMENTS HERE**, on the idempotency-key migration's precedent:
 * the policy is on the table and covers every column it will ever have.
 *
 * See docs/DECISIONS.md 9960–9974.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_runs', function (Blueprint $table): void {
            $table->string('job_uuid')->nullable()->after('idempotency_key');

            // The abandonment close reads `job_uuid` + `status`; the operator
            // join reads `job_uuid` alone. One index answers both.
            $table->index('job_uuid', 'automation_runs_job_uuid_index');
        });
    }

    public function down(): void
    {
        Schema::table('automation_runs', function (Blueprint $table): void {
            $table->dropIndex('automation_runs_job_uuid_index');
            $table->dropColumn('job_uuid');
        });
    }
};
