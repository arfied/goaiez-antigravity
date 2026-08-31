<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ⛔ **THE COLUMNS 2026-08-18's UNSIGNED AUDIT DID NOT REACH** (8140–8159).
 *
 * `2026_08_18_101216_constrain_unsigned_columns_that_postgres_does_not` states
 * that *"every `unsigned*` column in the schema was enumerated and classified"*.
 * ⚠️ **The enumeration was real and its result was a hand-written list**, and a
 * hand-written list is the artefact this codebase keeps watching go stale — the
 * `CLAUDE.md` schema paragraph, the thirteen-name horizon list, the prose
 * exceptions `TenancyTest`'s `$exempt` replaced. Re-derived on 2026-08-22 by
 * parsing every `->unsigned*('col')` in `database/migrations/` and asking
 * `pg_constraint` about each one, **136 declared columns came back and 55 of
 * them had no sign CHECK.**
 *
 * ⚠️ **31 OF THE 55 ARE REAL GAPS AND 24 ARE THE AUDIT'S OWN WRITTEN
 * EXEMPTIONS** — 20 sequence- or foreign-key-fed ids and `jobs`' four
 * framework-owned columns. **This migration takes 9 of the 31.** The other 22
 * are the L2 marts, which belong to a parallel lane this wave and are recorded
 * as owed rather than taken; `UnsignedColumnConstraintsTest`'s `$exempt` names
 * every one of them, with the line that has to be deleted when they land.
 *
 * ⛔ **AND THE SHARPEST ONE IS `platform_health_windows.max_duration_ms`.** The
 * audit constrained `total` and `failures` on that table on 2026-08-18; the
 * column was added on 2026-08-21, to a table the audit had **already** decided
 * about, and did not join the constraint. **That is what a hand list costs**:
 * being right once does not survive the next migration, and nothing failed.
 * `UnsignedColumnConstraintsTest`'s census lint is the half of this slice that
 * stops the next one, and this migration is only the arrears.
 *
 * ## The floor is nought everywhere here, and that is argued rather than tidy
 *
 * Every predicate below is `>= 0`. The audit's own boundary rule is that a `> 0`
 * written where `>= 0` was meant refuses a real value — and every one of these
 * has a real nought: a monthly usage row is created at zero events and
 * incremented, a delivery sample starts at zero pageviews, a reject counter is
 * declared `default(0)`, the first industry page sits at position 0, and a
 * health window that has recorded no run at all has no maximum duration.
 *
 * ⚠️ **`pixel_bundle_versions.byte_size` IS THE ONE WHERE `> 0` WAS TEMPTING**
 * and is refused: a zero-byte bundle is a broken build rather than a negative
 * one, `PixelTest`'s budget gate is what judges the size, and a CHECK that
 * aborted the row would lose the record of the build that went wrong.
 *
 * ## Tenancy
 *
 * No table is created and no policy is touched, so every table keeps the RLS
 * `ENABLE`+`FORCE` and the policy its creating migration gave it. A CHECK is
 * evaluated on the row being written, after the policy has already permitted it.
 */
