<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The widget feed's half of `plugins` (`17` FPR-05, row 3 slice G).
 *
 * TWO CHANGES, AND THE FIRST IS THE ONE THAT MATTERS.
 *
 * ---------------------------------------------------------------------------
 * 1. `tenant_isolation` BECOMES `public_read` + `tenant_write`
 * ---------------------------------------------------------------------------
 * `GET /api/widget/{embed_key}/reviews` is served to an anonymous browser on
 * somebody else's website. It presents an opaque key and nothing else — no
 * session, no token — and the tenant is *derived from that key*. So the lookup
 * has to succeed before any tenant exists, and the single `tenant_isolation`
 * policy this table was created with makes that impossible: with
 * `app.business_id` unset the predicate is NULL, the row is invisible, and the
 * feed 404s every request forever.
 *
 * This is decision 318's problem exactly, one table over, and it takes 318's
 * answer rather than an exemption. `public_read` grants the unauthenticated
 * SELECT; `tenant_write` keeps every INSERT, UPDATE and DELETE predicated on
 * the acting tenant. Postgres OR's permissive policies together, so SELECT is
 * satisfied by `public_read` and the write commands are reached only through
 * `tenant_write`.
 *
 * ⚠️ **`public_read` IS NOT "THIS TABLE IS PUBLIC".** It means a row is
 * fetchable *if you already hold its `embed_key`*, and that key is a random
 * UUID precisely so holding one is not a capability you can guess your way
 * into. What keeps it that way is that nothing in this application lists
 * `plugins` on a public path — `WidgetPlugins::resolve()` takes one key and
 * returns one row. The same property `feedback_pages` relies on for its slug.
 *
 * ---------------------------------------------------------------------------
 * 2. `min_stars_to_show`, WITH A CHECK
 * ---------------------------------------------------------------------------
 * ⚠️ **A REAL COLUMN RATHER THAN A `config` KEY, DEVIATING FROM DATA-MODEL
 * §5.11**, which files every widget setting under `config JSONB`. In the class
 * of decisions 286 and 304, and for one reason: this is the setting with a
 * *legal* dimension, and a JSONB key cannot carry a CHECK. The FTC's 2024 Rule
 * on Consumer Reviews and Testimonials addresses suppressing negative reviews,
 * and this column is the dial that does the suppressing — `min_stars_to_show`
 * of 4 means an owner's own website shows nothing below four stars.
 *
 * **DEFAULT 1 — SHOW EVERYTHING — IS THE OWNER'S RULING**, and it is decision
 * 110/114's shape rather than a guess: make the safe state the default so the
 * risky one is a deliberate act somebody has to take. Raising it is a settings
 * row, not a deploy.
 *
 * `layout` and `theme` are NOT built, though FPR-05 names them beside this one.
 * Their only consumer is the embeddable bundle, which `BUILD-PLAN` §2.6.4
 * conflict 3 defers — and columns nothing writes are what decision 311 removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plugins', function (Blueprint $table): void {
            // Nullable first, then backfilled and made NOT NULL, so an existing
            // row cannot end up holding NULL on a column every read treats as a
            // number.
            $table->unsignedTinyInteger('min_stars_to_show')->nullable()->after('allowed_domains');
        });

        // No tenant predicate, and none would work: this runs as the table owner
        // with `app.business_id` unset, and `plugins` is FORCE'd — so a
        // tenant-scoped UPDATE here would match zero rows and silently do
        // nothing. Decision 319's lesson, applied before it could bite.
        DB::table('plugins')->whereNull('min_stars_to_show')->update(['min_stars_to_show' => 1]);

        DB::statement('ALTER TABLE plugins ALTER COLUMN min_stars_to_show SET DEFAULT 1');
        DB::statement('ALTER TABLE plugins ALTER COLUMN min_stars_to_show SET NOT NULL');

        // 1–5, not `invite_threshold`'s 0–5. Zero is not "show everything" here
        // — it is a rating no review can hold, so it would read as a disabled
        // feed to anybody scanning the column while behaving identically to 1.
        // Slice E's decision 383 is the precedent for refusing to reuse a
        // neighbouring column's range just because it is adjacent.
        DB::statement(<<<'SQL'
            ALTER TABLE plugins
                ADD CONSTRAINT plugins_min_stars_to_show_in_range
                CHECK (min_stars_to_show BETWEEN 1 AND 5)
        SQL);

        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON plugins');

        DB::statement(<<<'SQL'
            CREATE POLICY public_read ON plugins
                FOR SELECT USING (true)
        SQL);

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_write ON plugins
                FOR ALL
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS public_read ON plugins');
        DB::statement('DROP POLICY IF EXISTS tenant_write ON plugins');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON plugins
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);

        DB::statement('ALTER TABLE plugins DROP CONSTRAINT IF EXISTS plugins_min_stars_to_show_in_range');

        Schema::table('plugins', function (Blueprint $table): void {
            $table->dropColumn('min_stars_to_show');
        });
    }
};
