<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One change this platform made to somebody else's website — `DATA-MODEL.md`
 * §5.11, corrected by `BUILD-PLAN` §2.11.5 conflict 1 and decisions 5520–5527.
 *
 * ⛔ **RULE 32 IS THIS TABLE'S WHOLE REASON FOR EXISTING**: *"every site change
 * snapshots its prior state and is reversible."* `before_snapshot` is `NOT NULL`
 * at the database and non-empty at the writer, because a row that cannot say
 * what the page looked like before is a change nobody can undo — and the undo
 * is what makes writing to a stranger's site defensible at all.
 *
 * ⛔ **ONLY `App\Services\Actuation\SiteChanges` MAY WRITE HERE**, held by a
 * chokepoint lint in `tests/Feature/Architecture/ActuationTest.php` on
 * `VoiceUsageEvent`'s and `GbpGrantRevocationAttempt`'s precedent (625, 5071).
 * A second writer is a second place the snapshot guard can be skipped, and the
 * guard is the reversibility promise.
 *
 * ## What is deliberately NOT here, and why that is not an omission
 *
 * ⚠️ `DATA-MODEL.md`'s spec additionally lists `approval_id`, `baseline_metrics`,
 * `measured_metrics` and `measured_at`. **They are not created here because
 * nothing in this slice writes them** — `CLAUDE.md`'s most-repeated failure is a
 * column with no writer (272, sixteen instances), whose tell is that an
 * isolation test passes perfectly against something nothing fills in. The
 * approval reference lands with slice D, which is what asks for CONFIRM; the
 * three measurement columns land with slice H, which is what measures. Each
 * arrives in the migration that gives it a writer (5525).
 *
 * ⚠️ **`verdict` IS THE OPPOSITE CASE AND IS CREATED NOW.** Slice A writes two
 * of its cases — `pending` at open, `rolled_back` at revert — so the column has
 * a writer from the first day. The four it does not write are enum *cases*, not
 * columns, which is `AutopilotActionType`'s anticipated-vocabulary pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // ⚠️ **REQUIRED, AND THAT IS CORRECTION 1** (`BUILD-PLAN` §2.11.5).
            // The spec has no `location_id` at all, yet `CLAUDE.md` requires
            // every job be location-scoped and the website being changed is a
            // *location's* — `locations.website_url`, not the business's. A
            // business-scoped change set could not answer "which of my three
            // sites did you edit", and a multi-location tenant is the ordinary
            // case rather than the exotic one (3061).
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // The page, never the site. Long because a real URL with query and
            // fragment routinely exceeds 255 and truncating one silently points
            // a rollback at the wrong page.
            $table->string('url', 2048);

            // What kind of change this was — 'meta_description', 'json_ld',
            // 'internal_links'. Deliberately NOT an enum of any kind: the set
            // grows with every fix slice C–L adds, and a closed vocabulary here
            // would need a migration per fix.
            $table->string('change_type', 64);

            // ⚠️ **CORRECTION 2 — A STRING CAST TO `App\Enums\ActuationTier`,
            // never a database enum** (`CLAUDE.md`: "Never use a database `enum`
            // column"). `DATA-MODEL.md` gets this right here and wrong at
            // `speed_change_sets`, which declares `tier actuation_tier`; that
            // table is slice L's and the convention test would fail the build on
            // it as written (5526).
            $table->string('tier', 32);

            // ⛔ **BOTH `NOT NULL`, AND `before_snapshot` ADDITIONALLY REFUSED
            // WHEN EMPTY BY THE WRITER.** A NOT NULL column happily accepts
            // `{}`, which is a snapshot of nothing wearing a snapshot's name —
            // the database can hold the shape and only the service can hold the
            // meaning.
            $table->jsonb('before_snapshot');

            // The change set as it was written — known before it is applied,
            // which is what makes a change reviewable rather than merely
            // auditable after the fact.
            $table->jsonb('after_snapshot');

            // 'autopilot' | 'owner' | 'staff', cast to App\Enums\SiteChangeActor.
            $table->string('applied_by', 16);

            // ⚠️ **NULL UNTIL THE ADAPTER HAS ACTUALLY WRITTEN.** Not in the
            // spec, and the spec is wrong without it: `created_at` records when
            // the change set was *opened*, and a change set opened against an
            // adapter that then failed would otherwise be indistinguishable from
            // one that reached the site (5524).
            $table->timestamp('applied_at')->nullable();

            // 'pending' | 'improved' | 'neutral' | 'insufficient_data' |
            // 'regressed' | 'rolled_back', cast to App\Enums\SiteChangeVerdict.
            // ⚠️ **CORRECTION 3 IS `insufficient_data`** and it is a fact rather
            // than a nicety: reporting silence as a finding is a false statement
            // (229), and collapsing "we could not tell" into "neutral, keep it"
            // is exactly that.
            $table->string('verdict', 24)->default('pending');

            // ⚠️ **CORRECTION 4 — WHO UNDID IT AND WHY.** The spec has
            // `rolled_back_at` and nothing else, so an owner pressing Undo and
            // the platform auto-reverting a regression would be the same row.
            // They are not the same fact: one is a customer telling us we were
            // wrong, the other is us telling ourselves, and slice H quarantines
            // an automation on the second while slice J's screen must never
            // claim the first.
            $table->timestamp('rolled_back_at')->nullable();
            $table->string('rolled_back_by', 16)->nullable();
            $table->string('rolled_back_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['business_id', 'location_id']);
            $table->index(['business_id', 'verdict']);
        });

        // ⛔ **ALL THREE ROLLBACK FACTS OR NONE.** `subscriptions`' agreed-price
        // columns are the precedent (3533): a half-written revert is a row that
        // says it was undone and cannot say by whom, which is the one question
        // correction 4 exists to answer.
        DB::statement(<<<'SQL'
            ALTER TABLE site_changes
                ADD CONSTRAINT site_changes_rollback_is_all_or_nothing CHECK (
                    (rolled_back_at IS NULL AND rolled_back_by IS NULL AND rolled_back_reason IS NULL)
                    OR (rolled_back_at IS NOT NULL AND rolled_back_by IS NOT NULL AND rolled_back_reason IS NOT NULL)
                )
        SQL);

        DB::statement('ALTER TABLE site_changes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE site_changes FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON site_changes
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_changes');
    }
};
