<?php

declare(strict_types=1);

use App\Enums\DataClassification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L1 — conformed events, derived from L0 and disposable.
 *
 * `GOAIEZ_PIXEL_MASTER_BUILD` §5.3, collapsed from ClickHouse into Postgres by
 * decision 89 and CLAUDE.md §"Pixel / warehouse".
 *
 * ⛔ **THIS MIGRATION IS WRITTEN FOR REPRODUCIBILITY BEFORE IT IS WRITTEN FOR
 * ANYTHING ELSE, AND DECISION 4580 IS WHY:** *"'byte-identical' is a constraint
 * on the L1/L2 DDL, not a property of the replay job — a derived row carrying
 * `now()`, a serial id, or an unordered aggregate is not reproducible, so the
 * first migration in that chain has to be written for it or the assertion can
 * never be made afterwards."* Every rule below follows from that, and
 * `WarehouseTest`'s reproducible-DDL lint fails the build on any of them:
 *
 *  1. **No serial and no identity column.** A `bigIncrements` id is allocated by
 *     a sequence, and a truncate-and-rebuild restarts it at a different place
 *     the moment one row was ever deleted or one insert ever rolled back. The
 *     primary key comes from L0 rather than from this table.
 *     ⛔ **THIS RULE SAID "THE PRIMARY KEY IS `event_id`" AND IT IS NOW
 *     `(business_id, event_id)` — BOTH READINGS KEPT AND DATED, 2026-08-20
 *     (6182, 6240).** `event_id` alone was a **global** key on a table every
 *     other warehouse layer keys per tenant, and it is minted in the *browser*
 *     — so the second tenant to present an id already held by the first lost
 *     the event to `ON CONFLICT DO NOTHING`, silently, because a unique index
 *     is not filtered by row-level security. See
 *     `2026_08_21_035151_key_l1_events_by_tenant_and_event.php`. ⚠️ **The rule
 *     this clause belongs to is unchanged**: the key is still not generated
 *     here, still carries no sequence, and is still reproducible from L0 —
 *     `business_id` is as much a function of the L0 object as `event_id` is.
 *  2. **No `created_at` / `updated_at`, and no default that reads a clock.**
 *     Laravel's `timestamps()` would put `now()` on every derived row, which is
 *     the single most obvious way this gate dies. §5.3's own `ingested_at` is
 *     the same hazard wearing the specification's name, and decision 4862 rules
 *     that it is receipt metadata read back from L0 rather than a derivation-
 *     time stamp — so it is `received_at` here and there is no second column.
 *  3. **No `double precision` and no `real`.** Floating-point addition is not
 *     associative, so an aggregate over the same rows in a different order sums
 *     to different bits; and PHP renders a float through `serialize_precision`,
 *     an ini setting. Exact `numeric` or an integer in the smallest unit.
 *  4. **Millisecond time, fixed at the archive boundary.** `timestamp(3)`
 *     matches §5.3's `DateTime64(3)` and matches what `L0Line` wrote, so the
 *     column cannot introduce a precision the archive does not have.
 *     ⛔ **`timestamp`, NOT `timestamptz`, AND `TimeTest` FAILS THE BUILD ON THE
 *     OTHER ONE** — this lane wrote `timestampTz` first and the lint caught it.
 *     It is the house convention (278 columns to nine), and it turns out to be
 *     the *stronger* choice for this gate rather than merely the consistent one:
 *     a `timestamp` carries no zone, so rendering it reads no session setting,
 *     where `timestamptz::text` and `to_char(timestamptz, …)` both go through
 *     the connection's `TimeZone`. ⚠️ **Which means `AT TIME ZONE 'UTC'` must
 *     NOT be applied to these columns** — on a plain `timestamp` that expression
 *     *converts to* `timestamptz` and **introduces** the very dependency it
 *     looks like it removes. `WarehouseSnapshot` and the L2 rollup say so where
 *     they read them.
 *  5. **`properties` is `text`, not `jsonb`.** `jsonb` normalises — it reorders
 *     keys, collapses whitespace and drops duplicates — so the bytes stored stop
 *     being the bytes derived, and L0→L1 fidelity is lost even though a rebuild
 *     would be self-consistent. The CHECK below keeps it valid JSON without
 *     letting Postgres rewrite it.
 *
 * ⛔ **AND `data_class` CANNOT BE `phi`, AT THE DATABASE** (decision 4863).
 * `29` §12.1 requires *"PHI never appears in L3"*; rule 24 requires a separate
 * schema, role and KMS key, and CLAUDE.md puts all three behind Stage 3. Until
 * they exist the warehouse refuses PHI outright rather than carrying it under
 * the ordinary key — `ObjectStoreL0Archive` refuses it at L0 and this CHECK is
 * the second layer, on the same reasoning tenancy uses for RLS: the
 * application-layer refusal is the one that is correct, and this is the one that
 * saves you on the day it is forgotten.
 *
 * ⚠️ **"THE WAREHOUSE REFUSES PHI OUTRIGHT" CLAIMED MORE THAN THIS CONSTRAINT
 * CAN DELIVER, AND THE GAP IS NAMED HERE RATHER THAN LEFT TO BE FOUND —
 * 2026-08-18 (5081).** What this CHECK refuses is the *string* `phi` in this
 * column. It says nothing about whether the **business** is a covered entity,
 * and those two came apart the moment a classification could move: a tenant that
 * ran the pixel as `pii` and was raised to `phi` by `Admin\PhiTenants` kept every
 * row it had accumulated — `data_class = 'pii'`, this constraint satisfied, and
 * the business a covered entity throughout. That is the ordinary path rather than
 * an edge case, because `TenantClassification` is a backstop and COMP-02's wizard
 * question does not exist. ✅ **[[\App\Services\Warehouse\PhiExclusion]] is
 * what closes it** — at `Replayer`, at `L1Loader`, and as a purge inside the
 * reclassification transaction. **This CHECK is unchanged and still the layer
 * that saves you**; what changed is the sentence describing its reach.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('l1_events', function (Blueprint $table): void {
            // From L0. Not generated here — §5.3's ReplacingMergeTree dedupes on
            // this, and the Postgres equivalent is the primary key plus the
            // replay's ON CONFLICT DO NOTHING: re-reading the same L0 batch
            // cannot duplicate a row.
            //
            // ⛔ **`->primary()` HERE IS SUPERSEDED AND THE LINE IS LEFT AS IT
            // RAN, 2026-08-20 (6240).** A shipped migration is history and is
            // not edited; `2026_08_21_035151_key_l1_events_by_tenant_and_event`
            // drops this constraint and adds `(business_id, event_id)`, which
            // is what a fresh install ends up with too. **Do not read this line
            // as the key** — read `pg_constraint`, or that migration.
            $table->uuid('event_id')->primary();

            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            // Strings cast to PHP backed enums, never database enums.
            $table->string('data_class', 16);
            $table->string('event_type', 64);
            $table->string('consent_state', 16);

            $table->uuid('anonymous_id')->nullable();
            $table->uuid('session_id')->nullable();

            // §5.3's occurred_at / received_at. `ingested_at` is deliberately
            // absent — see the class docblock and decision 4862.
            $table->timestamp('occurred_at', 3);
            $table->timestamp('received_at', 3);

            $table->boolean('is_bot');
            $table->unsignedSmallInteger('bot_score');

            $table->string('page_path', 512);
            $table->string('page_host', 255);
            $table->string('referrer_host', 255)->nullable();

            $table->string('utm_source', 128)->nullable();
            $table->string('utm_medium', 128)->nullable();
            $table->string('utm_campaign', 255)->nullable();

            $table->string('device_type', 32);

            // ⚠️ NO RAW IP COLUMN EXISTS AND NONE MAY BE ADDED. CLAUDE.md
            // §"Privacy & data": "Never store raw IP." §5.3 carries `ip_hash`,
            // which this table does not yet have either — there is no collector
            // to compute one from an address it then discards in the same scope,
            // and a nullable column nothing writes is decision 272's shape.
            // It arrives with the collector or not at all.

            // Whatever the payload carried beyond the typed columns, as the
            // canonical JSON text the derivation produced. Never jsonb.
            $table->text('properties');

            // §5.3: "l0_path gives you lineage from any row back to its source
            // object." This is what makes a targeted replay possible and what
            // makes an unexplained row answerable.
            $table->string('l0_path', 512);
            $table->unsignedSmallInteger('schema_version');

            // §5.3's ORDER BY, which is also the snapshot's ordering and the
            // range scan a replay uses to clear before it rebuilds.
            $table->index(['business_id', 'received_at', 'event_id'], 'l1_events_tenant_time_index');
        });

        $classes = collect(DataClassification::cases())
            ->reject(fn (DataClassification $class): bool => $class === DataClassification::Phi)
            ->map(fn (DataClassification $class): string => "'".$class->value."'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE l1_events
                ADD CONSTRAINT l1_events_data_class_is_never_phi
                CHECK (data_class IN ({$classes}))
        SQL);

        // Valid JSON without letting Postgres normalise it: the cast is checked
        // and thrown away, the stored bytes are the derivation's own.
        DB::statement(<<<'SQL'
            ALTER TABLE l1_events
                ADD CONSTRAINT l1_events_properties_is_json
                CHECK (properties::jsonb IS NOT NULL)
        SQL);

        // §12's bot table scores 0-100.
        DB::statement(<<<'SQL'
            ALTER TABLE l1_events
                ADD CONSTRAINT l1_events_bot_score_in_range
                CHECK (bot_score BETWEEN 0 AND 100)
        SQL);

        DB::statement('ALTER TABLE l1_events ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE l1_events FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON l1_events
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('l1_events');
    }
};
