<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The First 7-Day Results Path's state machine (`28` §3.2, `DATA-MODEL` §Trust
 * & early results, `29` §11.2 row 5).
 *
 * ⚠️ **ONE ROW PER TENANT, EVER — `business_id UNIQUE`, EXACTLY AS `DATA-MODEL`
 * SPECIFIES.** There is one first week, and a wizard completed twice (a repair
 * script, a resumed session racing itself) cannot restart a clock that already
 * ran.
 *
 * ⚠️ **`FirstWeekPath::begin()` IS NOT `firstOrCreate()`, AND THIS DOCBLOCK SAID
 * IT WAS** (1955) — while that method's own docblock explains at length why it
 * deliberately is not: `FirstWeekRun::$guarded` refuses every column, so
 * `firstOrCreate()`'s create path would fill nothing. It is a read followed by a
 * `forceFill()` on a miss, `ProofNumbers::recompute()`'s pattern. The difference
 * that matters here is that a read-then-write is **not atomic**: two concurrent
 * `/setup/done` requests can both miss, and the second `INSERT` is refused by
 * this constraint with a 500 rather than returning the existing row. That is the
 * correct failure and it heals — under 1890's ordering `begin()` runs *before*
 * `SetupFlow::complete()`, so the losing request leaves the wizard incomplete
 * and its retry finds the row the winner wrote.
 *
 * ⚠️ **`step` AND `win_type` ARE CONSTRAINED AT THE DATABASE TOO, ON 303–316'S
 * THREE-LAYER REASONING** — `App\Enums\FirstWeekWinType` is the PHP source of
 * truth (`CLAUDE.md` forbids a database enum), and the CHECK constraints here
 * are the same belt-and-braces `proof_numbers.period` carries: a service-layer
 * bug or a hand-run repair script that writes a value outside either set is
 * refused at the one layer that cannot be bypassed by forgetting to call the
 * service.
 *
 * `step` is which of the path's ordered days has been *fully processed* — never
 * decremented, never skipped backwards. See the service's own docblock for why
 * the doc's seven-row table collapses to four processed days here: two of the
 * seven rows (the Day-1 "invites out" observation and the Day-3 alt win) have
 * no independent action of their own once first-Google-review detection is
 * decoupled from the day-by-day loop and run on every tick instead.
 *
 * ⚠️ **`DATA-MODEL`'s `state JSONB` IS DELIBERATELY NOT CREATED (1886), AND ITS
 * ABSENCE IS PINNED BY A TEST** so it cannot return in a later migration written
 * by somebody reading `DATA-MODEL` rather than this — `autopilot_settings.
 * google_invite_threshold`'s precedent (304), one table over. It shipped in the
 * first version of this migration with **no writer and no reader**: `begin()`
 * left it `[]`, nothing ever touched it again, and the model carried a cast and
 * a `@property` for it. That is `CLAUDE.md`'s first recurring failure shape
 * verbatim — a column whose isolation test passes perfectly against something
 * nothing writes — and the fifteen instances that list already counts are the
 * reason it is cheaper to drop it than to explain it. It comes back with its
 * writer, in the slice that has one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('first_week_runs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('step')->default(0);

            $table->string('win_type')->nullable();

            $table->timestampTz('win_at')->nullable();

            $table->timestampTz('started_at');

            $table->timestampTz('completed_at')->nullable();
        });

        // `App\Services\Trust\FirstWeekPath::LAST_STEP` — four processed days,
        // indices 0 through 4 (4 means "past the last one", set alongside
        // completed_at). A value outside that range cannot come from the
        // service; it can only come from something that bypassed it.
        DB::statement(<<<'SQL'
            ALTER TABLE first_week_runs
                ADD CONSTRAINT first_week_runs_step_in_range
                CHECK (step BETWEEN 0 AND 4)
        SQL);

        // App\Enums\FirstWeekWinType's two cases, restated. `win_type IS NULL`
        // is the ordinary "no win yet" state, not a violation.
        DB::statement(<<<'SQL'
            ALTER TABLE first_week_runs
                ADD CONSTRAINT first_week_runs_win_type_known
                CHECK (win_type IS NULL OR win_type IN ('google_review', 'fallback_proof'))
        SQL);

        // win_at is set if and only if win_type is — the pair is written
        // together everywhere in the service, and the constraint says so rather
        // than leaving it to be trusted.
        DB::statement(<<<'SQL'
            ALTER TABLE first_week_runs
                ADD CONSTRAINT first_week_runs_win_pair
                CHECK ((win_type IS NULL) = (win_at IS NULL))
        SQL);

        DB::statement('ALTER TABLE first_week_runs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE first_week_runs FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON first_week_runs
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('first_week_runs');
    }
};
