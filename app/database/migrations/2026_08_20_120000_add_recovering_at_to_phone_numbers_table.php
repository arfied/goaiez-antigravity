<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a number entered `recovering` — doc 51 §5.3's cooldown recovery, the
 * half that walks a rested number the rest of the way back.
 *
 * ⚠️ **THE FOURTH COLUMN OF A SET OF THREE, AND IT IS WRITTEN THE SAME WAY THE
 * OTHER THREE ARE.** `quarantined_at`, `retired_at` and `released_at` are all
 * stamped by `NumberLifecycle::transitionTo()` — the one writer of `state` —
 * and `quarantined_at` is additionally *cleared* on the way out, on the
 * argument its own migration makes: a stale timestamp beside a state that has
 * moved on reads to the next person as *"this is happening right now"*,
 * because they checked the column precisely because it sounded like an answer.
 * This one takes `quarantined_at`'s posture rather than the two terminal ones:
 * set on entry to `recovering`, cleared on every exit.
 *
 * ⛔ **WITHOUT IT THERE IS NO CLOCK ON THE SECOND HOP, AND THE TWO OBVIOUS
 * SUBSTITUTES BOTH FAIL.** `phone_numbers.updated_at` looks like the answer and
 * is not: `NumberHealthService::recompute()` writes `health_score` on this row
 * every hour, so `updated_at` is the last *score* refresh far more often than
 * it is the last state change. `number_state_changes` genuinely holds the
 * moment — and a `MessagingTest` chokepoint confines that model to
 * `NumberLifecycle`, deliberately, so that the history has exactly one writer;
 * adding a second file that names it to read a timestamp would spend that
 * property to save a column.
 *
 * ⚠️ **NO BACKFILL, AND THAT IS THE FAIL-SAFE DIRECTION.** A number already
 * sitting in `recovering` when this deploys has a null `recovering_at`, and
 * `NumberRecovery` requires a non-null one to walk anything to `active` — so
 * such a number holds where it is until an operator moves it, rather than
 * being handed a manufactured entry time that could make it due the same night.
 * There is exactly one way into `recovering` today (`numbers:release-quarantine`,
 * an Ops command with a typed reason), so the population is small and known.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_numbers', function (Blueprint $table): void {
            $table->timestamp('recovering_at')->nullable()->after('quarantined_at');
        });
    }

    public function down(): void
    {
        Schema::table('phone_numbers', function (Blueprint $table): void {
            $table->dropColumn('recovering_at');
        });
    }
};
