<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Somebody was sent to a review destination — row 3 slice B.
 *
 * WHAT IS MISSING HERE IS THE DESIGN. There is no status, no outcome, no
 * completed_at, no posted_at, and no review_left flag, because decision 113 says
 * a destination click is never recorded or reported as a review and no platform
 * gives us a completion callback. Expressing that as an absence of anywhere to
 * write one is stronger than expressing it as a rule about how to read a column:
 * a report cannot aggregate a field that does not exist. A schema test asserts
 * no completion-shaped column ever appears (decision 307).
 *
 * NO UNIQUE CONSTRAINT EITHER. A customer clicking Google twice is two events.
 * Deduplicating would quietly convert a click log into an attempt to count
 * people, which is the first step toward reporting completions.
 *
 * The only true signal that a review landed is it appearing in review sync or
 * email ingest (`24` §1.4). That is slice I's, and it is a different table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destination_clicks', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // The first-party review the click hangs off. A click with no review
            // has no context and cannot be attributed to anything.
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();

            $table->string('destination');

            $table->timestamp('clicked_at');

            // created_at only: rows are written once. See the model's booted()
            // guards — an editable click log is one that can be made to say a
            // review was left.
            $table->timestamp('created_at')->nullable();

            // Postgres does not index foreign-key columns automatically — only
            // the primary key gets one for free. This is the slice's only
            // growth table, `business_id` is the predicate the RLS policy below
            // adds to every query whether or not the caller filtered on it, and
            // both FKs are cascadeOnDelete, so deleting a business or a review
            // would otherwise sequentially scan the whole click log to find its
            // rows. The composite also covers offeredFor()-style per-review
            // lookups, which is the other access pattern the tests exercise.
            $table->index(['business_id', 'review_id']);
        });

        DB::statement('ALTER TABLE destination_clicks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE destination_clicks FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON destination_clicks
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_clicks');
    }
};
