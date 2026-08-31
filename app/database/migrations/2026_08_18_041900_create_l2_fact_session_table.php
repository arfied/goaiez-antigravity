<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L2 — `GOAIEZ_PIXEL_MASTER_BUILD` §5.4's `fact_session`, and §8's
 * sessionization aggregated to the session grain.
 *
 * Decision 4864 named this as one of the four owed marts and decision 4879
 * item 5 carries the debt forward; this pays the sessionization half of it.
 * The same reproducibility rules as `l1_events` and `l2_fact_source_daily`
 * apply and are enforced by the same lint: no serial, no clock default, no
 * `double precision`, no `jsonb`.
 *
 * ⚠️ **`session_id` IS THE CLIENT'S, NOT THIS TABLE'S.** §8's boundary rule
 * — 30 minutes of inactivity, UTC midnight, or a new non-empty campaign —
 * is already implemented exactly in `resources/js/pixel.js`'s
 * `currentSession()` and minted into every event before it ever reaches this
 * table. What this migration's derivation does is aggregate the events that
 * already share one `session_id` into a single row; it does not re-derive
 * the boundary, because the boundary is a fact about when the *browser*
 * decided a session ended and there is no server-side signal that could
 * recompute it more truthfully.
 *
 * ⚠️ **`day` IS BUCKETED ON `received_at`, LIKE `l2_fact_source_daily`'S OWN
 * COLUMN, NOT ON `started_at`.** A session cannot legitimately span a UTC
 * midnight (§8 ends it there), so the two almost always agree — but keeping
 * the same column the rest of the warehouse buckets on is what keeps this
 * table deletable and rebuildable by the same day-range `Replayer` already
 * uses for every other mart, without a second range concept.
 *
 * ⛔ **THAT SENTENCE'S PREMISE IS FALSE IN BOTH CLOCKS, AND IT IS THE SENTENCE A
 * READER IS HANDED AS THE JUSTIFICATION FOR THIS COLUMN — CORRECTED 2026-08-22
 * (7920–7935). BOTH READINGS KEPT** (4368). *"A session cannot legitimately span
 * a UTC midnight"* is not true of **`received_at`**, because §8's boundary is
 * the browser's and ours is the network's — a beacon fired at 23:59:58 and
 * received at 00:00:01 straddles whatever the browser decided (7843). ⛔ **And it
 * is not true of `occurred_at` either**, which is the correction: `pixel.js`'s
 * `currentSession()` has exactly **one call site** and is evaluated once at
 * script init, so a page open from 23:55 to 00:10 emits post-midnight
 * `occurred_at` under a pre-midnight `session_id`. **So a session spans UTC
 * midnight on every clock available**, and this table's own primary key
 * `(business_id, day, session_id)` is what makes that legitimately **two rows**.
 * ⚠️ **The cost of the false premise was measured rather than argued**:
 * `App\Services\Warehouse\Replayer::rebuildFactConversion()` joined this table
 * on `session_id` alone, which cannot fan out if one session is one row — and
 * fanned out to a `SQLSTATE[23505]` that aborted a whole replay the first time
 * a day was re-replayed after its successor (7920).
 *
 * ⛔ **§5.4's OWN `fact_session` HAS NO DATE COLUMN AT ALL** — it is
 * `ORDER BY (tenant_id, started_at, session_id)` on a `ReplacingMergeTree`, one
 * row per session. **`day` is this repository's addition**, and whether it
 * belongs on the session grain at all is part of the open ruling (7930–7934).
 * Nothing here is changed on that account; it is written down so the next reader
 * does not take the column for the specification's.
 *
 * ⛔ **THE RULING CAME ON 2026-08-22 AND IT WENT THE OTHER WAY: THIS TABLE NO
 * LONGER HAS A `day` COLUMN, AND EVERY PARAGRAPH ABOVE ABOUT ONE DESCRIBES A
 * SHAPE THAT NO LONGER EXISTS** (8040, 8041; 8100–8119). The key is
 * `(business_id, session_id)`, the column is replaced by `first_received_at`,
 * and the sentence above — *"this table's own primary key
 * `(business_id, day, session_id)` is what makes that legitimately two rows"* —
 * is now the description of a defect rather than of this schema. **Both readings
 * are kept and dated** (4368), because the argument that produced the column is
 * what a reader needs in order to understand why it went.
 * `2026_08_22_210436_key_l2_fact_session_by_tenant_and_session.php` carries the
 * whole of it, including why `first_received_at` is not `day` under a new name.
 *
 * ⚠️ **REDUCED FROM §5.4'S OWN COLUMN LIST, ON L1DERIVATION'S OWN PRECEDENT**
 * (decision 4865: *"the L1 derivation fills the subset the payload can
 * answer, and the absent columns are absent from the table too"*). Not
 * carried: `identity_id` (§13 is unbuilt), `country`/`city` (no geo
 * enrichment exists, 4865 again), `max_scroll_pct` (would need a second JSON
 * extraction beside `active_s` for a column no report yet reads). A nullable
 * column nothing writes is 272's shape; these arrive with the component that
 * can fill them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('l2_fact_session', function (Blueprint $table): void {
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            $table->date('day');
            $table->uuid('session_id');

            $table->uuid('anonymous_id')->nullable();

            $table->timestamp('started_at', 3);
            $table->timestamp('ended_at', 3);

            $table->unsignedInteger('duration_s');
            $table->unsignedInteger('active_s');
            $table->unsignedSmallInteger('pageviews');
            $table->unsignedSmallInteger('conversions');

            $table->boolean('is_engaged');
            $table->boolean('is_bot');
            $table->boolean('is_new');

            $table->string('entry_page_path', 512);
            $table->string('exit_page_path', 512);

            // §5.4's `dim_source.source_key`, without the dimension table:
            // `utm_source` if present, else a referral marker, else
            // '(direct)'. Never null — the same '(none)' rule
            // `l2_fact_source_daily` applies, on a single-column key.
            $table->string('source_key', 255);

            $table->string('device_type', 32);
            $table->string('consent_state', 16);

            $table->primary(['business_id', 'day', 'session_id'], 'l2_fact_session_pkey');
        });

        DB::statement('ALTER TABLE l2_fact_session ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE l2_fact_session FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON l2_fact_session
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('l2_fact_session');
    }
};
