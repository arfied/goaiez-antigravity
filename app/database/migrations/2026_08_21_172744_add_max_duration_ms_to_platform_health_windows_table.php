<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How long the slowest run in this hour took — decisions 7080–7099.
 *
 * ## Why a duration belongs on a table of counters
 *
 * Decision 6975 is the reason this column exists: wave 6 gave all thirty-five
 * entries in `routes/console.php` an argued `withoutOverlapping()` window, and
 * **every one of those windows is reasoned from what the command does rather
 * than from what it has done**, because nothing in this application has ever
 * recorded how long a scheduled command takes. The one failure that change can
 * introduce — a window expiring under a still-running process, so a second copy
 * starts beside the first — was therefore the one failure nothing here could
 * report.
 *
 * ⚠️ **A NEW TABLE WAS REFUSED AND THIS IS WHY.** The subject is the platform's
 * own machinery, which is exactly what `platform_health_windows` already is; the
 * hourly bucket, the `(signal, source, window_start)` unique index, the atomic
 * `ON CONFLICT DO UPDATE` idiom, the thirty-day prune on
 * `ops:watch-platform-health` and the `TenancyTest` exemption argument all exist
 * and all apply unchanged. A second table would have needed its own copy of
 * every one of them, and would have been a second place to look for the same
 * question.
 *
 * ⚠️ **NULLABLE, AND NULL IS NOT ZERO.** Three of the four signals on this table
 * have no duration at all — a heartbeat, a rejected webhook and a vendor call
 * are events rather than runs — so their rows leave this null. A default of `0`
 * would make "no duration was ever recorded" read as "the slowest run took no
 * time", which is the shape `sending_health_windows` failed in (2496–2499): a
 * plausible figure standing in for a measurement nobody took.
 *
 * ⚠️ **MILLISECONDS, AND A MAXIMUM RATHER THAN A SUM OR A MEAN.** The question
 * this column is read to answer is *"could this command's slowest honest run
 * have outlived its own lock"*, and only the slowest run answers it — an average
 * over an hour of a nightly sweep is the sweep's one run divided by nothing, and
 * an average over a minute-by-minute entry hides the single 400-second run that
 * is the entire finding. Milliseconds because the fastest entries here are
 * measured in tens of them and a second-resolution column would record every one
 * of them as zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_health_windows', function (Blueprint $table): void {
            $table->unsignedBigInteger('max_duration_ms')->nullable()->after('failures');
        });
    }

    public function down(): void
    {
        Schema::table('platform_health_windows', function (Blueprint $table): void {
            $table->dropColumn('max_duration_ms');
        });
    }
};
