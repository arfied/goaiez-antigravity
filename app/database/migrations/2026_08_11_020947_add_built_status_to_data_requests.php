<?php

declare(strict_types=1);

use App\Enums\DataRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `data_requests.status` gains `built` — the state between a ZIP existing and a
 * tenant having collected it (1999).
 *
 * A tenant-export ask closed as `fulfilled` the moment the build finished, and
 * that word asserts a delivery this application can neither make nor observe:
 * the email relay vendor is not picked (1191–1195), bounce handling is unbuilt
 * (open question H), and 1905 refuses to let an agent fetch the ZIP on the
 * owner's behalf. So a statutory subject-access request read *Done* while
 * nobody on the ops side could tell whether the tenant had received anything.
 *
 * ## ⚠️ TWO OBJECTS HAVE TO MOVE TOGETHER, AND ONE OF THEM IS EASY TO MISS
 *
 * The CHECK is the obvious one. **`data_requests_queue_index` is the other**:
 * it is a *partial* index `WHERE status IN ('open','awaiting_deletion')`, so a
 * new open-ish status that is not in its predicate leaves the queue read —
 * `DataRequests::open()`, ordered by `due_at` — falling off the index for
 * precisely the rows this migration creates. Nothing would fail; it would
 * simply stop being indexed, which is the quietest kind of regression.
 *
 * ⚠️ **`data_requests_one_open_erasure_per_business` is deliberately NOT
 * touched.** It is `WHERE kind = 'erasure' AND status IN (...)`, and `built` is
 * a tenant-export state only — an erasure never reaches it. Widening it would
 * make the "one open erasure per account" rule quietly weaker in a way no test
 * asks about.
 *
 * ⚠️ **Both statuses are rebuilt from {@see DataRequestStatus} rather than
 * typed**, the same way the original migration built them, so the CHECK cannot
 * drift from the PHP enum. CLAUDE.md's rule stands: this is a `string` column
 * with a CHECK, never a Postgres `enum`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $statuses = collect(DataRequestStatus::cases())
            ->map(fn (DataRequestStatus $status): string => "'".$status->value."'")
            ->implode(', ');

        DB::statement('ALTER TABLE data_requests DROP CONSTRAINT data_requests_status_is_known');

        DB::statement(<<<SQL
            ALTER TABLE data_requests
                ADD CONSTRAINT data_requests_status_is_known
                CHECK (status IN ({$statuses}))
        SQL);

        DB::statement('DROP INDEX data_requests_queue_index');

        DB::statement(<<<'SQL'
            CREATE INDEX data_requests_queue_index
                ON data_requests (due_at, id)
                WHERE status IN ('open', 'awaiting_deletion', 'built')
        SQL);
    }

    public function down(): void
    {
        DB::statement("UPDATE data_requests SET status = 'fulfilled' WHERE status = 'built'");

        DB::statement('DROP INDEX data_requests_queue_index');

        DB::statement(<<<'SQL'
            CREATE INDEX data_requests_queue_index
                ON data_requests (due_at, id)
                WHERE status IN ('open', 'awaiting_deletion')
        SQL);

        DB::statement('ALTER TABLE data_requests DROP CONSTRAINT data_requests_status_is_known');

        DB::statement(<<<'SQL'
            ALTER TABLE data_requests
                ADD CONSTRAINT data_requests_status_is_known
                CHECK (status IN ('open', 'awaiting_deletion', 'fulfilled', 'refused', 'cancelled'))
        SQL);
    }
};
