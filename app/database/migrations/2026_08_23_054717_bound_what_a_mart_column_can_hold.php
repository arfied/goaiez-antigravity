<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * What a mart column can hold — in both directions, and by two different tools.
 *
 * ## ⛔ THE OVERFLOW HALF, AND WHY IT IS A WIDTH RATHER THAN A CHECK
 *
 * `l2_fact_session.pageviews`, `l2_fact_session.conversions` and
 * `l2_fact_conversion.touch_count` were declared `unsignedSmallInteger()` —
 * `smallint`, **max 32,767** — and every one of them is a `count()` over rows a
 * stranger's browser produces. `StorePixelBatchRequest::MAX_EVENTS` is 50 and
 * `App\Support\PixelRateLimits::BEACONS_PER_MINUTE` is 300, so **one host may
 * post 15,000 accepted events a minute**, and `pageview` is the one event type
 * [[\App\Services\Pixel\MonthlyEventCap]] admits past its cap unconditionally
 * and for ever (7704: *"there is no per-tenant ceiling on accepted traffic
 * anywhere in this application"*).
 *
 * ⛔ **REPRODUCED, NOT DERIVED.** 656 beacons of 50 `pageview` events on one
 * client-minted `session_id` — 32,800 — raised `SQLSTATE[22003]: smallint out of
 * range` inside `rebuildFactSession()`'s INSERT, which aborts `replay()`'s
 * transaction and takes **every mart of the range** with it. **L0 is immutable**,
 * so the receipt is archived for ever and every retry fails identically: decision
 * 5031's *"one `POST` would permanently deny a tenant their own warehouse"*, at
 * about two minutes of traffic from a single address.
 *
 * ⚠️ **THAT FIXTURE IS NOT WHAT SHIPS IN THE SUITE, AND
 * `tests/Feature/Warehouse/MartBoundsTest.php` SAYS WHY IN FULL.** It cost 185 MB
 * of peak and exhausted `phpunit.xml.dist`'s 512 MB ceiling — a zero-byte run for
 * the whole suite. The committed test drives the derivation at small scale and
 * the width directly.
 *
 * ⚠️ **A CHECK WOULD HAVE BEEN THE WRONG TOOL AND A CLAMP WOULD HAVE BEEN THE
 * WRONG ANSWER.** A CHECK converts a permanent denial into a *different*
 * permanent denial. A clamp — which is what [[\App\Services\Warehouse\Replayer]]
 * now does to `duration_s` and `days_to_convert` — silently under-reports a
 * count, and a count is meaningful at every magnitude: there is no §8 sentence
 * saying a session may not have 40,000 pageviews, only arithmetic saying nobody
 * legitimate does. **Width is the right instrument exactly when the number is
 * honest and the type was the accident.**
 *
 * ⚠️ **`integer` RATHER THAN `bigint`, AND THE REASON IS COHERENCE RATHER THAN
 * BYTES.** Every other count in every other mart is `unsignedInteger`, and
 * `l2_fact_daily_tenant.pageviews` is an `integer` **sum of these very rows** —
 * so a session grain wider than the daily grain that rolls it up would move the
 * overflow one method down rather than removing it, which is 314–316's shape
 * with a `bigint` on it. The whole family is bounded by one thing and it is not a
 * column width: **7704's missing per-tenant ceiling on accepted traffic.** That
 * is reported, not built here — its figure is an operator's.
 *
 * ⚠️ **WHAT THIS DOES AND DOES NOT REMOVE.** After it, overflowing a session
 * count needs 2,147,483,648 `l1_events` rows on **one** `session_id` inside one
 * replay range — roughly a hundred days of a single host's entire accepted
 * throughput, all of it still resident under [[WarehouseRetention]]'s 400-day L1
 * horizon, at several hundred gigabytes for one tenant. That is a storage attack
 * long before it is an overflow, and it is visible: `MonthlyEventCap::alertOnce()`
 * rings. The two-minute version is gone; the class is not, and saying so is the
 * point.
 *
 * ## ✅ THE SIGN HALF — EIGHTEEN COLUMNS, ON 5112'S STANDARD
 *
 * `unsignedInteger()`/`unsignedSmallInteger()` are documentation on PostgreSQL
 * and reach nothing; `tests/Feature/Schema/UnsignedColumnConstraintsTest.php`
 * exists to say so, and its `$exempt` block named eighteen `l2_fact_*` columns as
 * **owed rather than argued** — exempt only because `app/Services/Warehouse/**`
 * belonged to a parallel lane on 2026-08-22. This lane holds them, so they are
 * paid.
 *
 * ⚠️ **THE STANDARD IS 5112'S AND NOT "IS IT REACHABLE".** *"Unreachable by
 * today's writer is the wrong test — the right one is whether a negative would
 * ever be **meaningful**, which for a count it never is."* Seventeen of the
 * eighteen are counts and the eighteenth (`days_to_convert`) is a duration; a
 * negative is unreachable by construction on all eighteen today, which is
 * precisely the state in which the next edit makes one reachable and nothing
 * says so.
 *
 * ⛔ **AND A CHECK ON A DERIVED TABLE IS NOT A REJECTED WRITE — READ 5031 BEFORE
 * ADDING THE NINETEENTH.** It is a permanently unreplayable range. These are
 * added because every one of them is refusing a value that **cannot be produced
 * by any input** — `count(*)`, `count(DISTINCT …)` and `sum()` of non-negative
 * columns are non-negative for every possible L0 — so the only way to trip one is
 * a change to the derivation, which is exactly what a backstop is for. ⚠️ **The
 * two clamped columns are the ones to be careful about**, and neither gained an
 * upper bound here for that reason.
 *
 * ⚠️ **NO CHECK IS ADDED TO `l2_fact_session`**: 8182's four conjuncts already
 * cover it. What that constraint lacked was **driving cases** — it shipped with
 * none, because the census only asks *"is it bounded"* — and those are added to
 * the dataset in the same wave.
 */
return new class extends Migration
{
    /**
     * The four marts, and the columns of each that no input can make negative.
     *
     * ⚠️ **GROUPED PER TABLE BECAUSE A CONSTRAINT IS PER TABLE**, and named
     * `<table>_counters_are_not_negative` to match `l2_fact_source_daily`'s own,
     * which is the sibling this copies rather than a new convention.
     *
     * @var array<string, list<string>>
     */
    private const array COLUMNS = [
        'l2_fact_conversion' => ['days_to_convert', 'touch_count'],
        'l2_fact_daily_tenant' => [
            'bot_sessions', 'conversions', 'directions_clicks', 'engaged_sessions',
            'form_submissions', 'new_users', 'pageviews', 'phone_clicks', 'sessions', 'users',
        ],
        'l2_fact_page_daily' => ['conversions', 'exits', 'js_errors', 'pageviews', 'unique_views'],
        'l2_fact_vital_daily' => ['samples'],
    ];

    public function up(): void
    {
        // ⚠️ THE WIDTHS FIRST. `ALTER TYPE smallint -> integer` is a table
        // rewrite in PostgreSQL and these marts are small; the CHECK that
        // already covers two of them survives it unchanged, which is why the
        // order is safe either way.
        DB::statement('ALTER TABLE l2_fact_session ALTER COLUMN pageviews TYPE integer');
        DB::statement('ALTER TABLE l2_fact_session ALTER COLUMN conversions TYPE integer');
        DB::statement('ALTER TABLE l2_fact_conversion ALTER COLUMN touch_count TYPE integer');

        foreach (self::COLUMNS as $table => $columns) {
            $predicate = implode(' AND ', array_map(
                static fn (string $column): string => $column.' >= 0',
                $columns,
            ));

            DB::statement(
                "ALTER TABLE {$table} ADD CONSTRAINT {$table}_counters_are_not_negative CHECK ({$predicate})"
            );
        }
    }

    /**
     * ⚠️ **THE DOWN NARROWS THE COLUMNS BACK AND WILL FAIL IF ANYTHING USED THE
     * ROOM**, which is the honest behaviour: a `smallint` that silently truncated
     * a real count would be a worse state than a migration that refuses to run.
     */
    public function down(): void
    {
        foreach (array_keys(self::COLUMNS) as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_counters_are_not_negative");
        }

        DB::statement('ALTER TABLE l2_fact_conversion ALTER COLUMN touch_count TYPE smallint');
        DB::statement('ALTER TABLE l2_fact_session ALTER COLUMN conversions TYPE smallint');
        DB::statement('ALTER TABLE l2_fact_session ALTER COLUMN pageviews TYPE smallint');
    }
};
