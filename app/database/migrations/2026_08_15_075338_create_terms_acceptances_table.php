<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the business agreed to when it signed up (T176 P22, R24).
 *
 * ⛔ **TERMS ARE FOR TENANTS, NEVER FOR END CUSTOMERS** — R24 is an owner ruling
 * of record and this table is the shape of it. A row names a **business** and
 * the person who created it; there is no `customer_id` here and there must
 * never be one. A reactivation recipient's sending basis is the tenant's import
 * attestation and a missed caller's is their own inbound contact — neither is a
 * terms acceptance, and no consent screen, checkbox or terms link is ever
 * injected into a conversation thread.
 *
 * ⚠️ **A SEPARATE TABLE RATHER THAN A ROW IN `consent_records`**, for
 * `auto_renewal_acknowledgements`' reasons exactly: that table's `customer_id`
 * and `channel` are both NOT NULL, and this record has neither — the person
 * accepting is the **account holder**, not one of their own customers, and it
 * permits no contact of any kind. Writing it there would also recompute
 * `customers.messaging_lane`, whose whole job is to tell platform-captured
 * consent from a tenant's assertion.
 *
 * ⚠️ **IT IS NOT A `terms_accepted` COLUMN ON `users` OR `businesses`.** That is
 * the cheap shape and it is the one that answers nothing: what has to be
 * provable is **which document, which version, what wording, on which page,
 * from what agent, when, and by whom**. A boolean beside an account proves only
 * that a column was written. It is append-shaped by nature as well — a new
 * version of the Terms is a new acceptance, and both rows are evidence.
 *
 * ⚠️ **ONE ROW PER DOCUMENT, NOT ONE ROW PER SIGNUP.** Three documents are
 * accepted in one act (Terms, SMS & Communications Terms, Privacy) and each is
 * versioned independently by counsel, so a single row could only name one of
 * them honestly. `legal_document_id` points at the frozen published row, which
 * is what makes "what did they agree to" answerable years later without
 * reconstructing anything.
 *
 * TENANT-OWNED, SO ENABLE + FORCE AND A POLICY IN THIS MIGRATION. What one
 * business agreed to is that business's evidence and nobody else's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms_acceptances', function (Blueprint $table): void {
            $table->id();

            // business_id for the RLS policy, filled from context by
            // BelongsToTenant. ⛔ There is deliberately no customer column —
            // see this migration's docblock, and the lint that asserts it.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Which document — a string cast to LegalDocumentType, never a
            // database enum. Denormalised beside the foreign key below so that
            // "has this tenant accepted the SMS terms?" is one indexed read
            // rather than a join, and so the row still says what it is about if
            // it is ever read outside this application.
            $table->string('doc_type');

            // ⚠️ THE EXACT VERSION, WHICH IS THE WHOLE POINT OF THE RECORD.
            // `legal_documents` freezes a published row with a trigger, so this
            // foreign key resolves to the same words forever. RESTRICT rather
            // than cascade: an acceptance that points at nothing is worse than a
            // failed delete, and a published document cannot be deleted anyway.
            $table->foreignId('legal_document_id')->constrained()->restrictOnDelete();

            // The version string as published, alongside the key, for
            // `consent_records.disclosure_version`'s reason: the record has to
            // be readable as evidence on its own face.
            $table->string('version');

            // checkbox|sso_continue. A column rather than an assumption, because
            // the proof blob's `checkbox_state` is only meaningful beside it —
            // and because the two mechanisms are not equally strong, so a reader
            // has to be able to tell them apart.
            $table->string('method');

            // Who agreed — 'user:14', the actor string every service in this
            // codebase takes, never a foreign key, so the record survives the
            // user row being deleted (PurchaseConfirmation's reasoning).
            $table->string('accepted_by');

            // { url, ip_hash, user_agent, checkbox_state, disclosure_text }.
            // ip_hash only: raw IP is never stored, anywhere (`29` §2 rule 21).
            // NOT NULL — this is only ever shown on a page we serve, so an
            // unproved row here is a bug rather than a surface with nothing to
            // prove.
            $table->jsonb('proof');

            // Append-shaped, the same as consent_records,
            // review_phi_consents and auto_renewal_acknowledgements: an
            // acceptance is an event.
            $table->timestamp('created_at')->nullable();

            $table->index(['business_id', 'doc_type']);
        });

        DB::statement('ALTER TABLE terms_acceptances ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE terms_acceptances FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON terms_acceptances
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_acceptances');
    }
};
