<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `l1_events` is keyed `(business_id, event_id)`, not `event_id` — decision 6182.
 *
 * ⛔ **THE DEFECT THIS CLOSES IS SILENT DATA LOSS ACROSS A TENANT BOUNDARY, AND
 * IT IS NOT A UNIQUENESS PROBLEM.** `event_id` is minted in the **browser** —
 * `resources/js/pixel.js`'s `randomId()` — so it is a client-supplied value that
 * shared one global key space across every tenant on the platform. A unique
 * index is **not** filtered by row-level security: Postgres checks it against
 * rows the reader's policy hides, [[\App\Services\Warehouse\L1Loader]] inserts
 * `ON CONFLICT DO NOTHING`, and the second tenant to present an id already held
 * by the first simply lost the event. Counted in `etl_runs.l0_lines`, absent
 * from `l1_rows`, absent from `l0_rejected`, reported nowhere.
 *
 * ⚠️ **AN ACCIDENTAL COLLISION WAS NEGLIGIBLE; A CHOSEN ONE WAS NOT.** Two
 * UUIDv4s do not meet by chance, and a page that emits a *chosen* id is a page
 * the tenant next door controls. **A small oracle rode with it**: a tenant could
 * learn whether *somebody* on the platform already held an id, by watching
 * whether their own event survived.
 *
 * ⚠️ **THE ARGUMENT THE OLD KEY WAS MADE ON IS UNTOUCHED AND STAYS TRUE.** The
 * creating migration argued `event_id` on §11 row 5's idempotency — *"re-reading
 * the same L0 batch cannot duplicate a row"* — and never weighed the
 * cross-tenant half. **A composite key delivers that idempotency in full**: a
 * conflict can now only be *your own* row, which is exactly what a retried
 * beacon, a retried queue job and a replay of an already-live object produce.
 * What it stops delivering is the thing nobody wanted, which is a refusal keyed
 * on somebody else's data.
 *
 * ⚠️ **L1 WAS THE OUTLIER RATHER THAN THE PATTERN.**
 * `2026_08_18_041901_create_l2_fact_conversion_table.php` keys **the same value**
 * as `primary(['business_id', 'conversion_id'])`, and its own docblock records
 * that `conversion_id` *is* the converting event's `event_id`. Every mart in the
 * warehouse is `(business_id, …)`. This makes L1 agree with them.
 *
 * ✅ **AND IT DOES NOT DISTURB THE BYTE-IDENTICAL DDL GATE, WHICH IS WRITTEN
 * DOWN HERE RATHER THAN LEFT FOR A REVIEWER TO RE-DERIVE** (4580). That gate
 * forbids five things and `WarehouseTest`'s reproducible-DDL lint is what
 * enforces them: an identity column, a sequence default, a clock-reading
 * default, a random default, and a `double precision` / `real` / `jsonb` column.
 * **A composite primary key is none of the five, and that lint makes no
 * assertion about a primary key at all** — it walks `pg_attribute` column by
 * column. Ordering is likewise untouched: `l1_events_tenant_time_index` on
 * `(business_id, received_at, event_id)` is not dropped, and
 * [[\App\Services\Warehouse\WarehouseSnapshot]] still orders explicitly by
 * `received_at, event_id`, which is where the snapshot's determinism actually
 * lives.
 *
 * ⚠️ **A `DROP CONSTRAINT` RATHER THAN A REBUILD, AND THE REASON IS NOT ROW
 * COUNT.** Adding `(business_id, event_id)` over a table whose `event_id` was
 * already globally unique **cannot fail on data**: the pair is implied by the
 * single column, so no duplicate can exist to reject. What varies with row count
 * is how long the `ACCESS EXCLUSIVE` lock is held while the new unique index is
 * built, and nothing else.
 */
return new class extends Migration
{
    public function up(): void
    {
        // `l1_events_pkey` is Postgres' own default name for a primary key on
        // this table, so dropping and re-adding it lands back on the same
        // spelling — the one `l2_fact_conversion_pkey` uses two tables over.
        // Written out rather than left to `Blueprint::dropPrimary()`, which
        // guesses that name on Postgres and says nothing about it.
        DB::statement('ALTER TABLE l1_events DROP CONSTRAINT l1_events_pkey');

        DB::statement('ALTER TABLE l1_events ADD CONSTRAINT l1_events_pkey PRIMARY KEY (business_id, event_id)');
    }

    /**
     * ⚠️ **THIS ROLLBACK CAN FAIL, AND FAILING IS THE CORRECT BEHAVIOUR.**
     * Re-imposing a global unique index over rows collected while the key was
     * per-tenant raises `SQLSTATE[23505]` the moment two tenants hold one
     * `event_id` — which is precisely the state `up()` made legitimate. The
     * alternative is a `down()` that decides which tenant's event to destroy,
     * silently, on the way back to the defect this migration exists to close.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE l1_events DROP CONSTRAINT l1_events_pkey');

        DB::statement('ALTER TABLE l1_events ADD CONSTRAINT l1_events_pkey PRIMARY KEY (event_id)');
    }
};
