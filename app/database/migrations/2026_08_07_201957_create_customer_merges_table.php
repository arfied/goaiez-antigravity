<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The change set a contact merge rides on (`34` §1.2 — *"merge is a change
 * set"*, `34` §7's build-failing *"merge is undoable 30 days"*).
 *
 * ⚠️ **UNDO IS THE REQUIREMENT AND MERGE IS THE SIDE EFFECT.** A merge that
 * cannot be reversed is a data-loss feature wearing a tidy-up label (1326), so
 * this table is mandatory rather than an implementation choice: the thirty days
 * is a promise about state that has to be somewhere, and `customers` has nowhere
 * to put "what this row said before".
 *
 * ## Why this is not a generic `change_sets` table
 *
 * `34` §1.2's phrase is a requirement about reversibility, not a schema
 * instruction, and `34` Part 6's schema sketch names no columns for one. A
 * generic store with exactly one producer is decision 272's shape wearing a
 * general name — the generality has no second caller, and a polymorphic undo
 * dispatcher whose `match` has one arm cannot be tested for the branches it does
 * not have. Named for the change it records; extracted the day a second kind of
 * change needs an undo, which is a migration and not a redesign.
 *
 * ## What the payload holds, and why both sides of it
 *
 * `AuditService::recordChange()`'s rule — *"an audit entry that records only the
 * new value cannot answer what happened"* — with teeth on it, because here the
 * before-state is not documentation, it is the only copy. `changes` carries the
 * survivor's prior name and tags, **both rows' prior email and phone**, and
 * which side the owner picked for each choosable field.
 *
 * ⚠️ **THE MERGED-AWAY ROW'S IDENTIFIERS ARE CLEARED BY THE MERGE**, so this
 * payload is the only place they exist until an undo puts them back. Two reasons
 * they cannot stay on the row: `customers` carries `UNIQUE(business_id, email)`
 * and `UNIQUE(business_id, phone)`, so a hidden row goes on holding a live slot
 * the owner can no longer see; and `FeedbackSubmission::findByIdentifier()`
 * matches on the raw column with no exclusion for archived or merged rows, so
 * the next submission carrying that address would land on the row nobody can
 * open.
 *
 * ## What is deliberately not a column
 *
 * **A retention or prune marker.** The undo expires at thirty days; the row does
 * not. It is the record of what was done and by whom, and `44` §8's Advanced
 * duplicate queue — a later half — is the first thing that would want to read a
 * merge that can no longer be undone. A prune job would delete the account of a
 * merge on the day it became permanent, which is exactly the day it starts
 * mattering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_merges', function (Blueprint $table): void {
            $table->id();

            // The tenant key every RLS policy compares. Present alongside the
            // two customer ids for the reason `consent_records` states: the
            // policy needs its own column, and BelongsToTenant fills it.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // The contact that survives, and the one folded into it.
            //
            // ⚠️ **CASCADE, AND THE FIRST VERSION OF THIS MIGRATION SAID
            // RESTRICT.** The argument for RESTRICT was that this row is the
            // only copy of what both contacts held, so deleting a customer must
            // not silently take the undo with it — and the caller it was
            // protecting against **does not exist**: nothing in `app/` deletes a
            // customer, and `44` §8's third contact state is a tombstone with a
            // seven-day restore rather than a hard delete.
            //
            // What does exist is `28` §9.5's Delete, which is one
            // `$business->delete()` relying on *"78 cascadeOnDelete FKs
            // [carrying] the tenant's own data out with this line"*. A RESTRICT
            // is checked immediately rather than deferred, so whether that
            // succeeds depends on Postgres reaching this table's own cascade
            // from `businesses` before it reaches `customers`' — **which it does
            // today and which nothing documents**. A tenant that cannot be
            // deleted is a compliance failure that sweeps forever with a foreign
            // key error naming a table nobody was thinking about, and it would
            // arrive by an ordering change rather than by a code change.
            //
            // ⚠️ **AND THE TEST CANNOT TELL THE TWO APART, WHICH IS THE POINT
            // RATHER THAN A GAP IN IT.** `CustomerMergeTest` drives the whole
            // business delete with a merge in place, and it passes under
            // RESTRICT **and** under CASCADE — mutation confirmed it, because
            // RESTRICT simply does not fire today. So this is not a constraint
            // a test chose; it is one chosen because the alternative's success
            // rests on behaviour nothing promises. The test stays as the
            // tripwire that reddens if that ever changes.
            $table->foreignId('survivor_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('merged_id')->constrained('customers')->cascadeOnDelete();

            // Who merged, and who undid it. Nullable on delete because a staff
            // or owner account can leave and the record of the change must
            // outlive the employment (743's reasoning).
            $table->foreignId('merged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('undone_by')->nullable()->constrained('users')->nullOnDelete();

            // The change set itself — see the class docblock.
            $table->jsonb('changes');

            // ⚠️ NOT NULL, AND NOT `created_at`. The undo window is measured
            // from here, so an undated merge would be one whose thirty days
            // cannot be evaluated — and 40 of 43 tables in this schema carry a
            // nullable `created_at` because that is Laravel's default (289).
            // A window measured off a nullable column is a window that opens
            // forever the first time somebody inserts without it.
            $table->timestamp('merged_at');

            $table->timestamp('undone_at')->nullable();

            $table->timestamps();

            // RLS predicates business_id on every query and Postgres indexes no
            // foreign key automatically — a MySQL habit that does not transfer
            // (314–316).
            $table->index(['business_id', 'survivor_id']);
            $table->index(['business_id', 'merged_id']);
        });

        DB::statement('ALTER TABLE customer_merges ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE customer_merges FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON customer_merges
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);

        // Decision 359's ruling and 216's three layers: the service gives a
        // caller an error they can act on, and the CHECK catches the repair
        // script that never reached the service.
        //
        // A contact merged into itself is not a smaller merge, it is a row whose
        // undo would restore a survivor from its own cleared identifiers.
        DB::statement(<<<'SQL'
            ALTER TABLE customer_merges
                ADD CONSTRAINT customer_merges_two_different_contacts
                CHECK (survivor_id <> merged_id)
        SQL);

        // The state and its actor land together, in both directions — 1323's
        // pairing rule, which exists because two columns that can disagree about
        // one fact is what got `customers.is_suppressed` dropped (286). An undo
        // with no actor cannot answer "who put this back", and an actor with no
        // moment cannot answer "when".
        DB::statement(<<<'SQL'
            ALTER TABLE customer_merges
                ADD CONSTRAINT customer_merges_undo_carries_its_actor
                CHECK ((undone_at IS NULL) = (undone_by IS NULL))
        SQL);

        // ⚠️ ONE LIVE MERGE PER MERGED-AWAY CONTACT, ENFORCED WHERE THE SERVICE
        // CANNOT BE BYPASSED. `customers.merged_into_id` holds one value, so two
        // live merge rows naming the same loser would leave the undo of the
        // first restoring a row the second still claims — and the screens would
        // show two Undo buttons that disagree about what they reverse. A partial
        // index rather than a plain unique, because a contact merged, undone and
        // merged again is the ordinary case rather than a violation.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX customer_merges_one_live_merge_per_contact
                ON customer_merges (merged_id)
                WHERE undone_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_merges');
    }
};
