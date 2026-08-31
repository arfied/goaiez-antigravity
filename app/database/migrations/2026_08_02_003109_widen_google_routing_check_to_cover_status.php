<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `reviews_google_is_never_routed` was narrower than its own stated reason.
 *
 * ⚠️ THE 314–316 FAMILY, INSIDE THE CORRECTION FOR IT. The constraint added one
 * migration ago argues — in its docblock, in decision 383 and in its test's
 * comment — that it exists because *"`status = 'in_triage'` written against a
 * Google row fails `Review::displayable()`'s status clause, and that is hiding a
 * Google review"*. It then constrained `routing_decision`, `routed_destinations`
 * and `routed_at` and **not `status`**, so
 * `UPDATE reviews SET status='in_triage' WHERE source='google'` passed the CHECK
 * and still hid the review. The stated protection layer was broader than the one
 * that shipped, which is the exact shape slice B's whole-branch review found and
 * this constraint was written to close.
 *
 * The original migration is committed and is not edited; this widens it.
 *
 * ⚠️ `approved` IS DELIBERATELY STILL ALLOWED, AND THAT IS THE PART A FUTURE
 * READER WILL WANT TO "FIX". `29` §2 rule 1 reads "never held, hidden,
 * **approved**, or moderated", so `status = 'approved'` on a Google row looks
 * like the next thing to prohibit. It is not, and prohibiting it would be
 * actively harmful: `Review::displayable()` requires
 * `status IN ('approved','displayed')`, so those two values are precisely what
 * makes a Google review *visible*. A CHECK forbidding `approved` would hide one
 * hundred per cent of Google reviews, permanently — **decision 358's bug,
 * re-created in a place no test would think to look.** Rule 1 forbids subjecting
 * a Google review to an approval *decision*; it does not forbid the string
 * appearing in a bookkeeping column, which is what `ReviewStatus`' own docblock
 * already says: "rows ingested from Google carry a status for bookkeeping, never
 * for gating."
 *
 * `in_triage` AND NOTHING ELSE, THEN. `rejected` and `flagged` also hide a
 * Google review and are also forbidden by rule 1 in spirit — but this constraint
 * is named `..._is_never_routed`, its subject is what routing writes, and
 * `in_triage` is the only status routing can produce. Adding the other two would
 * make the constraint broader than any stated reason, which is the mirror of the
 * defect being fixed here, and would assert something about slice I's importer
 * before that importer exists to be constrained. If a writer for either ever
 * appears, it earns its own constraint with its own name and its own test.
 *
 * NOTHING EXISTING VIOLATES THIS. `reviews.status` defaults to `pending`,
 * `ReviewFactory::fromGoogle()` leaves it there, and `ReviewRouter` — which
 * refuses a Google row on its first line — is the only `in_triage` writer in the
 * codebase. Verified before writing, not after.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT reviews_google_is_never_routed');

        DB::statement(<<<'SQL'
            ALTER TABLE reviews
                ADD CONSTRAINT reviews_google_is_never_routed
                CHECK (
                    source <> 'google'
                    OR (
                        routing_decision IS NULL
                        AND routed_destinations IS NULL
                        AND routed_at IS NULL
                        AND status <> 'in_triage'
                    )
                )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT reviews_google_is_never_routed');

        DB::statement(<<<'SQL'
            ALTER TABLE reviews
                ADD CONSTRAINT reviews_google_is_never_routed
                CHECK (
                    source <> 'google'
                    OR (
                        routing_decision IS NULL
                        AND routed_destinations IS NULL
                        AND routed_at IS NULL
                    )
                )
        SQL);
    }
};
