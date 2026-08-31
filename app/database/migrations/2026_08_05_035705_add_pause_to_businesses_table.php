<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The owner's stop. `29` §11.2 row 5's *Pause*, and `28` §9.5's *"same as the
 * owner's Pause Everything"* given the thing it says it is the same as.
 *
 * ⚠️ THIS IS NOT THE KILL SWITCH THAT ALREADY EXISTS, AND THE DIFFERENCE IS THE
 * REASON BOTH ARE NEEDED. `AutopilotJob::killSwitchThrownFor()` reads
 * `config('autopilot.kill_switch')` — deliberately from config rather than the
 * database, because a kill switch that needs a working database is not much of
 * a kill switch — and it is **platform-wide**: thrown, every tenant stops. That
 * is ours, for the day something of ours is misbehaving. This is the tenant's,
 * for the day something *of theirs* is, and it is per-business state that only a
 * database can hold. Neither substitutes for the other, and the job checks them
 * in that order.
 *
 * ⚠️ ON `businesses` RATHER THAN `autopilot_settings`, WHICH IS PER LOCATION.
 * Pause Everything is one switch on the whole account — an owner reaching for it
 * is not in a state to think about which of their locations is affected, and a
 * per-location pause on a multi-location tenant is a pause that half works. The
 * per-location toggles remain what they were; this sits above all of them.
 *
 * ## The three columns move together, and a CHECK says so
 *
 * `paused_at` is the state. `paused_by` is the actor, in `audit_log`'s own
 * vocabulary (`user:14`, `support:9`) rather than a foreign key — support pauses
 * on a tenant's behalf under `28` §9.5, so the column has to name a person who
 * is not this tenant's user, and automation is a first-class actor in every
 * other record here.
 *
 * The CHECK refuses the two incoherent states: an actor with no pause, and a
 * pause with no actor. Both are what a half-finished repair script leaves
 * behind, and the second is the one that matters — a paused account nobody can
 * attribute is a support conversation with no answer in it. Decisions 216, 359
 * and 383's pattern: the claim and the constraint land together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->timestampTz('paused_at')->nullable();
            $table->string('paused_by')->nullable();
            $table->string('pause_reason', 500)->nullable();
        });

        // ⚠️ `pause_reason` IS DELIBERATELY OUTSIDE THIS CONSTRAINT. Requiring
        // it would make the CHECK refuse the owner's own pause, which is the
        // only path that exists today and the one with nothing to explain — an
        // owner pressing their own stop button owes nobody a sentence. Support
        // does, and that is enforced where support's path is built.
        DB::statement(<<<'SQL'
            ALTER TABLE businesses
                ADD CONSTRAINT businesses_pause_is_attributed
                CHECK ((paused_at IS NULL AND paused_by IS NULL)
                    OR (paused_at IS NOT NULL AND paused_by IS NOT NULL))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE businesses DROP CONSTRAINT businesses_pause_is_attributed');

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn(['paused_at', 'paused_by', 'pause_reason']);
        });
    }
};
