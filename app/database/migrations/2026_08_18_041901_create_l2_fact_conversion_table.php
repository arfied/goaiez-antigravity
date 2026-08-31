<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L2 — `GOAIEZ_PIXEL_MASTER_BUILD` §5.4's `fact_conversion`, and §8's
 * attribution: *"last non-direct touch, 30-day lookback. Resolve at
 * conversion time, freeze on the row, never recompute."*
 *
 * ⛔ **`attributed_source_key` IS WRITTEN ONCE, BY THE DERIVATION, AND NEVER
 * UPDATED BY ANYTHING ELSE.** §8's own words: *"A number that changes after a
 * client reads it is a support ticket."* Nothing outside
 * `App\Services\Warehouse\Replayer` may write this column — the same
 * chokepoint rule `WarehouseTest`'s *"nothing outside the warehouse writes to
 * a derived layer"* already holds `l1_events` and `l2_fact_source_daily` to.
 *
 * ⚠️ **`attributed_at` IS THE CONVERSION'S OWN `occurred_at`, NOT THE MOMENT
 * THE DERIVATION RAN.** §8 says resolution happens *"at conversion time"* —
 * read literally, the two are the same instant — and a column holding the
 * derivation's wall-clock would read `now()` at rebuild time, which is
 * exactly what would break `ByteIdenticalReplayTest`: two replays run at two
 * different moments would then disagree on every row.
 *
 * ⚠️ **`conversion_id` IS THE CONVERTING EVENT'S OWN `event_id`, REUSED
 * RATHER THAN GENERATED** — the same "no generated id" rule `l1_events`
 * follows, for the same reason: a random id here would break the byte-
 * identical gate on every replay.
 *
 * ⚠️ **REDUCED FROM §5.4'S OWN COLUMN LIST**, on `l1_events`'s own precedent
 * (4865). Not carried: `value_usd` — this codebase has no monetary value
 * concept for a `phone_click`/`form_submitted`/etc. conversion yet, and
 * defaulting one to `0` would be fabricated data rather than an honest
 * absence; `data_class` — `l1_events_data_class_is_never_phi` already makes
 * this constant at this layer, so carrying it here would be a column with one
 * possible value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('l2_fact_conversion', function (Blueprint $table): void {
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            // Bucketed on `received_at`, the same convention `l2_fact_session`
            // and `l2_fact_source_daily` both use, and what lets `Replayer`
            // delete-then-rebuild this table over the same day range.
            $table->date('day');

            $table->uuid('conversion_id');

            $table->uuid('session_id')->nullable();
            $table->uuid('anonymous_id')->nullable();

            // §8's four: form_submitted | phone_click | email_click |
            // directions_click. A string cast to a PHP backed enum, never a
            // database enum — see `App\Enums\ConversionType`.
            $table->string('conversion_type', 32);

            $table->timestamp('occurred_at', 3);

            $table->string('attributed_source_key', 255);
            $table->timestamp('attributed_at', 3);

            $table->unsignedSmallInteger('touch_count');
            $table->unsignedSmallInteger('days_to_convert');

            $table->string('page_path', 512);

            $table->primary(['business_id', 'conversion_id'], 'l2_fact_conversion_pkey');
        });

        DB::statement('ALTER TABLE l2_fact_conversion ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE l2_fact_conversion FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON l2_fact_conversion
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('l2_fact_conversion');
    }
};
