<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `wizard_progress.current_step` becomes a string keyed to App\Enums\WizardStep.
 *
 * The column was an integer — a positional pointer into a fourteen-step list
 * (`29` §7.2) whose middle is unbuilt. Inserting Connect Google later renumbers
 * every step after it, and each wizard already in progress resumes on the wrong
 * screen with no error raised anywhere.
 *
 * ON FIRST APPLICATION EVERY ROW HOLDS 1 — TenantProvisioner writes
 * `current_step => 1` and no wizard existed to move it, so 1 is the only value
 * a fresh install's `up()` will ever see. `up()`'s mapping is written out in
 * full anyway, matching every position `down()` can produce, because `up()`
 * runs again on a `migrate:rollback` followed by `migrate` — and by then the
 * column can legitimately hold 2–5, since `down()` restores a wizard's real
 * position rather than collapsing it. A `1`-only mapping was only ever exact
 * while `down()` also collapsed to `1`; once `down()` stopped doing that,
 * reusing the old `up()` unmodified would silently reintroduce the same
 * "resumes on the wrong screen" failure on the re-forward path.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE wizard_progress ALTER COLUMN current_step DROP DEFAULT');

        // Mirrors down()'s mapping exactly. A fresh install only ever has 1 to
        // map — but a rollback-then-forward run can hand this 2-5 too, and
        // collapsing those to 'welcome' is the same data loss down() was just
        // fixed to stop causing, on the other direction.
        DB::statement(<<<'SQL'
            ALTER TABLE wizard_progress
            ALTER COLUMN current_step TYPE varchar(32)
            USING CASE current_step
                WHEN 1 THEN 'welcome'
                WHEN 2 THEN 'find_business'
                WHEN 3 THEN 'how_customers_reach'
                WHEN 4 THEN 'review_rules'
                WHEN 5 THEN 'done'
                ELSE 'welcome'
            END
        SQL);

        DB::statement("ALTER TABLE wizard_progress ALTER COLUMN current_step SET DEFAULT 'welcome'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE wizard_progress ALTER COLUMN current_step DROP DEFAULT');

        // ⚠️ down() MAPS EACH STEP TO ITS POSITION RATHER THAN COLLAPSING TO 1.
        // A bare `USING 1` would reset every in-progress wizard to the first
        // step on any rollback — the exact "resumes on the wrong screen with no
        // error" failure this migration exists to close, relocated to the path
        // nobody exercises until a bad deploy. Restoring a positional column
        // means restoring positions.
        DB::statement(<<<'SQL'
            ALTER TABLE wizard_progress
            ALTER COLUMN current_step TYPE integer
            USING CASE current_step
                WHEN 'welcome' THEN 1
                WHEN 'find_business' THEN 2
                WHEN 'how_customers_reach' THEN 3
                WHEN 'review_rules' THEN 4
                WHEN 'done' THEN 5
                ELSE 1
            END
        SQL);

        DB::statement('ALTER TABLE wizard_progress ALTER COLUMN current_step SET DEFAULT 1');
    }
};
