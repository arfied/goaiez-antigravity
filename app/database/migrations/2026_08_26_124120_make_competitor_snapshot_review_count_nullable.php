<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `competitor_snapshots.review_count` becomes nullable, so that "Google did not
 * tell us how many reviews this peer has" stops being stored as "this peer has
 * no reviews".
 *
 * ⛔ **THE COLUMN COULD ONLY EVER SAY ONE OF THE TWO.** It was `unsignedInteger`
 * NOT NULL, and its single writer wrote `$peer->userRatingCount ?? 0` — so a
 * response that carried a peer's rating and no count was recorded as a business
 * with nought reviews, in a tenant-owned table, with nothing anywhere marking it
 * apart from a peer who genuinely has none.
 *
 * ⚠️ **`rating` ON THIS SAME TABLE WAS ALREADY NULLABLE FOR EXACTLY THIS
 * REASON**, and `CompetitorSignals::compare()` skips a snapshot whose rating is
 * null rather than averaging a nought into the neighbourhood. The two columns
 * are the same kind of fact about the same vendor response; they now have the
 * same shape.
 *
 * ⚠️ **THE CHECK CONSTRAINT IS UNTOUCHED AND STILL CORRECT.**
 * `competitor_snapshots_review_count_is_not_negative` is `review_count >= 0`,
 * and a SQL CHECK passes on NULL — a floor on a number is not a claim that the
 * number exists. `tests/Feature/Schema/UnsignedColumnConstraintsTest` still
 * drives -1 red and still stores 0.
 *
 * ⛔ **NOTHING IS BACKFILLED AND NOTHING CAN BE.** Every existing row's `0` is
 * unrecoverably ambiguous — the response that produced it is long past the
 * Places cache's 24 hours and no payload is logged anywhere — and a migration
 * establishes no tenant, so an `UPDATE` over a FORCE-RLS table would match zero
 * rows and report success (`CLAUDE.md` §Convention tests). The history stays as
 * written; only what is recorded from here on is honest.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE competitor_snapshots ALTER COLUMN review_count DROP NOT NULL');
    }

    public function down(): void
    {
        // A row this migration made possible has no truthful integer to become,
        // so the reversal has to invent one to satisfy the constraint it is
        // restoring. Zero is chosen because it is what this column held before —
        // reverting restores the old reading rather than inventing a new one.
        DB::statement('UPDATE competitor_snapshots SET review_count = 0 WHERE review_count IS NULL');
        DB::statement('ALTER TABLE competitor_snapshots ALTER COLUMN review_count SET NOT NULL');
    }
};
