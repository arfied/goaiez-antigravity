<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §5.7's dead-letter queue — decision 5000s,
 * closing what 4879 item 2 and 4974 both carried forward.
 *
 * §5.7's own words: *"Never silently drop a validation failure. Rejects are
 * queryable, countable, and alertable — a spike in rejects means a pixel bug
 * and you want to know that day."*
 *
 * ---------------------------------------------------------------------------
 * ⛔ AN HOURLY ROLLUP, NOT A ROW PER REJECT — AND THAT IS THE WHOLE DESIGN
 * ---------------------------------------------------------------------------
 * §5.7's own DDL is a row per rejected request carrying a `raw_payload`. ⛔ **A
 * ROW PER REJECT IS UNBOUNDED IN EXACTLY THE CASE THIS TABLE EXISTS TO
 * OBSERVE.** The failure it is built for is a tenant who installed the snippet
 * on a website they never listed — decision 4968's fail-closed consequence,
 * *"the most likely reason a real tenant will report the pixel is not
 * working"*. That tenant's site emits one rejected batch **per pageview**, from
 * one origin, for as long as nobody notices. A busy site is tens of thousands
 * of rows a day, indefinitely, and **nothing in this application prunes this
 * table** — W19's `storage.retention_days` covers object-storage kinds and
 * reaches no Postgres row.
 *
 * ⛔ **THAT LAST CLAUSE STOPPED BEING TRUE ON 2026-08-22 AND IS CORRECTED HERE
 * RATHER THAN LEFT STANDING — BOTH READINGS KEPT AND DATED** (7620–7639).
 * `App\Services\Pixel\IngestRejects::prune()` and `pixel:prune-rejects` give
 * this table a **90-day** horizon, swept nightly at 03:55. ⚠️ **The argument for
 * the rollup is untouched and is the reason the horizon is not the whole
 * answer**: the rollup bounds the accidental case *absolutely*, at 24 rows a
 * day, where a horizon only bounds it eventually — and the paragraph below is
 * still the honest account of what neither of them bounds within the window.
 * ⚠️ **`storage.retention_days` still reaches no Postgres row**; this is a
 * constant on the service, for `OperatorAlerts::RETENTION_DAYS`' reasons.
 *
 * So the grain is `(business_id, reason, origin, hour)` with a count, and that
 * same mis-installed tenant produces **24 rows a day** instead of 40,000 while
 * `rejects` carries the number that actually answers *"is this a spike?"*.
 * ⚠️ **HOUR GRANULARITY IS ENOUGH ON §5.7's OWN TERMS** — *"you want to know
 * that day"* — and `created_at`/`updated_at` bound first and last sighting
 * inside each bucket for nothing extra.
 *
 * ⚠️ **WHAT THE ROLLUP DOES NOT BOUND, SAID OUT LOUD:** `origin` is a header
 * the client writes, so a caller varying it per request still writes a row per
 * request. That is bounded by `PixelRateLimits` per source and by nothing else
 * here, and it is the adversarial case rather than the one §5.7 is about — the
 * accidental case, which is the common one by a wide margin, is now bounded
 * absolutely.
 *
 * ⚠️ **AND IT IS THAT PARAGRAPH, NOT THE ONE ABOVE, THAT THE 2026-08-22 HORIZON
 * ANSWERS.** `PixelRateLimits::BEACONS_PER_MINUTE` is 300, so one source holding
 * a tenant's public key writes up to **432,000 rows a day**, each carrying up to
 * 512 bytes it chose. The rate limit bounds the *rate*; nothing bounded the
 * *total*, and permanence is the half a horizon removes.
 *
 * ---------------------------------------------------------------------------
 * ⛔ NARROWER THAN §5.7's OWN VOCABULARY, AND DELIBERATELY SO
 * ---------------------------------------------------------------------------
 * The specification's `reason` vocabulary is `schema|unknown_key|origin|cap|
 * malformed`, one shared dead-letter table for every refusal. This table
 * carries only `origin_not_allowed` today, for three reasons argued in full on
 * `App\Enums\PixelRefusal`'s class docblock and repeated here in short:
 *
 *  1. `App\Enums\PixelRefusal` already gives every refusal a named reason and
 *     a structured log line — "a count, not a record" — so a queryable row is
 *     additive for the one shape worth diagnosing individually, not the only
 *     observability the pipeline has.
 *  2. **A mismatched origin is the shape most likely to mean a real,
 *     mis-installed tenant** rather than an anonymous stranger probing a
 *     public endpoint. `unknown_key`, `malformed`/`schema` and rate-limit
 *     refusals are ordinary internet noise on an anonymous endpoint at any
 *     real volume — and two of them are refused *before* a tenant is known at
 *     all, so there is no `business_id` to file them under and no row-level
 *     security policy that could admit them.
 *  3. **The monthly cap (row 4) is deliberately NOT here.** A tenant at its
 *     500,000-event cap could drop thousands of events an hour;
 *     `pixel_monthly_usage.events_dropped` is that counter, and it is a count
 *     for exactly the reason `App\Services\Pixel\MonthlyEventCap`'s docblock
 *     gives.
 *
 * ⚠️ **NO `raw_payload` COLUMN**, unlike §5.7's own DDL. `CLAUDE.md`'s
 * ambiguity rule 2 — *less stored PII* — settles it: the field that actually
 * answers "why did this reject happen" is the mismatched `origin` itself, not
 * the batch's device signals and UTMs, and archiving a copy of every dropped
 * request's payload would be a second visitor-behaviour record beside the log
 * line `PixelCollector::refuse()` already writes. A rollup could not carry one
 * honestly in any case: there is no single payload behind a bucket of 40,000.
 *
 * ⚠️ **TENANT-OWNED, ON `pixel_monthly_usage`'s PRECEDENT.** The origin check
 * runs after `Tenancy::set()`, so every row genuinely belongs to one business
 * — unlike `voice_usage_events`, where the rows that matter most carry none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingest_rejects', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // A string cast to App\Enums\PixelRefusal, never a database enum —
            // the standing rule. Sized for the enum's longest current value
            // with room to grow rather than trimmed to today's.
            $table->string('reason', 32);

            // The Origin header that was rejected. A hostname a browser sent,
            // not personal data about the visitor behind it — the one field
            // that actually explains the reject to whoever reads this table.
            //
            // ⚠️ NULL IS A REAL AND DISTINCT ANSWER: the request carried no
            // `Origin` header at all, which `WidgetPlugins::businessAllowsOrigin()`
            // refuses and which means the caller was not a browser on a page.
            // That is worth telling apart from a named-but-unlisted site, so it
            // is a NULL rather than an empty-string sentinel — see the unique
            // index below for what that costs.
            $table->string('origin', 512)->nullable();

            // The UTC hour this bucket counts, truncated to the hour by the
            // writer. Not the timestamp of one event — see the class docblock.
            $table->timestamp('hour');

            $table->unsignedBigInteger('rejects')->default(0);

            // `created_at` is first sighting inside the bucket and `updated_at`
            // is last — two useful facts for free, rather than a pair of
            // hand-rolled `first_seen_at`/`last_seen_at` columns.
            $table->timestamps();

            $table->index(['business_id', 'hour']);
        });

        // ⚠️ **`NULLS NOT DISTINCT` IS LOAD-BEARING AND IS WHY THIS IS RAW DDL
        // RATHER THAN `$table->unique(...)`.** Postgres treats NULLs as
        // distinct in a unique index by default, so without this clause every
        // reject from a caller sending no `Origin` header — the non-browser
        // case, and the easiest one for a script to produce — would insert a
        // new row instead of incrementing a bucket, and the unbounded growth
        // this table's whole shape exists to prevent would survive in the one
        // arm nobody would think to test. Postgres 15+; this project is on 16.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX ingest_rejects_bucket_unique
                ON ingest_rejects (business_id, reason, origin, hour)
                NULLS NOT DISTINCT
        SQL);

        DB::statement('ALTER TABLE ingest_rejects ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ingest_rejects FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON ingest_rejects
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ingest_rejects');
    }
};
