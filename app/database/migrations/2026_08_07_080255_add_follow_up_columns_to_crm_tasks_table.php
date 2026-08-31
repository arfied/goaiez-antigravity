<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The three columns `crm_tasks` was missing (decision 1322), and the CHECK that
 * pairs the state with its timestamp (1323).
 *
 * ⚠️ **NOTHING IS RENAMED.** `44` §12 spells these columns `body`,
 * `assignee_user_id` and `due_on`; the live table has `title`, `assigned_to`
 * and `due_at` from `DATA-MODEL` §5.5, and `DATA-MODEL` is the schema
 * authority. A rename would touch a model, a factory, an isolation test and an
 * index for no behaviour. What is added is the three absences that carry
 * meaning:
 *
 *   done_at        `status` says *whether*, nothing says *when*. `44` event 217
 *                  and "overdue plainly marked" both need the moment.
 *   snoozed_until  snoozing by moving `due_at` destroys the date "overdue" is
 *                  measured against — the feature would erase its own evidence.
 *   created_by     `44` event 216 carries *source: manual/suggested* against a
 *                  row that had no author. `crm_notes.user_id` exists for
 *                  exactly this reason.
 *
 * ⚠️ **THE CHECK IS 359/383'S PRECEDENT**: two columns that can disagree about
 * one fact is what got `customers.is_suppressed` dropped (286), and the repair
 * script that reaches neither the enum nor the service is what a CHECK is for.
 * `status = 'done'` if and only if `done_at IS NOT NULL`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table): void {
            $table->timestamp('done_at')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Existing rows all carry status = 'open' and gain done_at = NULL, so
        // the constraint is immediately true and needs no backfill — which
        // matters, because this table is RLS-FORCEd and a migration-time
        // UPDATE would silently touch zero rows (decision 319).
        DB::statement(<<<'SQL'
            ALTER TABLE crm_tasks
                ADD CONSTRAINT crm_tasks_done_state_matches_timestamp
                CHECK ((status = 'done') = (done_at IS NOT NULL))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE crm_tasks DROP CONSTRAINT IF EXISTS crm_tasks_done_state_matches_timestamp');

        Schema::table('crm_tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['done_at', 'snoozed_until']);
        });
    }
};
