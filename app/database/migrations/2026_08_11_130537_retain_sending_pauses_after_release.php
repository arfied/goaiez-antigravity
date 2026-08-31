<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A tenant's pause must survive its own release — decision 2119(a), 2470–2479.
 *
 * ## What was wrong, in one sentence
 *
 * `SendingGuard::resume()` called `$pause->delete()`, so **restarting a tenant
 * destroyed the only record of why they had been stopped**. The row carries the
 * reason, the actor — `tripped_by`, which is the one thing distinguishing 2102's
 * automatic trip from an operator's deliberate pause — and `observed_rate_bp`,
 * *"a snapshot, never recomputed"* in the creating migration's own words. By the
 * time anybody asks, the window has rolled and that number cannot be obtained
 * from anywhere else in this schema.
 *
 * ⚠️ **AN AUTOMATIC CONTAINMENT WHOSE INCIDENTS CANNOT BE REVIEWED IS NOT
 * ANSWERABLE**, and 2101 is why this one in particular has to be: R8 sends
 * attested lists over the **GOAIEZ** 10DLC brand from **our own** number pool,
 * so the platform carries the carrier reputation for every tenant at once. The
 * questions asked after a trip are *how often has this tenant done this*, *at
 * what rate*, and *who let them start again* — and a deleted row answers none.
 *
 * ⚠️ **AND THE AUDIT LOG DID NOT COVER FOR IT.** `sending.paused` is recorded
 * with `entity_id` pointing at this row, so every resume left a **dangling
 * audit reference to a row that no longer existed**. The append-only log is what
 * an auditor reads; this table is the incident series an operator queries. The
 * full division is written up on `SendingGuard::resume()`.
 *
 * ## The unique constraint had to go, and what replaces it
 *
 * The creating migration made `business_id` unique with a reason: *"a tenant is
 * paused or is not. Two rows would mean two answers, and the resume path would
 * clear one of them."* That reason is exactly right and it survives — it just
 * needs saying about **live** rows rather than about all of them, because
 * retaining released rows means a tenant paused three times has three.
 *
 * ⚠️ **A PARTIAL UNIQUE INDEX, IN THE DATABASE, NOT A CHECK IN PHP.** This is
 * the constraint that stops the resume path clearing one of two rows, and the
 * write it has to hold against is 2102's automatic trip, which runs unattended
 * on the send path while an operator may be pausing the same tenant by hand.
 * A read-then-insert in the service leaves that window open on precisely the
 * path nobody is watching.
 *
 * ## Bounding the free text, both columns
 *
 * `note` was `text` and unbounded, which was defensible while the row was
 * deleted on resume. **It is not defensible now**: the row is permanent, so an
 * unbounded box lets whoever fills it paste a document into a record nothing
 * prunes — `TenantPause::normaliseReason()`'s reasoning, which reached the same
 * answer for `audit_log`. Both notes are capped at 500 characters, the service
 * truncates before writing, and the CHECK below is the backstop against a second
 * writer that forgets to.
 */
