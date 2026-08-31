<?php

declare(strict_types=1);

use App\Jobs\Reviews\PostReplyJob;
use App\Services\Automation\AutomationRunRetention;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make the reply an approved reply's publish attempts are about a queryable
 * column, instead of an unindexed key inside two `jsonb` payloads — decisions
 * 10188, 10260.
 *
 * ## ⛔ Why this exists: `ReplyPublicationStatus` cannot read `automation_runs`
 * without it
 *
 * `Services\Reviews\ReplyPublicationStatus::for()` answers the owner's *"is my
 * reply on Google?"* from columns on the `replies` row alone, and never from the
 * run's own account of itself — so its terminal `NotPublishedYet` is said about
 * a run this platform's own worker killed mid-flight (`AutomationRunStatus::
 * Abandoned`), which is the one state where *"This is not on Google"* can be a
 * false statement about a third party rather than merely an unhelpful one.
 * Wave 34 refused to close this: reading the run needs a join on
 * `input->>'reply_id'`, unindexed, on a table it had just proved unbounded.
 *
 * ⚠️ **THE OBJECTION IS NARROWER THAN IT LOOKS AND STILL REAL** — see
 * `App\Services\Automation\AutomationRunRetention`'s own docblock for the half
 * that answers "unbounded": the survivor rule that ships beside the pruner in
 * this same slice keeps the newest terminal run per reply for ever regardless
 * of any period an operator states, so a pruned table cannot make this read
 * wrong. What is left is genuinely just the missing index, which this migration
 * is.
 *
 * ## Generated, never written directly — the `message_cost_entries` precedent,
 * with the opposite ending
 *
 * `2026_08_14_113356_store_message_cost_entries_in_millicents.php` used
 * `GENERATED ALWAYS AS (…) STORED` **then dropped the expression**, because that
 * column needed to become independently writable once the backfill finished.
 * ⛔ **THIS COLUMN MUST NEVER BECOME INDEPENDENTLY WRITABLE, SO THE EXPRESSION
 * STAYS.** `reply_id` exists to answer *"what does this run's own JSON already
 * say"*, and a column anybody could set directly would be a second place that
 * fact could be told, which is `CLAUDE.md`'s own named failure — a lint holding
 * its own copy of the pattern its guard reads is wrong even when both copies
 * agree today.
 *
 * ⚠️ **A SELF-CONTAINED COMPUTATION, NOT A BACKFILL `UPDATE`** — the DDL escape
 * `CLAUDE.md` §Convention tests names: `automation_runs` is `ENABLE`+`FORCE` row-
 * level secured and this migration establishes no tenant, so an `UPDATE` here
 * would match zero rows and report success. A generated column has no such
 * problem — Postgres computes it for every existing row as part of running the
 * `ALTER TABLE` itself, reading only the two `jsonb` columns already on the same
 * row, joining nothing.
 *
 * ⚠️ **`input` BEFORE `output` IN THE `COALESCE`, DELIBERATELY.**
 * `App\Jobs\Reviews\PostReplyJob::input()` writes `reply_id` unconditionally, at
 * `claimRun()` time, before `execute()` or `handoff()` runs — so it is present
 * on every row for this automation, including a `Failed` row whose `output` was
 * never written (`AutopilotJob::handle()`'s generic `catch` writes `error` and
 * `status` only). `output.reply_id`, from
 * {@see PostReplyJob::unpublished()}, is read as the fallback
 * for symmetry with any future automation that carries the identifier only in
 * its result.
 *
 * ⚠️ **`NULL` FOR EVERY OTHER AUTOMATION, WHICH IS THE POINT AND NOT A GAP.**
 * Only `PostReplyJob` and `GenerateReplyJob` write a `reply_id` key at all
 * (checked with a repository-wide grep before this migration was written); every
 * other automation's row computes `NULL` here, and `NULL` groups with `NULL`
 * under `DISTINCT ON` exactly as if the column were absent — so
 * {@see AutomationRunRetention::survivorIds()} grouping
 * on this column changes nothing for the location-level automations
 * `VisibilitySyncHistory` reads.
 *
 * ## The index
 *
 * ⚠️ **PARTIAL, ON PURPOSE.** Most rows in this table will never carry a
 * `reply_id` — it is one automation of 142 — so indexing every row would cost
 * write throughput on every automation to speed up a lookup only one of them
 * needs. `WHERE reply_id IS NOT NULL` keeps the index to the rows that answer
 * `ReplyPublicationStatus`'s question at all.
 *
 * ⚠️ **NO RLS STATEMENTS HERE**, on the idempotency-key and `job_uuid`
 * migrations' precedent: the table's own policy already covers every column it
 * will ever have.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE automation_runs
                ADD COLUMN reply_id bigint
                GENERATED ALWAYS AS (
                    coalesce(
                        nullif(input->>'reply_id', '')::bigint,
                        nullif(output->>'reply_id', '')::bigint
                    )
                ) STORED
        SQL);

        DB::statement(
            'CREATE INDEX idx_automation_runs_reply_id
                 ON automation_runs (automation_key, reply_id)
                 WHERE reply_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_automation_runs_reply_id');
        DB::statement('ALTER TABLE automation_runs DROP COLUMN IF EXISTS reply_id');
    }
};
