<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The three numbers on the owner's home screen (`28` §3.3, `DATA-MODEL` §Trust
 * & early results, `29` §11.2 row 5).
 *
 * ⚠️ **A CACHE, AND ONLY EVER A CACHE.** Every row here is re-derivable from
 * `reviews` and `triage_conversations` by `ProofNumbers::recompute()`, and a
 * test drives the computation and compares it against a fresh count of the
 * source rows. That is not tidiness: §3.3's integrity rule is *"computed only
 * from auditable rows … no estimates, no modeled numbers, ever"*, and a rollup
 * that cannot be re-derived **is** an estimate — one with a timestamp on it,
 * which is worse, because the timestamp reads as provenance.
 *
 * ⚠️ **`google_reviews` IS STRUCTURALLY ZERO TODAY AND THAT IS CORRECT.**
 * Nothing writes `reviews.source = 'google'`; ingest is row 3 slice I and waits
 * on the GBP approval. §3.3 answers this directly — *"If a number would be zero,
 * show zero — the First 7-Day path exists to fix that, not the copy"* — so the
 * column ships, reads zero, and is never quietly backfilled from first-party
 * submissions, which would report a tenant's own feedback form as reviews on
 * their Google listing.
 *
 * ## Two deviations from `DATA-MODEL`, both deliberate
 *
 * **A surrogate `id` plus UNIQUE (business_id, period), not `PRIMARY KEY
 * (business_id, period)`.** Eloquent does not support composite primary keys —
 * `find()`, route binding and every relation assume one key column — so the
 * documented shape would mean a model this codebase's conventions cannot drive.
 * The uniqueness the composite key exists to guarantee is kept as a constraint,
 * which is the half that was load-bearing. Decision 863's precedent, where
 * `credit_kind` was written as a Postgres enum in `DATA-MODEL` and shipped as a
 * string because `CLAUDE.md` forbids database enums: follow neither document
 * silently, deviate and say so.
 *
 * **`computed_at` is its own column rather than `updated_at`**, because a row
 * can be re-saved without being recomputed, and the screen states when the
 * numbers were last worked out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proof_numbers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // 'YYYY-MM' or the literal 'all'. A string cast to nothing — there is
            // no PHP enum here because the set is unbounded (one value per month
            // forever), which is the one shape `CLAUDE.md`'s no-database-enum
            // rule would not have caught anyway.
            $table->string('period', 7);

            // ⚠️ Reviews first seen on the tenant's Google listing. Zero for
            // every tenant until row 3 slice I ingests them; see the docblock
            // above for why it is never filled from another source.
            $table->unsignedInteger('google_reviews')->default(0);

            // §3.3: "inbound calls answered or texted-back + forms captured +
            // bookings made". Calls are Stage 2 and bookings do not exist, so v1
            // is forms captured — which `29` §11.2 row 5 names exactly:
            // "Proof numbers v1 (reviews+forms)".
            $table->unsignedInteger('leads')->default(0);

            // ⚠️ NARROWER THAN §3.3's WORDS, AND THE SCREEN SAYS SO. §3.3 defines
            // this as triage that reached `resolved` *"with the customer
            // confirmed satisfied"*, and no column anywhere records the
            // customer's confirmation — `triage_conversations` has a status and a
            // free-text `resolution`. Counting `resolved` as "confirmed
            // satisfied" would be precisely the modeled number the same
            // paragraph forbids, so what is counted is `status = 'resolved'` and
            // the on-tap definition states that rather than §3.3's sentence.
            $table->unsignedInteger('recovered')->default(0);

            $table->timestampTz('computed_at');

            $table->timestamps();

            // The uniqueness `DATA-MODEL`'s composite primary key was for.
            $table->unique(['business_id', 'period']);
        });

        // A period is 'all' or exactly 'YYYY-MM'. At the database as well as in
        // the service, on 303–316's three-layer reasoning: the service gives a
        // caller an error they can act on, and this catches the repair script
        // that never reached the service. A malformed period is not cosmetic —
        // it silently creates a second bucket nothing ever reads, and the screen
        // keeps showing the old one.
        DB::statement(<<<'SQL'
            ALTER TABLE proof_numbers
                ADD CONSTRAINT proof_numbers_period_is_all_or_a_month
                CHECK (period = 'all' OR period ~ '^[0-9]{4}-(0[1-9]|1[0-2])$')
        SQL);

        DB::statement('ALTER TABLE proof_numbers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE proof_numbers FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON proof_numbers
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('proof_numbers');
    }
};