return new class extends Migration
{
    /**
     * The cap on both note columns, in characters.
     *
     * `TenantPause::normaliseReason()`'s 500, deliberately the same number: a
     * developer who has met one of these should not have to look up the other.
     */
    private const int NOTE_LIMIT = 500;

    public function up(): void
    {
        Schema::table('sending_pauses', function (Blueprint $table): void {
            // ⚠️ **THE RELEASE IS THE END OF AN INCIDENT, NOT THE DELETION OF
            // ONE.** `released_at IS NULL` is now what "paused" means, and it is
            // the only thing that means it.
            $table->timestamp('released_at')->nullable();

            // `audit_log`'s actor vocabulary — `user:14`, `support:9`. Bounded
            // at 100 like `sending_halts.released_by` was, because an actor
            // string is an identifier and never prose.
            $table->string('released_by', 100)->nullable();

            // ⚠️ **A SECOND COLUMN RATHER THAN REUSING `note`, AND THE CHOICE IS
            // THE WHOLE POINT OF THIS MIGRATION ONE LEVEL DOWN.** `note` is what
            // an operator wrote when sending stopped; this is what somebody wrote
            // when they decided it was safe to start again. They are different
            // facts, written at different times, usually by different people —
            // and a single column would be *overwritten by the release*, which is
            // the exact defect being fixed here, in miniature.
            $table->string('release_note', self::NOTE_LIMIT)->nullable();

            // The history read: every pause this tenant has ever had. The unique
            // constraint dropped below was silently serving this lookup, and
            // PostgreSQL does not index a foreign key by itself, so removing it
            // without this would turn the incident list into a sequential scan.
            $table->index('business_id', 'sending_pauses_business_id_index');
        });

        // ⚠️ **THE OLD CONSTRAINT SAID "ONE ROW PER TENANT, EVER".** It has to go
        // before a second row can exist at all; everything below is what keeps
        // its actual guarantee.
        Schema::table('sending_pauses', function (Blueprint $table): void {
            $table->dropUnique(['business_id']);
        });

        // ⚠️ **AT MOST ONE *LIVE* PAUSE PER TENANT, ENFORCED BY THE DATABASE.**
        // The predicate is the same one `SendingGuard::isPaused()` asks, which is
        // what makes "the guard sees one row" and "the table holds one row" the
        // same statement rather than two that can drift.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX sending_pauses_one_live_per_tenant
                ON sending_pauses (business_id)
                WHERE released_at IS NULL
        SQL);

        // A release is attributed or it did not happen — `sending_halts_release_
        // is_attributed`'s reasoning, kept by 2119 along with the retained row.
        // ⚠️ **THE HALF-WRITTEN *RELEASE* IS THE DANGEROUS HALF**: a half-written
        // pause is visible within seconds because sending stops, while a release
        // that sets the timestamp without naming who set it switches sending back
        // on with nobody accountable for the decision.
        //
        // `release_note` is on the null side rather than the not-null side: a
        // note is optional, but a note on a pause that was never released is a
        // sentence about something that did not happen.
        DB::statement(<<<'SQL'
            ALTER TABLE sending_pauses
                ADD CONSTRAINT sending_pauses_release_is_attributed
                CHECK (
                    (released_at IS NULL AND released_by IS NULL AND release_note IS NULL)
                    OR (released_at IS NOT NULL AND released_by IS NOT NULL)
                )
        SQL);

        // The operator's pause-time note, bounded now that the row is permanent.
        // ⚠️ **A CHECK RATHER THAN `ALTER COLUMN TYPE varchar(500)`**, because
        // narrowing the type would either refuse the migration or silently
        // truncate stored text depending on the `USING` clause — and a schema
        // change that can destroy data on somebody else's database is the wrong
        // way to enforce a limit the writer already applies.
        $limit = self::NOTE_LIMIT;

        DB::statement(<<<SQL
            ALTER TABLE sending_pauses
                ADD CONSTRAINT sending_pauses_note_is_bounded
                CHECK (note IS NULL OR char_length(note) <= {$limit})
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sending_pauses DROP CONSTRAINT sending_pauses_note_is_bounded');
        DB::statement('ALTER TABLE sending_pauses DROP CONSTRAINT sending_pauses_release_is_attributed');
        DB::statement('DROP INDEX sending_pauses_one_live_per_tenant');

        // ⚠️ **THIS CAN FAIL, AND THE FAILURE IS THE ROLLBACK TELLING THE TRUTH.**
        // Restoring "one row per tenant, ever" is impossible once a tenant has
        // been paused twice, and PostgreSQL will say so rather than choosing a
        // row to keep. Rolling this back means deciding which incidents to
        // destroy, and that is a decision for a person.
        Schema::table('sending_pauses', function (Blueprint $table): void {
            $table->dropIndex('sending_pauses_business_id_index');
            $table->unique('business_id');
            $table->dropColumn(['released_at', 'released_by', 'release_note']);
        });
    }
};
