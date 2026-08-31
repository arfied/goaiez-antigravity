<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One tenant's Business Associate Agreement — `29` §2 rule 24.
 *
 * WHAT THIS EXISTS TO ANSWER. Rule 24 holds a PHI tenant's form values at
 * `schema_only` "until the BAA is executed", and until this table that phrase
 * had no state anywhere. `legal_documents` versions the *template* — counsel
 * drafts and publishes the BAA exactly like the other twelve documents
 * (decisions 417–420) — and nothing recorded that an agreement had been
 * executed with a given business. So rule 24's condition was unanswerable and
 * `29` §12.2's "healthcare-counsel review of the BAA template before the first
 * PHI tenant" had nothing enforcing it.
 *
 * ⚠️ TENANT-OWNED, UNLIKE `legal_documents`. The distinction is not a style
 * choice and copying the wrong neighbour would be a real defect. A legal
 * document is *our* text, identical for every tenant, which is why it has no
 * `business_id` and no RLS at all (417–420). An execution is one named business
 * agreeing to one exact version, carrying the names of the people who signed —
 * their data, inside the boundary, with `ENABLE` + `FORCE` row-level security
 * and the standard `tenant_isolation` policy below.
 *
 * ⚠️ `tenant_signer_name`, `tenant_signer_title` AND `goaiez_signer` ARE PII OF
 * NAMED INDIVIDUALS. What protects them is RLS plus the platform-staff gate on
 * the one screen that reads them. They are never logged, never placed in a URL,
 * and never rendered into a toast — toast text carries no personal data
 * (decision 104, CLAUDE.md), so the screen's toasts name the act and not the
 * signer.
 *
 * ⚠️ NO PDF, NO FILE PATH, AND DELIBERATELY NOT ONE "FOR LATER". Where an
 * executed BAA lives as a document is an open question for the owner, and the
 * default answer here would be R2 — which would make Cloudflare the holder of
 * an executed legal agreement carrying signer identities. That is a
 * subprocessor row the four inventory assertions of decisions 428–433 cannot
 * catch, because they compare outbound *host literals* in code against
 * `docs/SUBPROCESSOR-INVENTORY.md` and a filesystem write to a configured disk
 * is neither. A nullable path column added "for later" is how that becomes a
 * decision nobody made.
 *
 * THE FOUR CHECKS ARE DECISION 359'S RULING: the claim and the constraint land
 * together. Slice B's 314–316 is the warning that rides with it — a stated
 * enforcement layer is what stops the next reviewer looking, so each of these
 * is written after it exists rather than before. ⚠️ The fourth arrived a fix
 * wave later, because the ruling was applied to three claims and skipped for
 * the one the test suite had already described in prose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baa_records', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // THE EXACT VERSION EXECUTED, and the reason it is a foreign key
            // rather than a copied version string is decision 330's: the
            // pointer back to the words somebody actually agreed to has to
            // resolve forever. `legal_documents` rows are frozen once published
            // — a trigger refuses the update and the delete — so this reference
            // cannot rot, which is exactly why `consent_records` points the same
            // way.
            //
            // Nullable until executed: a pending record names no version,
            // because none has been agreed. The CHECK below is what stops that
            // nullability reaching an executed row.
            $table->foreignId('legal_document_id')
                ->nullable()
                ->constrained('legal_documents');

            // A string cast to App\Enums\BaaStatus, never a database enum
            // (CLAUDE.md): a DB enum is a second source of truth that drifts
            // from the PHP one, and Postgres enum values cannot be dropped or
            // reordered once added.
            $table->string('status');

            // The tenant's side of the signature. PII — see the docblock.
            $table->timestamp('tenant_signed_at')->nullable();
            $table->string('tenant_signer_name')->nullable();
            $table->string('tenant_signer_title')->nullable();

            // Ours. Also a named human: "GO AI EZ" is not a signature.
            $table->timestamp('goaiez_signed_at')->nullable();
            $table->string('goaiez_signer')->nullable();

            $table->timestamp('revoked_at')->nullable();
            $table->text('revoke_reason')->nullable();

            $table->timestamps();

            // ⚠️ UNIQUE, NOT MERELY INDEXED, AND THE UNIQUENESS IS THE POINT.
            // `BaaRecords::open()` reads then inserts with no lock, so two
            // simultaneous calls both see nothing and both write — decision
            // 350's shape. The consequence here is not a tidy duplicate: the
            // service takes the newest row by id, so a second *pending* row
            // landing after an executed one makes `isExecutedFor()` answer
            // false for a tenant that has signed, and rule 24's condition goes
            // silently unanswered with the evidence sitting in the same table.
            // One row per business is the intended invariant — re-execution
            // after revocation is deliberately not built — so the racing insert
            // fails closed with SQLSTATE 23505 instead.
            //
            // It is also the index the table needs. Postgres does not index
            // foreign-key columns automatically — only the primary key gets one
            // for free, which is a MySQL habit that does not transfer and was a
            // real finding on `destination_clicks` (314–316). The RLS policy
            // below adds `business_id` as a predicate to every query whether or
            // not the caller filtered on it, `BaaRecords` looks every record up
            // by business, and the foreign key is cascadeOnDelete.
            $table->unique('business_id');

            // The other foreign key, for the same reason and with no uniqueness
            // to it: one published version is executed by many tenants, and
            // `legal_documents` rows are frozen rather than deleted — but the
            // admin screen joins this way to render which version was signed,
            // and the migration that reasons at length about Postgres not
            // indexing foreign keys indexed one of its two.
            $table->index('legal_document_id');
        });

        // Rule 24's condition, made unfakeable. An "executed" BAA with no
        // evidence of execution is decision 290's "an acknowledgement nobody
        // made" in its most expensive form: this row is what a regulator, a
        // customer's counsel, or our own gate would be shown as proof that an
        // agreement exists, and a status string alone is not proof of anything.
        //
        // All five together, in one constraint, because the row is only
        // meaningful as a set — a signature with no date, or a date with no
        // version, answers none of the questions the record is kept to answer.
        DB::statement(<<<'SQL'
            ALTER TABLE baa_records
                ADD CONSTRAINT baa_records_executed_has_evidence
                CHECK (
                    status <> 'executed'
                    OR (
                        tenant_signed_at IS NOT NULL
                        AND tenant_signer_name IS NOT NULL
                        AND goaiez_signed_at IS NOT NULL
                        AND goaiez_signer IS NOT NULL
                        AND legal_document_id IS NOT NULL
                    )
                )
        SQL);

        // The same rule on the other end. A revocation with no date cannot
        // answer "was this agreement in force on the day that record was
        // written", which is the only question a revocation is ever asked.
        DB::statement(<<<'SQL'
            ALTER TABLE baa_records
                ADD CONSTRAINT baa_records_revoked_has_a_date
                CHECK (status <> 'revoked' OR revoked_at IS NOT NULL)
        SQL);

        // The state the suite already described in words and nothing refused:
        // "a row reading 'in force' and 'ended on the 3rd' at the same time,
        // which `isExecutedFor()` would answer true for". `revoke()` refuses to
        // produce it and `recordExecution()` refuses to re-execute a revoked
        // record — both service-layer checks, reachable by neither a repair
        // script nor a psql session, which is decision 359's whole ruling and
        // was applied to three of this migration's four claims.
        //
        // Stated as the executed row owning its own emptiness rather than as
        // "revoked implies not executed", because that is the direction the
        // damage runs: `isInForce()` reads `status`, so it is an *executed* row
        // carrying a revocation date that lies, and the row with a revoked
        // status is answered correctly whatever else it holds.
        DB::statement(<<<'SQL'
            ALTER TABLE baa_records
                ADD CONSTRAINT baa_records_executed_is_not_also_ended
                CHECK (status <> 'executed' OR revoked_at IS NULL)
        SQL);

        // The third layer under the enum and the service (216, 314–316). The
        // enum stops a bad value reaching the model and the service gives a
        // caller a sentence instead of a SQLSTATE; neither is reachable from a
        // repair script, a seeder or a psql session. And an unrecognised value
        // here does not fail loudly — `BaaStatus::isInForce()` would read it as
        // "not executed" if it could be constructed at all, so the damage is
        // silent in one direction and a cast exception in the other.
        //
        // A whitelist rather than a prohibition, which is the opposite of
        // `review_destinations_yelp_is_not_a_destination` and for the opposite
        // reason: there is no single forbidden value here, the set is small, and
        // a fourth status is a change to what "executed" means legally rather
        // than the routine growth decision 309's upsert was built to allow.
        DB::statement(<<<'SQL'
            ALTER TABLE baa_records
                ADD CONSTRAINT baa_records_status_is_known
                CHECK (status IN ('pending', 'executed', 'revoked'))
        SQL);

        // ENABLE alone is not enough: PostgreSQL exempts a table's owner from
        // its policies unless the table is also FORCEd, and migrations run as
        // the owner. Without FORCE the policy would exist, look correct, and do
        // nothing (decision 133).
        DB::statement('ALTER TABLE baa_records ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE baa_records FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON baa_records
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('baa_records');
    }
};
