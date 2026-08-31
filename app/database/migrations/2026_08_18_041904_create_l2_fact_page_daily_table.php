<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L2 — `GOAIEZ_PIXEL_MASTER_BUILD` §5.4's `fact_page_daily`. `exits` is
 * rebuilt from `l2_fact_session.exit_page_path` — the same reasoning
 * `l2_fact_daily_tenant`'s docblock gives for reading session-grain facts
 * back from `l2_fact_session` rather than re-deriving them from raw events.
 *
 * ⚠️ **REDUCED FROM §5.4'S OWN COLUMN LIST**, on `l1_events`'s own precedent
 * (4865). Not carried: `avg_active_s`/`avg_scroll_pct` — both are per-page
 * rollups of the same `scroll_depth` JSON extraction `l2_fact_session.active_s`
 * already does at the session grain, and duplicating that parse into a
 * second, differently-scoped aggregate is scope this slice does not need to
 * take on to answer §8's canonical definitions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('l2_fact_page_daily', function (Blueprint $table): void {
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            $table->date('day');
            $table->string('page_path', 512);

            $table->unsignedInteger('pageviews');
            $table->unsignedInteger('unique_views');
            $table->unsignedInteger('exits');
            $table->unsignedInteger('conversions');

            $table->primary(['business_id', 'day', 'page_path'], 'l2_fact_page_daily_pkey');
        });

        DB::statement('ALTER TABLE l2_fact_page_daily ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE l2_fact_page_daily FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON l2_fact_page_daily
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('l2_fact_page_daily');
    }
};
