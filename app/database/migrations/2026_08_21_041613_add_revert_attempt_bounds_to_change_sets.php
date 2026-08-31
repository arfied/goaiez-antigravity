<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The three columns that stop a failed revert being retried for ever — 6055's
 * fourth part, built at 6265.
 *
 * ⛔ **THE RETRY ITSELF IS RIGHT AND IS NOT WHAT THIS BOUNDS.** Leaving our own
 * harmful edit on a customer's website because one night's attempt failed is
 * worse than asking again, and `SiteMeasurements::dueForRevert()`'s docblock
 * says so at length. What was missing is the other end: **the sweep re-asked
 * every night, for ever, with no counter, no spacing and nothing that ever said
 * we had given up.** A site that has been gone for six months was asked again
 * last night, and nobody was told either fact.
 *
 * ⚠️ **THREE COLUMNS BECAUSE THE ANSWER HAS THREE PARTS**, and collapsing any
 * two of them loses one:
 *
 *   - `revert_attempts` — how many times the row has been handed to the sweep.
 *     Without it there is no ceiling to state.
 *   - `revert_attempt_after` — the earliest the next attempt may be made. This
 *     is the **backoff**, and a ceiling without one is the same nightly
 *     hammering with an end date.
 *   - `revert_attempts_exhausted_at` — when we stopped. Without it the sweep
 *     cannot tell *"has had its last attempt"* from *"has not yet"*, and the
 *     owner-facing item would either never be filed or be filed every night —
 *     5746's card-a-night failure at the moment it matters most.
 *
 * ⛔ **`revert_attempts_exhausted_at` IS SET ONE SWEEP AFTER THE LAST ATTEMPT,
 * NEVER BEFORE IT.** Marking a row exhausted at the moment its final attempt is
 * dispatched would file *"we have stopped trying"* about an attempt that had not
 * run and might yet succeed — telling a customer their page is stuck while it is
 * being unstuck. The next sweep sees the count at the ceiling with the row still
 * live, which is the first moment the sentence is true.
 *
 * ⚠️ **BOTH TABLES, THE SAME THREE NAMES, ONE CEILING.** `site_changes` carries
 * slice H's nightly revert retry; `speed_change_sets` carries slice L's
 * `RevertFailed` arm of `SpeedFixes::dueForJudgement()`, which 6055 names
 * separately and which is the same failure on a different table — 5861 already
 * records that the first sweep cannot see the second's rows.
 *
 * ⚠️ **`unsignedSmallInteger` AND NOT A BIGINT.** The ceiling is
 * `SiteMeasurements::REVERT_ATTEMPT_CEILING`; a column that could hold 65,535
 * attempts is already four orders of magnitude wider than anything this can
 * count to, and a wider one would be storage bought against a number that is
 * bounded in code.
 *
 * ⛔ **NO `CHECK` TYING THE THREE TOGETHER, AND THAT IS DELIBERATE** — unlike
 * slice H's three measurement columns, which are all-or-nothing because a row
 * claiming a verdict with nothing behind it is a lie. These three are a
 * *progression*: a row legitimately has attempts and no exhaustion, a next-time
 * and no exhaustion, and an exhaustion with the next-time cleared. Every
 * combination the writer produces is a real state, so a constraint could only
 * forbid states that occur.
 *
 * ⚠️ **ROW-LEVEL SECURITY IS THE TABLES' AND IS UNTOUCHED.** Both carry
 * `ENABLE` + `FORCE` and their policies from their creating migrations; adding
 * columns needs no second one, and `TenancyTest`'s lints derive their subject
 * from the table rather than from a column list.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const array TABLES = ['site_changes', 'speed_change_sets'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedSmallInteger('revert_attempts')->default(0);
                $blueprint->timestamp('revert_attempt_after')->nullable();
                $blueprint->timestamp('revert_attempts_exhausted_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn([
                    'revert_attempts',
                    'revert_attempt_after',
                    'revert_attempts_exhausted_at',
                ]);
            });
        }
    }
};
