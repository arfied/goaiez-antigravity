<?php

declare(strict_types=1);

use App\Services\Impersonation\Impersonation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Answering the question `impersonation_sessions` was created asking.
 *
 * Its `business_id` shipped `restrictOnDelete` with a comment written for
 * whoever built Delete:
 *
 *   *"⚠️ Theirs, and RESTRICTED rather than cascading — which is the opposite of
 *   what every other business_id in this schema does … when the real deletion
 *   path is built it has to decide what happens to this history rather than
 *   inherit an answer from a default."*
 *
 * This is that decision. Both defaults it was protected from are wrong:
 *
 * - **Cascade** would destroy the record that support entered a customer's
 *   account, at the moment the customer left. Decision 622 made *reading* an
 *   account an audited act so that record exists; deleting the account must not
 *   become the way to erase it, and it would hand anyone who can request a
 *   deletion a route to clearing their own support-access history.
 * - **Restrict, kept** would mean any account a support agent ever opened can
 *   never be deleted — not a compliance posture but an accident, because
 *   needing support and asking to leave correlate.
 *
 * ## ⚠️ AND THE OBVIOUS THIRD ANSWER — `nullOnDelete` — IS REFUSED BY THE TABLE
 *
 * That was this migration's first draft and the suite rejected it with
 * `SQLSTATE[P0001] … the terms of a session are fixed when it starts`. Two
 * triggers already guarantee more than the foreign key does:
 *
 *   `impersonation_sessions_freeze_terms`   names `business_id` explicitly among
 *                                           the terms no UPDATE may change.
 *   `impersonation_sessions_refuse_delete`  refuses every DELETE, because
 *                                           *"support sessions are a permanent
 *                                           record"* (`28` §0 rule 9).
 *
 * A referential action is an UPDATE or a DELETE, and this table permits neither.
 * So the answer cannot be *what happens to the row* — nothing may happen to the
 * row. **The foreign key itself is what goes.**
 *
 * `business_id` stays exactly as it is: NOT NULL, never written twice, and now
 * free-standing. Deleting a business no longer touches this table at all — no
 * UPDATE for the freeze to refuse, no DELETE for the second trigger to refuse,
 * and the account it names is still named. That is the same posture
 * `audit_log.actor` and `staff_events.actor` already take: *a label and not a
 * key*, chosen because the thing it points at is allowed to disappear while the
 * record is not.
 *
 * ⚠️ **WHAT THIS COSTS, STATED RATHER THAN GLOSSED.** There is no longer a
 * database guarantee that a new session names a real business. What stands in
 * its place is {@see Impersonation} being the only
 * writer, held there by a chokepoint lint (562), and it opens a session for a
 * `Business` it has already loaded. That is weaker than a constraint and it is
 * the price of the row outliving its subject.
 *
 * ⚠️ **`agent_id` is untouched and stays `restrictOnDelete`.** That one is ours,
 * not theirs: it stops a *staff leaver* being deleted out of a session record,
 * which decision 743 built `UserRole::None` for rather than weakening. Deleting
 * a tenant has never had anything to do with it, and widening this migration to
 * reach it would be an unrelated security change riding in on a deletion slice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impersonation_sessions', function (Blueprint $table): void {
            // The column, its NOT NULL, its index and both triggers all stay.
            // Only the referential action goes.
            $table->dropForeign(['business_id']);
        });
    }

    public function down(): void
    {
        // ⚠️ Rows whose business has already been deleted cannot take the
        // constraint back, and they cannot be removed either — the delete
        // trigger refuses. So this reversal fails on any database where a
        // deletion has actually run, deliberately and loudly, rather than
        // quietly dropping the evidence to make itself succeed.
        DB::statement(<<<'SQL'
            ALTER TABLE impersonation_sessions
                ADD CONSTRAINT impersonation_sessions_business_id_foreign
                FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE RESTRICT
        SQL);
    }
};
