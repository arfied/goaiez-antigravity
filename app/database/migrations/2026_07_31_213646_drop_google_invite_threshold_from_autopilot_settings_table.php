<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The single global review threshold, removed — row 3 slice B, decision 304.
 *
 * DELIBERATELY DEVIATES FROM DATA-MODEL §5.4, which still lists this column. Its
 * own comment there already pointed at `review_destinations` for the real
 * answer, because decision 111 split the threshold per destination before any
 * code existed: Google may be asked selectively, Trustpilot may be asked only if
 * every customer is asked, and one number cannot say both.
 *
 * WHY DROP RATHER THAN LEAVE IT UNREAD. Two sources of truth for "should this
 * customer be asked" is drift in the field that decides whether a message goes
 * out — and this is the one with an admin screen attached, so on any
 * disagreement it would win by accident rather than by decision. Nothing read it
 * before today (`review_destinations` did not exist), so nothing loses a value
 * it was using.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('autopilot_settings', function (Blueprint $table): void {
            $table->dropColumn('google_invite_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('autopilot_settings', function (Blueprint $table): void {
            // The default it always carried — decision 110's owner override.
            $table->smallInteger('google_invite_threshold')->default(5);
        });
    }
};
