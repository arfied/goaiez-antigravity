<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L2 — `GOAIEZ_PIXEL_MASTER_BUILD` §5.4's `fact_daily_tenant`: the one screen
 * a tenant is most likely to look at, rebuilt from `l2_fact_session` and
 * `l1_events` rather than from L1 alone — the session-grain facts
 * (`is_engaged`, `is_bot`, `is_new`) already live on `l2_fact_session`, and
 * recomputing them here from raw events would be a second derivation that
 * agrees with the first until it does not (the same reasoning `L1Loader`'s
 * docblock gives for having one insert path rather than two).
 *
 * §5.4: *"All L2 tables exclude bot traffic from tenant-facing metrics (bot
 * counts are carried separately)."* `bot_sessions` is that separate count;
 * `sessions` is never `sessions + bot_sessions`.
 *
 * ⚠️ **REDUCED FROM §5.4'S OWN COLUMN LIST**, on `l1_events`'s own precedent
 * (4865). Not carried: `avg_lcp_ms`/`avg_inp_ms`/`avg_cls`/`js_errors`/
 * `uptime_pct` — each needs parsing a `vital`/`js_error` event's JSON
 * `properties`, which is a derivation of its own and not this slice's to
 * invent; `identified_users` — needs `identity_id`, and §13 is unbuilt
 * (4865, same reasoning). `bot_pct` is also not carried: a stored ratio is
 * exactly what `l2_fact_source_daily`'s own docblock refuses — *"a rate
 * belongs in the report that divides two of these, never in the mart"* — and
 * `sessions`/`bot_sessions` are both here for a report to divide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('l2_fact_daily_tenant', function (Blueprint $table): void {
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            $table->date('day');

            $table->unsignedInteger('sessions');
            $table->unsignedInteger('engaged_sessions');
            $table->unsignedInteger('bot_sessions');
            $table->unsignedInteger('users');
            $table->unsignedInteger('new_users');

            $table->unsignedInteger('pageviews');
            $table->unsignedInteger('conversions');
            $table->unsignedInteger('phone_clicks');
            $table->unsignedInteger('form_submissions');
            $table->unsignedInteger('directions_clicks');

            $table->primary(['business_id', 'day'], 'l2_fact_daily_tenant_pkey');
        });

        DB::statement('ALTER TABLE l2_fact_daily_tenant ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE l2_fact_daily_tenant FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON l2_fact_daily_tenant
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('l2_fact_daily_tenant');
    }
};
