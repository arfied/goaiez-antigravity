<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `audit_log` refuses UPDATE at the database (decisions 3020–3029).
 *
 * DPA Annex II §5 represents *"append-only audit logs"* to every counterparty
 * who signs one. Until this migration that was true only at the model layer —
 * `AuditLogEntry` throws on `updating`/`deleting`, and its own docblock records
 * the hole: a Query Builder update bypasses model events. `DB::table('audit_log')
 * ->update(...)` returned 1 row affected, verified before this was written.
 *
 * ⚠️ THIS IS THE UPDATE HALF ONLY, AND THE DELETE HALF IS DELIBERATELY OWED.
 * `audit_log.business_id` is `cascadeOnDelete`, so `TenantDeletion::execute()`'s
 * `$business->delete()` is a *legitimate* remover — the statutory erasure path of
 * `28` §9.5. A row-level `BEFORE DELETE` trigger fires on an FK cascade and
 * aborts it: installing one reddened three of `TenantDeletionTest`'s cases with
 * `DELETE FROM ONLY "public"."audit_log" WHERE $1 = "business_id"`. Refusing
 * DELETE here would trade a tamper hole for a broken right-to-erasure, which is
 * the worse failure. See 3020–3029 for what a future slice must resolve.
 *
 * UPDATE carries no such risk: nothing in `app/` updates an audit row, and a
 * cascade only deletes. It is also the vector that matters more — a destroyed
 * tenant is loud and obvious, a silently rewritten history is the one nobody can
 * detect afterwards.
 *
 * The model guard stays. Two layers, the way RLS sits beneath the global scopes:
 * the model gives a readable `LogicException` to application code, the trigger
 * catches what the model cannot see.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Follows `legal_documents_freeze_published()`, this schema's first
        // trigger. No TG_OP branch is needed here because the trigger is scoped
        // to UPDATE alone, so NEW is never NULL — but nothing is returned
        // either, since RAISE always fires and the function never falls through.
        //
        // 23514 is the SQLSTATE a CHECK violation raises, chosen for the same
        // reason legal_documents chose it: a caller that already handles "the
        // database refused this write" handles this identically. The rule is a
        // check constraint in everything but the mechanism.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_log_refuse_update()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION
                    'audit_log row % is append-only and cannot be updated. Write a new entry instead.',
                    OLD.id
                    USING ERRCODE = '23514';
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER audit_log_rows_are_never_updated
                BEFORE UPDATE ON audit_log
                FOR EACH ROW
                EXECUTE FUNCTION audit_log_refuse_update()
        SQL);
    }

    /**
     * ⚠️ THE INVERSE OF FREEZING AN AUDIT LOG IS UNFREEZING ONE, AND THAT IS THE
     * POINT OF THIS METHOD RATHER THAN AN ARGUMENT AGAINST IT.
     *
     * The honest options were: drop the trigger, or throw and make the migration
     * irreversible. Throwing was refused. `migrate:rollback` is not the threat
     * model — anyone who can run it can run `DROP TRIGGER` directly, so an
     * irreversible `down()` stops no attacker and instead breaks
     * `migrate:refresh` and every developer rebuilding a local schema. A
     * migration that cannot be rolled back is also one whose `up()` cannot be
     * exercised twice, which is how the mutation test for this trigger is run.
     *
     * What makes the tamper detectable is not this method's absence: it is that
     * dropping the trigger is a schema change, and a schema change is visible in
     * a way that an `UPDATE` slipped through a repair script is not. That is the
     * whole reason the trigger beats the model guard.
     *
     * The function is dropped by name as well. `legal_documents`' `down()`
     * records why: the trigger goes with its table, the function does not, and a
     * leftover function is invisible and would silently survive a rebuild. Here
     * the table outlives the trigger entirely, so both must be named.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_log_rows_are_never_updated ON audit_log');

        DB::statement('DROP FUNCTION IF EXISTS audit_log_refuse_update()');
    }
};
