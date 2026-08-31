<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The daily self-audit's stop, on the row it is a fact about.
 *
 * Doc `16` §15.3: *"Alert owner + auto-pause generation if thresholds breach."*
 *
 * ⛔ **THE VOLUME CAPS DO NOT LIVE HERE AND MUST NOT BE MOVED HERE.** A cap
 * breach is a **count** — four pages this month, twenty per cent this quarter —
 * so it is derived from `growth_pages` on every attempt, refuses at the job, and
 * releases itself at the period boundary with nothing to clear. A stored copy of
 * a derivable fact is a column that drifts from the rows that justify it, which
 * is `messaging_lane`'s argument and `site_changes.tier`'s.
 *
 * ⚠️ **THE SELF-AUDIT IS THE OTHER SHAPE AND THAT IS WHY IT NEEDS A COLUMN.**
 * Its inputs are ninety days of Search Console impressions — somebody else's
 * API, over a network, rate-limited and occasionally down. Deriving that on the
 * publish path would put a vendor round trip in front of every page and make the
 * gate's answer depend on Google being up. So the audit runs on a clock, and
 * what it decided is read from here.
 *
 * ⚠️ **IT CLEARS ITSELF AND THAT IS DELIBERATE.** The next clean audit lifts the
 * pause. There is no Ops release command and no tenant-facing toggle: the owner
 * is told what to fix (`OwnerActionNeeded`), and a switch that says *"ignore the
 * audit"* is a support surface offering to turn off the thing protecting the
 * tenant from a manual action.
 *
 * ⚠️ **ALL-OR-NOTHING BY CHECK**, on `site_changes`' rollback trio (5524): a
 * pause with no reason cannot be explained to the owner it stopped, and a reason
 * with no pause is a sentence nothing acts on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->timestamp('content_generation_paused_at')->nullable();
            $table->string('content_generation_pause_reason', 64)->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE locations
                ADD CONSTRAINT locations_content_pause_is_all_or_nothing CHECK (
                    (content_generation_paused_at IS NULL) = (content_generation_pause_reason IS NULL)
                )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE locations DROP CONSTRAINT locations_content_pause_is_all_or_nothing');

        Schema::table('locations', function (Blueprint $table): void {
            $table->dropColumn(['content_generation_paused_at', 'content_generation_pause_reason']);
        });
    }
};
