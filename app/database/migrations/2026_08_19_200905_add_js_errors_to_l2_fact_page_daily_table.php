<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fourth of `28` §4.3's rollback triggers needs a denominator, and it
 * already had one.
 *
 * §4.3: *"JS error rate on affected pages doubles."* A **rate** on a **page**,
 * so the numerator belongs beside the page's own `pageviews` rather than in a
 * mart of its own — one table, one grain, one row read to answer the question.
 * `l2_fact_page_daily` already counts every non-bot event for a path; this adds
 * the `js_error` filter next to the four counts it already keeps.
 *
 * ⚠️ **A PAGE CAN CARRY ERRORS AND NO PAGEVIEWS, AND THAT ROW ALREADY
 * EXISTED.** `Replayer::rebuildFactPageDaily()` groups every non-bot event by
 * path and counts pageviews with a `FILTER`, so a path whose only event is an
 * error already produced a row with `pageviews = 0`. That is why the reader
 * treats a zero denominator as a state rather than dividing by it.
 *
 * ⚠️ **NO `after()`.** It is a MySQL clause Laravel silently drops on Postgres,
 * and column position is not part of the answer here anyway:
 * `WarehouseSnapshot` selects an explicit, written-down list precisely so that
 * `pg_attribute` order cannot become part of the compared bytes.
 *
 * ⚠️ **`default(0)` IS FOR THE ALTER, NOT FOR THE DERIVATION.** Every row in
 * this table is rebuilt from L1 on the next replay of its range, so the default
 * only fills rows that exist right now; `WarehouseTest`'s reproducible-DDL lint
 * forbids a default that reads a clock, a sequence or a random source, and a
 * constant zero is none of those.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('l2_fact_page_daily', function (Blueprint $table): void {
            $table->unsignedInteger('js_errors')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('l2_fact_page_daily', function (Blueprint $table): void {
            $table->dropColumn('js_errors');
        });
    }
};