return new class extends Migration
{
    /**
     * Every constraint this migration adds, as `[table, name, predicate]`.
     *
     * One list rather than nine `DB::statement()` calls, for the reason the
     * 2026-08-18 migration gives: `down()` has to name each of them again and
     * two hand-maintained lists drift.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function constraints(): array
    {
        return [
            /*
             * The order a marketing page sits in on the industries index.
             * `SeedIndustryPages` writes it from a manifest and the first page
             * is at nought.
             */
            ['industry_pages', 'industry_pages_position_is_not_negative', 'position >= 0'],

            /*
             * ⚠️ **AN UPSERT COUNTER, WHICH IS THE SHAPE THE AUDIT WAS WRITTEN
             * ABOUT.** `IngestRejects::record()` writes
             * `ingest_rejects.rejects + excluded.rejects`, so the stored value is
             * arithmetic rather than a literal — the one class of write where a
             * negative arrives without anybody typing one.
             */
            ['ingest_rejects', 'ingest_rejects_count_is_not_negative', 'rejects >= 0'],

            /*
             * The pixel bundle's size on disk and gzipped. `PixelDelivery` writes
             * `strlen($stamped)`; `PixelTest` weighs the real artefact against
             * the 14,336-byte budget and is what judges whether a size is right.
             * This only says it is not below nought.
             */
            [
                'pixel_bundle_versions',
                'pixel_bundle_versions_sizes_are_not_negative',
                'byte_size >= 0 AND gzip_byte_size >= 0',
            ],

            /*
             * The canary's two counters. `PixelDeliveryHealth` increments them
             * per accepted batch and the canary watch compares their ratio
             * against the halt threshold — so a negative `js_errors` does not
             * read as an error, it reads as a **healthier** bundle and holds off
             * a rollback.
             */
            [
                'pixel_delivery_samples',
                'pixel_delivery_samples_counters_are_not_negative',
                'pageviews >= 0 AND js_errors >= 0',
            ],

            /*
             * ⛔ **THE MONTHLY EVENT CAP READS THESE TO DECIDE WHETHER TO DROP
             * TRAFFIC.** `MonthlyEventCap` compares `events_total` against the
             * plan's ceiling, so a negative total is a tenant who can never reach
             * their cap — a bound defeated by arithmetic rather than by anybody's
             * decision, which is 3297's shape.
             */
            [
                'pixel_monthly_usage',
                'pixel_monthly_usage_counters_are_not_negative',
                'events_total >= 0 AND events_dropped >= 0',
            ],

            /*
             * ⛔ **THE COLUMN THIS MIGRATION EXISTS FOR.** Added 2026-08-21 to a
             * table the 2026-08-18 audit had already constrained.
             * `PlatformHealth::recordRun()` is its only writer and
             * `PlatformHealthSignal` reads `MAX(max_duration_ms)` to decide
             * whether a scheduled run is overrunning — so a negative is a job
             * that reads as instantaneous.
             *
             * ⚠️ **NULLABLE, AND THE PREDICATE SAYS SO EXPLICITLY.** A window
             * that has recorded no run has no maximum. A CHECK refuses only when
             * its predicate is FALSE, so `max_duration_ms >= 0` alone would
             * already accept NULL — the `IS NULL` arm is written anyway, because
             * a reader of `pg_get_constraintdef()` should not have to know that
             * rule to know the column is nullable.
             */
            [
                'platform_health_windows',
                'platform_health_windows_max_duration_is_not_negative',
                'max_duration_ms IS NULL OR max_duration_ms >= 0',
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->constraints() as [$table, $name, $predicate]) {
            /*
             * ⚠️ **RAW `SELECT` ON THE SAME CONNECTION AS THE `ALTER`**, the
             * 2026-08-18 migration's own rule: the runtime role is under FORCE
             * row-level security, so a count taken there would answer `0` for a
             * table full of violations while the `ALTER` failed for
             * `permission denied`.
             *
             * `NOT (predicate)` is the CHECK's own rejection rule, NULL handling
             * included — pinned as an assertion in
             * `tests/Feature/Schema/UnsignedColumnConstraintsTest.php`.
             */
            $offending = (int) DB::selectOne(
                sprintf('SELECT count(*) AS offending FROM %s WHERE NOT (%s)', $table, $predicate)
            )->offending;

            if ($offending > 0) {
                throw new RuntimeException(sprintf(
                    '%s already holds %d row(s) that violate %s — CHECK (%s). '
                    .'A stored value outside this range has already been read somewhere; '
                    .'decide what those rows should say before constraining the column.',
                    $table,
                    $offending,
                    $name,
                    $predicate,
                ));
            }

            DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s)', $table, $name, $predicate));
        }
    }

    public function down(): void
    {
        foreach ($this->constraints() as [$table, $name]) {
            DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $table, $name));
        }
    }
};
