<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One speed fix, on one site, with the evidence it was judged on — `28` §4.3:
 * *"each fix = one change set … fixes deploy one at a time per site with ≥48h
 * between, so regressions are attributable."*
 *
 * ⛔ **THE SPEC'S SHAPE IS NOT THE BUILT SHAPE — SIX CORRECTIONS, WRITTEN BACK
 * INTO `DATA-MODEL.md` ON SLICE A's PRECEDENT** (5526, `BUILD-PLAN` §2.11.5
 * conflict 1). The superseded spec read:
 *
 *   speed_change_sets (id, business_id, location_id, fix_key, tier actuation_tier,
 *                      change_set_id, baseline JSONB, result JSONB,
 *                      status, -- applied|measuring|kept|rolled_back|quarantined
 *                      applied_at, decided_at)
 *
 * ⚠️ **(1) AND (2) ARE THE TWO DB ENUMS `ConventionsTest` FAILS THE BUILD ON.**
 * `tier actuation_tier` is a Postgres enum; so is the `status` its comment
 * enumerates. Both are `string` columns cast to PHP backed enums —
 * `App\Enums\ActuationTier` and `App\Enums\SpeedFixStatus` — exactly as
 * `site_changes.tier` already is. `fix_key` is the same correction a third time,
 * pre-empted: it is a closed set of seven and it is still a string, cast to
 * `App\Enums\SpeedFix`.
 *
 * ⚠️ **(3) `decided` IS A STATUS THE SPEC HAS NO WORD FOR, AND WITHOUT IT EVERY
 * ROW WOULD BEGIN LIFE LYING.** The row is written when the fix is chosen and
 * the change set is opened — before anything has reached the website — because
 * that is `SiteChanges`' own two-step rule (open, then apply) and its own
 * reason: an attempt that failed midway is exactly the one an owner needs a
 * record of. A row inserted as `applied` before the adapter answered would
 * report an edit that never happened.
 *
 * ⛔ **(4) `applied` IS DROPPED, BECAUSE IT AND `measuring` NAME ONE STATE.**
 * There is no instant at which a fix is applied and not being measured. Two
 * words for one state is two things a reader has to match on, and the query
 * every reader would end up writing is `status IN ('applied','measuring')`.
 * `measuring` survives because it says what is happening; the fact that the
 * write landed is `applied_at`, which is a timestamp and cannot drift from
 * itself.
 *
 * ⛔ **(5) `quarantined` IS DROPPED, BECAUSE THE QUARANTINE IS NOT A FACT ABOUT
 * THIS ROW.** It is a fact about the pair `(location, change_type)` and it
 * lives on `site_change_quarantines`, which slice H built with a partial unique
 * index making *live* a state rather than a pile of rows (5812). A second copy
 * here would be a second answer to *"is this fix resting?"*, and the two would
 * disagree the first time an operator released one — the release writes three
 * columns on that table and nothing here. What this column says instead is what
 * happened to **this application of the fix**: `rolled_back` when the page was
 * put back, `revert_failed` when the site would not take it and our own change
 * is still on it.
 *
 * ⛔ **(6) `insufficient_data` IS A TERMINAL STATUS AND MAY NEVER COLLAPSE INTO
 * `kept`** (5523). The window is anchored on `applied_at`, so a window that was
 * too thin when it closed is too thin for ever — leaving such a row as
 * `measuring` would make it look stuck, and calling it `kept` would report
 * silence as a finding. 4861 makes this the **expected** outcome for most fixes
 * for some time.
 *
 * ## The unique index is the *"one at a time per site"* rule, made structural
 *
 * ⛔ **A SERVICE CHECK ALONE WOULD BE A RULE TO REMEMBER.** `28` §4.3's whole
 * attribution argument — *"so regressions are attributable"* — collapses the
 * moment two fixes are in flight on one site, because the 7-day measured window
 * of the first would contain the second and the decider would attribute the
 * first fix's regression to the wrong change. The partial unique index refuses
 * the second insert.
 *
 * ⚠️ **ITS PREDICATE IS `applied_at IS NOT NULL AND result IS NULL`, AND THE
 * FIRST HALF IS WHAT STOPS IT WEDGING A SITE FOR EVER.** A fix whose adapter
 * write failed keeps `applied_at` null: it never reached the website, so it is
 * not in flight and must not block the next attempt. Predicating on `result IS
 * NULL` alone would leave one failed write holding a customer's site closed to
 * the speed layer permanently, with nothing on any screen saying why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speed_change_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Location-scoped: the website is a location's (`locations.website_url`)
            // and `CLAUDE.md` requires every job be location-scoped. §4.3's own
            // rule is "one at a time per **site**".
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // 'image_dimensions' | 'lazy_load_images' | … — `28` §4.1's seven
            // rows, cast to App\Enums\SpeedFix. A string, not a Postgres enum,
            // and not because the set is open: it is closed at seven. It is a
            // string because a DB enum is a second source of truth that drifts
            // from the PHP one, and its values can be neither dropped nor
            // reordered once added.
            $table->string('fix_key', 48);

            // 't1_plugin' | 't3_pixel' | … cast to App\Enums\ActuationTier, as
            // `site_changes.tier` already is. Correction (1).
            $table->string('tier', 16);

            // ⛔ **THE `site_changes` ROW IS REQUIRED AND UNIQUE.** A speed fix
            // that is not a change set is a change to somebody's website with no
            // snapshot and no undo — `29` §2 rule 32 — and this table is
            // deliberately incapable of describing one. Unique because a second
            // speed row against one change set would be two verdicts about one
            // edit.
            $table->foreignId('change_set_id')->unique()->constrained('site_changes')->cascadeOnDelete();

            // ⚠️ **NULLABLE, AND A WITHHELD FIGURE IS `null` RATHER THAN `{}`**
            // (5804). A fix that has not been judged has no result; an empty
            // document would say *"we measured and found nothing"* about a
            // measurement that has not happened.
            $table->jsonb('baseline')->nullable();
            $table->jsonb('result')->nullable();

            // 'decided' | 'measuring' | 'kept' | 'rolled_back' | 'revert_failed'
            // | 'insufficient_data', cast to App\Enums\SpeedFixStatus.
            // Corrections (2)–(6). ⚠️ **NO CHECK NAMES THESE VALUES**: a CHECK
            // enumerating them would be the second source of truth the string
            // column exists to avoid, which is `indexing_submissions`' own
            // recorded reasoning.
            $table->string('status', 24);

            // When the fix was chosen and the change set opened.
            $table->timestamp('decided_at');

            // When the adapter reported writing it. Null until then, and null
            // for ever on a write that failed.
            $table->timestamp('applied_at')->nullable();

            $table->timestamps();

            $table->index(['business_id', 'location_id']);
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX speed_change_sets_one_in_flight_per_location
                ON speed_change_sets (business_id, location_id)
                WHERE applied_at IS NOT NULL AND result IS NULL
        SQL);

        // A fix that never reached the site cannot have been judged. This names
        // no status value, so it constrains the shape without becoming a second
        // vocabulary.
        DB::statement(<<<'SQL'
            ALTER TABLE speed_change_sets
                ADD CONSTRAINT speed_change_sets_result_needs_an_application CHECK (
                    result IS NULL OR applied_at IS NOT NULL
                )
        SQL);

        DB::statement('ALTER TABLE speed_change_sets ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE speed_change_sets FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON speed_change_sets
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('speed_change_sets');
    }
};
