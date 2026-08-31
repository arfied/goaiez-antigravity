<?php

declare(strict_types=1);

use App\Enums\PurchaseReconciliationVerdict;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the sweep asked the gateway, and what it did about the answer — the
 * reconciliation read decision 3483 recorded as owed and left unbuilt.
 *
 * ## Why a row exists at all, rather than a counter on `credit_purchases`
 *
 * ⛔ **THE SWEEP MAY NOT WRITE TO `credit_purchases` EXCEPT THROUGH
 * `CreditPurchases::settle()`, AND THAT IS NOT TIDINESS.** `settle()` claims its
 * purchase with `UPDATE … WHERE status IN ('pending','authorized')`, so **any**
 * other status this sweep wrote would permanently disarm the ordinary settlement
 * path. A gateway that says "declined" today and delivers a captured-payment
 * notification tomorrow is unlikely; a sweep that made that notification
 * uncreditable would produce exactly the failure it was built to fix, silently,
 * and only for the tenants it had already given up on. So everything the sweep
 * concludes lives here instead, and the purchase row keeps its own vocabulary.
 *
 * ## What the rows are for, in order of how much they matter
 *
 *   the bound      An inconclusive answer is retried, and *counting the retries*
 *                  is what stops an unbounded run of reads against a dead
 *                  transaction — a rate-limit incident and a cost with no
 *                  ceiling. The count is `SELECT count(*)` over these rows, so
 *                  there is no counter to get wrong and no lost update.
 *   the stop       One terminal verdict ends the sweep's interest for ever
 *                  ({@see PurchaseReconciliationVerdict::stopsTheSweep()}).
 *   the record     A credit written from a reconciliation rather than from a
 *                  notification is an act somebody will need to reconstruct.
 *                  `audit_log` carries it too; this carries the vendor's own word
 *                  for the state, which the audit metadata alone would not
 *                  explain.
 *
 * ⚠️ **APPEND-ONLY BY CONVENTION AND NOT BY TRIGGER**, unlike `credit_ledger`.
 * Nothing in `app/` updates or deletes one — the sweep only inserts — and no
 * money is decided from these rows, so the machinery `credit_ledger` carries
 * would be a claim about enforcement this migration does not make (314–316).
 *
 * ⚠️ **`detail` IS OUR OWN SENTENCE AND NEVER THE VENDOR'S TEXT.** Authorize.Net's
 * `errorText` quotes the value it rejected — a card's last four, an email — and
 * `AuthorizeNetRequestFailed` exists because of it. What lands here is a code, a
 * documented status name, and words this application wrote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_purchase_reconciliations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->foreignId('credit_purchase_id')
                ->constrained('credit_purchases')
                ->cascadeOnDelete();

            // A string cast to App\Enums\PurchaseReconciliationVerdict — never a
            // Postgres enum type (`CLAUDE.md`, decision 863) — with the CHECK
            // below as 216's second layer.
            $table->string('verdict');

            // ⚠️ THE VENDOR'S OWN WORD, KEPT VERBATIM AND NEVER INTERPRETED
            // TWICE. `settledSuccessfully`, `capturedPendingSettlement`,
            // `succeeded`, `requires_payment_method`. Two vocabularies share this
            // column because the question — "what did they say" — is the same one,
            // and an operator reading a row needs the term they can search the
            // vendor's own documentation for. Nullable: an unreachable vendor said
            // nothing at all.
            $table->string('gateway_status')->nullable();

            // Our sentence about it. See the docblock: never a vendor message.
            $table->string('detail');

            $table->timestamp('attempted_at');

            // RLS predicates business_id on every query and Postgres does not
            // index a foreign key automatically. The reader's questions are "how
            // many attempts has this purchase had" and "has one of them been
            // terminal", both keyed on the purchase.
            $table->index(['business_id', 'credit_purchase_id', 'id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE credit_purchase_reconciliations
                ADD CONSTRAINT credit_purchase_reconciliations_verdict_is_known
                CHECK (verdict IN (
                    'credited',
                    'already_settled',
                    'mismatched',
                    'not_paid',
                    'refunded',
                    'ambiguous',
                    'unreachable',
                    'abandoned'
                ))
        SQL);

        // An attempt that explains nothing is the row an operator is asked about
        // and cannot answer — `credit_purchases_failure_is_explained`'s reasoning
        // one table over, and the reason `detail` is NOT NULL rather than
        // nullable-with-a-convention.
        DB::statement(<<<'SQL'
            ALTER TABLE credit_purchase_reconciliations
                ADD CONSTRAINT credit_purchase_reconciliations_are_explained
                CHECK (btrim(detail) <> '')
        SQL);

        DB::statement('ALTER TABLE credit_purchase_reconciliations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE credit_purchase_reconciliations FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON credit_purchase_reconciliations
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_purchase_reconciliations');
    }
};
