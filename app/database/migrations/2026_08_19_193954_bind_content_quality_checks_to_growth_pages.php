<?php

declare(strict_types=1);

use App\Services\Reviews\ModerationVerdict;
use App\Support\Readability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The foreign key `content_quality_checks` has been waiting for since Stage 0,
 * and the one column its spec is missing — decisions 5560–5572.
 *
 * ⛔ **THE DEFERRAL IS THE STAGE 0 MIGRATION'S OWN**, in as many words:
 * *"Unconstrained: growth_pages belongs to the content slice … The foreign key
 * lands with that table."* It lands here, in the slice that creates the table
 * and writes the first row this table has ever had.
 *
 * ## ⛔ `passed` HAS THREE STATES AND THE THIRD ONE IS DECISION 347
 *
 * The column is nullable and always was; nothing had given the null a meaning.
 * It has one now, and it is the same three-state shape as
 * `reviews.moderation_flags` (346) and {@see ModerationVerdict}:
 *
 *   `true`   every check ran and the page cleared the gate
 *   `false`  a check ran and refused — `failure_reasons` says which
 *   `null`   no verdict exists — `unavailable_reason` says why nobody could say
 *
 * **A moderation refusal is the third state and never the second.** 347: *"a
 * refusal withholds for a human and is not recorded as a failure … filing it as
 * an outage would send somebody to check a vendor that is fine."* Filing it as a
 * *content* failure is worse than that — it tells a tenant their copy broke a
 * rule it did not break. So `unavailable_reason` is added rather than another
 * `ContentQualityFailure` case, and a CHECK constraint makes the three states
 * mutually exclusive at the database, because "which column is populated" is
 * exactly the kind of pairing prose cannot hold (303–316's three layers).
 *
 * ⚠️ **`readability_score` HOLDS A GRADE, SO LOWER IS BETTER.** `DATA-MODEL.md`
 * named the column and never said which direction it runs; the gate's rule is
 * `≤ 8` (`29` §9.1, doc `33`). A reader who assumes the other direction inverts
 * the check and every fixture still passes, so it is written here, in
 * {@see Readability}, in the model, and back into `DATA-MODEL.md`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_quality_checks', function (Blueprint $table): void {
            // ⚠️ **WHY NOTHING NEEDS BACKFILLING**: this table has had zero
            // writers since the day it was created (`BUILD-PLAN` §2.11.1), so
            // every deployment's copy of it is empty. That is 272's shape
            // finally being closed rather than a lucky break.
            $table->foreign('page_id')
                ->references('id')
                ->on('growth_pages')
                ->cascadeOnDelete();

            // Why a verdict does not exist. Never a customer-facing string and
            // never a content judgement — 'moderation_refused',
            // 'moderation_unavailable'.
            $table->string('unavailable_reason', 64)->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE content_quality_checks
                ADD CONSTRAINT content_quality_checks_verdict_is_one_of_three CHECK (
                    (passed IS TRUE  AND failure_reasons IS NULL     AND unavailable_reason IS NULL)
                    OR (passed IS FALSE AND failure_reasons IS NOT NULL AND unavailable_reason IS NULL)
                    OR (passed IS NULL  AND failure_reasons IS NULL     AND unavailable_reason IS NOT NULL)
                )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE content_quality_checks DROP CONSTRAINT content_quality_checks_verdict_is_one_of_three');

        Schema::table('content_quality_checks', function (Blueprint $table): void {
            $table->dropForeign(['page_id']);
            $table->dropColumn('unavailable_reason');
        });
    }
};
