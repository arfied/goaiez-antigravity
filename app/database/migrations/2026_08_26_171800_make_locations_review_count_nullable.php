<?php

declare(strict_types=1);

use App\Services\Visibility\CompetitorSignals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `locations.review_count` becomes nullable, so that "this location has never
 * been synced against Google" stops being stored as "this location has no
 * reviews" — the same repair `2026_08_26_124120_make_competitor_snapshot_review_count_nullable`
 * made for `competitor_snapshots.review_count`, one table over.
 *
 * ⛔ **THE COLUMN COULD ONLY EVER SAY ONE OF THE TWO.** It was `integer` NOT
 * NULL `default(0)`, and its single writer —
 * {@see CompetitorSignals::recordOurOwnRating()} —
 * only ever touches it when `$subject->rating !== null`, so a location whose
 * sync has never run, or whose every sync so far returned a listing with no
 * rating, keeps the schema default of `0` for ever. `Admin\LocationSettings`
 * renders that `0` under "Reviews" with no distinction from a business that
 * genuinely has none.
 *
 * ⚠️ **`current_rating` ON THIS SAME TABLE WAS ALREADY NULLABLE FOR EXACTLY
 * THIS REASON, WITH NO DEFAULT.** The two columns are the same kind of fact
 * about the same vendor response, written by the same method in the same
 * `forceFill()`, and `App\Models\Location`'s own docblock already points a
 * reader here as the pair worth reading twice. They now share the same shape:
 * nullable, no default, "we do not know" is a real value rather than a number.
 *
 * ⛔ **THE DEFAULT IS DROPPED, NOT JUST THE NOT-NULL.** A `default(0)` beside a
 * nullable column would still stamp every newly-provisioned location with a
 * confident zero until the first successful sync — the exact ambiguity this
 * migration exists to remove, just deferred to row creation instead of column
 * definition.
 *
 * ⛔ **NOTHING IS BACKFILLED AND NOTHING CAN BE.** Every existing row's `0` is
 * unrecoverably ambiguous — Places' 24-hour cache is long past for any of them
 * and no payload is logged anywhere — and a migration establishes no tenant, so
 * an `UPDATE` over a FORCE-RLS table would match zero rows and report success
 * (`CLAUDE.md` §Convention tests). The history stays as written; only what is
 * recorded from here on is honest. `Admin\LocationSettings` says as much on the
 * screen rather than implying every zero shown from today is a fresh reading.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE locations ALTER COLUMN review_count DROP NOT NULL');
        DB::statement('ALTER TABLE locations ALTER COLUMN review_count DROP DEFAULT');
    }

    public function down(): void
    {
        // A row this migration made possible has no truthful integer to become,
        // so the reversal has to invent one to satisfy the constraint it is
        // restoring. Zero is chosen because it is what this column held before —
        // reverting restores the old reading rather than inventing a new one.
        DB::statement('UPDATE locations SET review_count = 0 WHERE review_count IS NULL');
        DB::statement('ALTER TABLE locations ALTER COLUMN review_count SET DEFAULT 0');
        DB::statement('ALTER TABLE locations ALTER COLUMN review_count SET NOT NULL');
    }
};
