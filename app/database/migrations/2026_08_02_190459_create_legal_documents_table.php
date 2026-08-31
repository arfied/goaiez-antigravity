<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The versioned legal documents `29` §6.1 specifies and nothing built.
 *
 * `LegalDocumentController`'s own docblock has said so since row 3 slice C:
 * "`29` §6.1 also specifies these as rendered from a versioned `legal_documents`
 * table. That table does not exist and building it is not this slice's; when it
 * lands, this controller reads from it and the allowlist below becomes its
 * contents." This is that table.
 *
 * NO `business_id`, AND THAT IS THE POINT. These are *our* documents — the terms
 * under which every tenant uses the platform. A tenant-owned legal document
 * would mean each business editing the agreement it is bound by. The model
 * therefore joins the `TenancyTest` scope allowlist with its reasoning
 * written there.
 *
 * ⚠️ A PUBLISHED ROW IS IMMUTABLE, AND THIS MIGRATION IS WHERE THAT BECOMES TRUE
 * RATHER THAN INTENDED. `consent_records.disclosure_version` is the only pointer
 * from a stored consent back to the exact words a customer read (decision 330).
 * If a published body can be edited, every consent record written against it
 * silently starts pointing at words that person never saw — and producing those
 * words on demand is the whole of `29` §2 rule 7. **The damage is not
 * recoverable by editing the row back**, because nothing anywhere records what
 * the text used to be.
 *
 * That puts it in slice B's category (314–316, 359, 383): a rule whose damage is
 * unrecoverable earns database enforcement, not only a service check. A CHECK
 * constraint cannot express it — CHECK sees one row's new values and never the
 * old ones — so this is **the first trigger in this schema**, and it is
 * deliberate rather than a reach for a new tool. The model throws too, because a
 * developer wants an exception naming the rule rather than a SQLSTATE, and the
 * service is where the caller is refused politely. Three layers, each catching
 * what the others cannot.
 *
 * A DRAFT IS FREELY EDITABLE. Immutability begins at publication, which is the
 * moment a document can be cited. The one permitted update to a row is the
 * draft → published transition itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table): void {
            $table->id();

            // A string cast to LegalDocumentType, never a database enum
            // (`CLAUDE.md`). The values are the drafting packs' own doc_type
            // slugs — `39`'s for most of them, R53's for the internal refund
            // policy CC-4 added.
            $table->string('doc_type');

            // Free-form rather than a number: `39` seeds its drafts as "0.9",
            // and counsel's own convention for a revision is theirs to set. What
            // matters is that a version, once published, names one exact text
            // forever.
            $table->string('version');

            $table->string('title');
            $table->text('body');

            // `39`'s checklist step 5: "wide launch stays locked while any served
            // doc is a placeholder". The flag is the input to that gate, so it
            // is a column rather than something inferred from the body.
            $table->boolean('is_placeholder')->default(true);

            // Null means draft. Publication is the act that freezes the row.
            $table->timestamp('published_at')->nullable();

            // ⚠️ THE ROW IS ITS OWN AUDIT TRAIL, AND THAT IS A CONSTRAINT RATHER
            // THAN A PREFERENCE. `29` §2 rule 42 requires every sensitive action
            // in an append-only log, and `AuditService::record()` opens with
            // `Tenancy::idOrFail()` — it cannot record an action that belongs to
            // no tenant, and publishing the terms every tenant is bound by is
            // exactly that. Filing it under whichever business the admin
            // happened to be viewing would bury a platform act in one tenant's
            // log and hide it from the other forty.
            //
            // So these four columns are the record: who reviewed, when, who
            // published, when. The trigger below makes them unfalsifiable after
            // the fact, which is the property an audit log is for. When a
            // platform-scoped audit log exists, this becomes a second copy
            // rather than the only one.
            $table->string('published_by')->nullable();

            // `39`'s checklist step 3: "counsel checkbox (named reviewer)". A
            // string, not a user id — the reviewer is frequently outside counsel
            // and has no account here, and a foreign key would have nothing to
            // point at.
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // One text per (type, version), forever. This is what makes a stored
            // version pointer resolvable.
            $table->unique(['doc_type', 'version']);

            // The public read path: newest published row for one type.
            $table->index(['doc_type', 'published_at']);
        });

        // Reviewed and published are two halves of one act, and a row claiming
        // review without a reviewer is worse than one claiming none: `39`'s
        // checklist makes the named reviewer the record that review happened.
        DB::statement(<<<'SQL'
            ALTER TABLE legal_documents
                ADD CONSTRAINT legal_documents_review_is_attributed
                CHECK ((reviewed_at IS NULL) = (reviewed_by IS NULL))
        SQL);

        // The same rule for the act this table exists to record. An anonymous
        // publication is the one shape that would make these columns useless as
        // the audit trail they are standing in for.
        DB::statement(<<<'SQL'
            ALTER TABLE legal_documents
                ADD CONSTRAINT legal_documents_publication_is_attributed
                CHECK ((published_at IS NULL) = (published_by IS NULL))
        SQL);

        // Immutability, at the layer that survives a repair script.
        //
        // TG_OP is read rather than writing two triggers because DELETE and
        // UPDATE differ in what they must return — NEW is NULL on a DELETE, and
        // returning it would cancel the statement silently instead of raising.
        //
        // 23514 is chosen deliberately: it is the SQLSTATE a CHECK violation
        // raises, so a caller that already handles "the database refused this
        // write" handles this identically. The rule is a check constraint in
        // everything but the mechanism.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION legal_documents_freeze_published()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF OLD.published_at IS NOT NULL THEN
                    RAISE EXCEPTION
                        'legal_documents row % is published and is immutable (attempted %). Publish a new version instead.',
                        OLD.id, TG_OP
                        USING ERRCODE = '23514';
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER legal_documents_published_rows_are_immutable
                BEFORE UPDATE OR DELETE ON legal_documents
                FOR EACH ROW
                EXECUTE FUNCTION legal_documents_freeze_published()
        SQL);
    }

    public function down(): void
    {
        // The trigger goes with the table; the function does not, so it is
        // dropped by name. A leftover function is invisible and would silently
        // survive a rebuild.
        Schema::dropIfExists('legal_documents');

        DB::statement('DROP FUNCTION IF EXISTS legal_documents_freeze_published()');
    }
};
