<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When this recipient was first put off — decision 2687, closing 2589's owed
 * ceiling.
 *
 * ⛔ **WITHOUT THIS COLUMN THE DEFERRAL HAS NO CLOCK, AND 2589 SAYS SO IN
 * TERMS.** 2570 stopped `RunCampaignJob` answering every refusal with a
 * terminal `markRefused()` — the right trade, because a campaign started at
 * 21:30 met `QuietHours` on every contact and closed with no recoverable list.
 * What it bought instead was an **unbounded** deferral: a `Skipped` row is
 * outstanding, so an imported list nobody ever supplies a `region_code` for is
 * re-marked `Skipped` every fifteen minutes forever, and the campaign never
 * closes and never tells anybody.
 *
 * ⚠️ **`updated_at` COULD NOT DO THIS JOB AND IS THE OBVIOUS THING TO REACH
 * FOR.** Every barren pass re-saves the row, so `updated_at` is *always* a few
 * minutes old — it records the most recent deferral, and the ceiling needs the
 * first one. A ceiling measured from `updated_at` would never be reached.
 *
 * ⚠️ **AND IT IS NOT CLEARED WHEN THE ROW FINALLY SENDS.** A contact who was
 * held for nine days and then reached is a different fact from one who went
 * straight out, and it is the fact somebody investigating a slow campaign wants.
 * Nothing reads it after the send; it costs one nullable timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table): void {
            $table->timestamp('first_deferred_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table): void {
            $table->dropColumn('first_deferred_at');
        });
    }
};
