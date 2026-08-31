<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make "run twice, one side effect" enforceable rather than aspirational.
 *
 * FOUND-05 requires that a job replayed with the same idempotency key produces
 * one side effect. Nothing weaker than a database constraint actually delivers
 * that: a cache lock evaporates on eviction, and Laravel's ShouldBeUnique is
 * queue-level, so it stops a duplicate *dispatch* while doing nothing about the
 * same work arriving tomorrow from the scheduler, or after a failed deploy
 * replays a batch.
 *
 * So the uniqueness lives here, and the base class treats the violation as the
 * signal rather than as an error — the row already existing *is* the answer to
 * "has this run?".
 *
 * Nullable because not every automation is replay-shaped. A job with no natural
 * key (a nightly sweep that is meant to run every night) leaves it null, and
 * PostgreSQL does not collide nulls in a unique index, so those rows coexist
 * freely. That is the behaviour we want and it is worth stating, because the
 * same schema in MySQL would need thinking about.
 *
 * Composite with business_id: two businesses running the same automation over
 * the same subject must not collide, and a global unique here would let one
 * tenant's run block another's — while revealing that it happened.
 *
 * See docs/DECISIONS.md 182.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_runs', function (Blueprint $table): void {
            $table->string('idempotency_key')->nullable()->after('automation_key');

            $table->unique(
                ['business_id', 'automation_key', 'idempotency_key'],
                'automation_runs_idempotency_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('automation_runs', function (Blueprint $table): void {
            $table->dropUnique('automation_runs_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
