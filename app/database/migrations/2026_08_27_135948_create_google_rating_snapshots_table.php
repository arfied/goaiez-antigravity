<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An append-only daily history of one location's own Google review count and
 * rating — review-loss detection (row `GOAIEZ-MASTER-PLAN.md:8293`, wave 38
 * lane D).
 *
 * ⛔ **THE SUBSTRATE THIS TABLE FIXES.**
 * `App\Services\Visibility\CompetitorSignals::recordOurOwnRating()` has
 * written `locations.review_count` and `locations.current_rating` daily since
 * the day it shipped, and every write **overwrites** the last one — so there
 * is no yesterday to compare today against, and a loss has nothing to be a
 * loss *from*. This table is that yesterday: one row per successful read,
 * kept rather than replaced.
 *
 * ⚠️ **`review_count` IS NULLABLE WITH NO DEFAULT, ON `competitor_snapshots`'
 * AND `locations.review_count`'s OWN RULE (2026-08-26 migrations,
 * `PlaceSummary::knownReviewCount()`).** Google omits a field holding its
 * default value even when the mask asked for it, so an absent count is
 * usually a genuine zero and NOT "we don't know" — but the one case that IS
 * "we don't know" (a response that contradicts itself: a rating with no
 * count) has to stay distinguishable from a corroborated zero, and from a day
 * this table has no row for at all. `App\Services\Visibility\ReviewLossDetection`
 * is the only writer and is the only thing allowed to decide which of the
 * three a given call means — a `?? 0` at any reader is the defect that made
 * every nullable sibling column in this schema nullable in the first place.
 * `rating` is written only when Google sent one (the same gate
 * `recordOurOwnRating()` already applies before it touches `locations`), so a
 * row only ever exists for a call that had at least a rating to report.
 *
 * ⚠️ **NO `destination` COLUMN.** The plan's own words are "daily count per
 * destination", but Google is the only destination this platform has any
 * count substrate for at all — Facebook, Trustpilot, Yelp and BBB have no
 * review-count sync of any kind, on any table, today. A column that would
 * only ever hold one value is speculative generality; the table name says
 * what it actually is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_rating_snapshots', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('review_count')->nullable();
            $table->decimal('rating', 2, 1)->nullable();

            $table->timestamp('captured_at');

            $table->index(['location_id', 'captured_at']);
        });

        // The hot read walks one location's history newest-first; a raw index
        // for the DESC direction, `idx_boost_history_location`'s exact pattern
        // one table over.
        DB::statement(
            'CREATE INDEX idx_google_rating_snapshots_location
                 ON google_rating_snapshots (location_id, captured_at DESC)'
        );

        DB::statement(
            'ALTER TABLE google_rating_snapshots
                 ADD CONSTRAINT google_rating_snapshots_rating_between_0_and_5
                 CHECK (rating IS NULL OR rating BETWEEN 0 AND 5)'
        );

        // `unsignedInteger()` is documentation on PostgreSQL, not enforcement
        // — CLAUDE.md §Convention tests, `UnsignedColumnConstraintsTest`'s
        // whole subject. A SQL CHECK passes on NULL, so this is a floor on the
        // number when one is reported, never a claim that one exists.
        DB::statement(
            'ALTER TABLE google_rating_snapshots
                 ADD CONSTRAINT google_rating_snapshots_review_count_is_not_negative
                 CHECK (review_count IS NULL OR review_count >= 0)'
        );

        DB::statement('ALTER TABLE google_rating_snapshots ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE google_rating_snapshots FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON google_rating_snapshots
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('google_rating_snapshots');
    }
};
