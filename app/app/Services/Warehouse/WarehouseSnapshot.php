<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use Generator;
use Illuminate\Support\Facades\DB;

/**
 * The derived layers, rendered to bytes, so "byte-identical" can be asserted.
 *
 * `GOAIEZ_PIXEL_MASTER_BUILD` §11 row 7's acceptance criterion is *"truncate
 * L1/L2, rebuild from L0, byte-identical results"*, and `29` §12.1 lists replay
 * fidelity among the build-failing adversarial tests. Neither says what "the
 * results" are as a sequence of bytes. This class decides, once, and every
 * assertion and the `etl_runs` digest use it — because a comparison each caller
 * invents is a comparison each caller can weaken.
 *
 * ⛔ **RENDERED BY POSTGRES, NOT BY PHP, AND THAT IS THE WHOLE DESIGN.** The
 * obvious implementation fetches models and serialises them, which compares
 * *PHP's* idea of each value: a `Carbon` reformatted, an integer that arrived as
 * a string, a float printed through `serialize_precision`. Every one of those
 * normalises away a difference that is really there in the table. So each column
 * is cast to text **in SQL**, and what is compared is Postgres's own rendering of
 * what Postgres actually stored.
 *
 * The four hazards that rendering brings, and what is done about each:
 *
 *  1. ⛔ **RENDERING A TIMESTAMP DEPENDS ON TWO SESSION SETTINGS** — `TimeZone`
 *     and `DateStyle`. `timestamptz::text` prints one stored instant as
 *     `2026-08-17 12:34:56.789+00` or `08/17/2026 05:34:56.789 PDT` depending on
 *     who asked, so a replay from a cron shell with a different `PGTZ` would
 *     differ from the original in every timestamp. **Two things answer it here
 *     and the second is counter-intuitive.** `to_char()` with an explicit
 *     pattern ([[L0Line::PG_TIMESTAMP_FORMAT]]) reads neither setting; and the
 *     columns are **`timestamp`, not `timestamptz`** — the house convention
 *     `TimeTest` enforces — so they carry no zone to begin with. ⚠️ **Which is
 *     why there is deliberately NO `AT TIME ZONE 'UTC'` below**: on a plain
 *     `timestamp` that expression *converts to* `timestamptz` and **introduces**
 *     the session dependency rather than removing it. The obvious-looking
 *     hardening is the bug.
 *  2. ⛔ **`date::text` DEPENDS ON `DateStyle` TOO** — `2026-08-17` under ISO,
 *     `08-17-2026` under SQL. Same fix, explicit pattern.
 *  3. ⛔ **TEXT ORDERING DEPENDS ON THE DATABASE COLLATION.** `ORDER BY
 *     utm_source` under `en_US.UTF-8` ignores punctuation and case in ways `C`
 *     does not, so `'_direct'` and `'Direct'` come back in a different order on a
 *     database created with a different `LC_COLLATE` — or on the same database
 *     after a glibc upgrade silently changes one. Every text column in an ORDER
 *     BY here is `COLLATE "C"`, which is byte order and is the same everywhere.
 *  4. ⚠️ **`SELECT *` WOULD MAKE COLUMN ORDER PART OF THE ANSWER.** A dropped and
 *     re-added column moves to the end of `pg_attribute`, so the same data would
 *     render in a different key order after an unrelated migration. The column
 *     lists below are written down, in order, and a column added to a table
 *     without being added here is simply not compared — which is why the
 *     reproducible-DDL lint checks the two against each other.
 *
 * ⚠️ **NULL AND EMPTY STRING STAY DISTINGUISHABLE**, which is why the rows go
 * through [[CanonicalJson]] rather than being joined with a separator: `null` and
 * `""` are two different JSON tokens, where in a delimited line they are the same
 * empty field. CLAUDE.md's own list of replay hazards names null-vs-absent, and
 * this is where it is answered.
 */
final class WarehouseSnapshot
{
    /**
     * `l1_events`, in the column order the snapshot compares.
     *
     * @var array<string, string> column => SQL expression rendering it to text
     */
    private const array L1_COLUMNS = [
        'event_id' => 'event_id::text',
        'business_id' => 'business_id::text',
        'data_class' => 'data_class',
        'event_type' => 'event_type',
        'consent_state' => 'consent_state',
        'anonymous_id' => 'anonymous_id::text',
        'session_id' => 'session_id::text',
        // ⛔ NO `AT TIME ZONE 'UTC'` HERE, AND THAT IS THE OPPOSITE OF WHAT IT
        // LOOKS LIKE IT SHOULD BE — see hazard 1 in the class docblock. These are
        // `timestamp` columns, so they carry no zone and `to_char` on them reads
        // no session setting. Applying `AT TIME ZONE 'UTC'` would **convert them
        // to `timestamptz`** and reintroduce the dependency.
        'occurred_at' => "to_char(occurred_at, '%FORMAT%')",
        'received_at' => "to_char(received_at, '%FORMAT%')",
        'is_bot' => 'is_bot::text',
        'bot_score' => 'bot_score::text',
        'page_path' => 'page_path',
        'page_host' => 'page_host',
        'referrer_host' => 'referrer_host',
        'utm_source' => 'utm_source',
        'utm_medium' => 'utm_medium',
        'utm_campaign' => 'utm_campaign',
        'device_type' => 'device_type',
        'properties' => 'properties',
        'l0_path' => 'l0_path',
        'schema_version' => 'schema_version::text',

        // §11 row 8 (decision 5000s). Plain text columns — no timestamp, no
        // float, no jsonb hazard among them — so none of the four rendering
        // rules above applies; they are here so the reproducible-DDL and
        // snapshot-completeness lints in `WarehouseTest` have something to
        // compare, which is the whole point of the second of those two.
        'ip_hash' => 'ip_hash',
        'browser' => 'browser',
        'browser_version' => 'browser_version',
        'os' => 'os',
    ];

    /**
     * `l2_fact_source_daily`, likewise.
     *
     * @var array<string, string>
     */
    private const array L2_COLUMNS = [
        'business_id' => 'business_id::text',
        'day' => "to_char(day, 'YYYY-MM-DD')",
        'utm_source' => 'utm_source',
        'utm_medium' => 'utm_medium',
        'events' => 'events::text',
        'sessions' => 'sessions::text',
        'visitors' => 'visitors::text',
        'bot_events' => 'bot_events::text',
    ];

    /**
     * `l2_fact_session`, likewise — and the one list here with no `day` in it.
     *
     * ⛔ **THAT ABSENCE IS THE OWNER'S RULING AND THIS LINT IS HALF OF WHAT HOLDS
     * IT** (8040, 2026-08-22). A session is one row keyed
     * `(business_id, session_id)`; the day dimension lives only on the `*_daily`
     * rollups, and `first_received_at` is the receipt watermark the rollups
     * truncate and the retention sweep expires on. The snapshot-completeness lint
     * compares this list against `pg_attribute`, so a `day` re-added to the mart
     * would have to be added here too.
     *
     * ⚠️ **`first_received_at` RENDERS THROUGH `%FORMAT%` LIKE THE OTHER TWO
     * TIMESTAMPS AND NOT LIKE THE `day` IT REPLACED.** It is a `timestamp(3)`
     * rather than a `date`, so hazard 1 in this class's docblock applies to it and
     * hazard 2 does not — and a `to_char(…, 'YYYY-MM-DD')` copied from the old
     * entry would silently stop comparing the time of day.
     *
     * @var array<string, string>
     */
    private const array L2_FACT_SESSION_COLUMNS = [
        'business_id' => 'business_id::text',
        'session_id' => 'session_id::text',
        'anonymous_id' => 'anonymous_id::text',
        'started_at' => "to_char(started_at, '%FORMAT%')",
        'ended_at' => "to_char(ended_at, '%FORMAT%')",
        'first_received_at' => "to_char(first_received_at, '%FORMAT%')",
        'duration_s' => 'duration_s::text',
        'active_s' => 'active_s::text',
        'pageviews' => 'pageviews::text',
        'conversions' => 'conversions::text',
        'is_engaged' => 'is_engaged::text',
        'is_bot' => 'is_bot::text',
        'is_new' => 'is_new::text',
        'entry_page_path' => 'entry_page_path',
        'exit_page_path' => 'exit_page_path',
        'source_key' => 'source_key',
        'device_type' => 'device_type',
        'consent_state' => 'consent_state',
    ];

    /**
     * `l2_fact_conversion`, likewise.
     *
     * @var array<string, string>
     */
    private const array L2_FACT_CONVERSION_COLUMNS = [
        'business_id' => 'business_id::text',
        'day' => "to_char(day, 'YYYY-MM-DD')",
        'conversion_id' => 'conversion_id::text',
        'session_id' => 'session_id::text',
        'anonymous_id' => 'anonymous_id::text',
        'conversion_type' => 'conversion_type',
        'occurred_at' => "to_char(occurred_at, '%FORMAT%')",
        'attributed_source_key' => 'attributed_source_key',
        'attributed_at' => "to_char(attributed_at, '%FORMAT%')",
        'touch_count' => 'touch_count::text',
        'days_to_convert' => 'days_to_convert::text',
        'page_path' => 'page_path',
    ];

    /**
     * `l2_fact_daily_tenant`, likewise.
     *
     * @var array<string, string>
     */
    private const array L2_FACT_DAILY_TENANT_COLUMNS = [
        'business_id' => 'business_id::text',
        'day' => "to_char(day, 'YYYY-MM-DD')",
        'sessions' => 'sessions::text',
        'engaged_sessions' => 'engaged_sessions::text',
        'bot_sessions' => 'bot_sessions::text',
        'users' => 'users::text',
        'new_users' => 'new_users::text',
        'pageviews' => 'pageviews::text',
        'conversions' => 'conversions::text',
        'phone_clicks' => 'phone_clicks::text',
        'form_submissions' => 'form_submissions::text',
        'directions_clicks' => 'directions_clicks::text',
    ];

    /**
     * `l2_fact_page_daily`, likewise.
     *
     * @var array<string, string>
     */
    private const array L2_FACT_PAGE_DAILY_COLUMNS = [
        'business_id' => 'business_id::text',
        'day' => "to_char(day, 'YYYY-MM-DD')",
        'page_path' => 'page_path',
        'pageviews' => 'pageviews::text',
        'unique_views' => 'unique_views::text',
        'exits' => 'exits::text',
        'conversions' => 'conversions::text',
        'js_errors' => 'js_errors::text',
    ];

    /**
     * `l2_fact_vital_daily`, likewise.
     *
     * ⚠️ **NOT ONE COLUMN HERE NEEDS A RENDERING RULE EXCEPT `day`, AND THAT IS
     * THE POINT OF THE TABLE.** `metric` and `device_type` are short ASCII
     * tokens from two enums, `bucket` and `samples` are exact integers, and
     * there is no timestamp, no float and no `jsonb` among them — because the
     * mart stores a histogram rather than a percentile. The four rendering
     * hazards in this class's docblock have nothing to bite on.
     *
     * @var array<string, string>
     */
    private const array L2_FACT_VITAL_DAILY_COLUMNS = [
        'business_id' => 'business_id::text',
        'day' => "to_char(day, 'YYYY-MM-DD')",
        'metric' => 'metric',
        'device_type' => 'device_type',
        'bucket' => 'bucket::text',
        'samples' => 'samples::text',
    ];

    /**
     * `l3_benchmark_cohort_daily`, likewise — and the one list here with no
     * `business_id` in it.
     *
     * ⛔ **THAT ABSENCE IS ASSERTED RATHER THAN ASSUMED.** The
     * snapshot-completeness lint in `Architecture/WarehouseTest` compares this
     * list against `pg_attribute`, so a `business_id` added to L3 would have to
     * be added here too — and the L3 column lint beside it refuses that column
     * by name. Two lints, because a snapshot that quietly stopped comparing a
     * column is the failure decision 256 describes, and a tenant column in L3 is
     * the failure the layer exists to prevent.
     *
     * ⚠️ No timestamp, no float, no `jsonb`: only `day` needs a rendering rule,
     * exactly as in the vitals mart.
     *
     * @var array<string, string>
     */
    private const array L3_BENCHMARK_COHORT_DAILY_COLUMNS = [
        'day' => "to_char(day, 'YYYY-MM-DD')",
        'vertical' => 'vertical',
        'metric' => 'metric',
        'tenant_count' => 'tenant_count::text',
        'p25' => 'p25::text',
        'p50' => 'p50::text',
        'p75' => 'p75::text',
    ];

    /**
     * The alias the row count rides in on, and why it is a window function.
     *
     * ⛔ **THE HEADER SAYS `rows=N` AND IT IS EMITTED BEFORE THE ROWS, SO A
     * STREAMING SNAPSHOT HAS TO KNOW `N` BEFORE IT HAS SEEN THEM** (8362). The
     * obvious answer is a second `SELECT count(*)`, and it is wrong: two
     * statements are two snapshots under `READ COMMITTED`, so a live ingest
     * landing between them writes a section whose header disagrees with the rows
     * beneath it — a digest no re-run reproduces, produced by the class whose
     * whole job is reproducibility. `count(*) OVER ()` is one statement, so the
     * count and the rows are the same set by construction.
     *
     * ⚠️ **IT IS STRIPPED BEFORE THE ROW IS ENCODED**, so the bytes are
     * unchanged: [[CanonicalJson::assertShape()]] compares keys **in order**,
     * and it runs after the `unset()` — which is what proves the strip happened.
     */
    private const string COUNT_ALIAS = '__snapshot_rows';

    /**
     * Every derived row for one business, as the bytes the gate compares.
     *
     * ⚠️ **THE TABLE NAME IS IN THE OUTPUT, AND THE ROW COUNT IS TOO.** Without
     * them, a snapshot of an empty L1 and a snapshot of an empty L2 are the same
     * empty string, and so is a snapshot taken before either table existed — so
     * a rebuild that produced nothing at all would compare equal to a rebuild
     * that produced nothing at all, and the gate would pass most loudly at the
     * moment it should fail. This is 256's vacuity written into a fixture.
     *
     * ⛔ **THIS RETURNS THE WHOLE ANSWER AS ONE STRING AND IS THEREFORE THE ONE
     * METHOD HERE THAT IS NOT BOUNDED — IT HAS NO CALLER IN `app/` AND MUST NOT
     * GAIN ONE** (8362). Every assertion in `ByteIdenticalReplayTest` compares
     * two snapshots, or looks for a substring in one, so the tests need the
     * bytes in hand; `Replayer` needs only their digest, and {@see self::digest()}
     * never materialises them. **Measured before the split: 24.9 MB of output
     * cost 129.3 MB of peak** on one tenant's 33,000 L1 rows, because
     * `section()` held the row objects, the encoded lines and the joined string
     * at once. A production caller added here would put that back on a path that
     * runs on every replay.
     */
    public static function of(int $businessId): string
    {
        $bytes = '';

        foreach (self::bytes($businessId) as $chunk) {
            $bytes .= $chunk;
        }

        return $bytes;
    }

    /**
     * The same answer, as the digest `etl_runs` records.
     *
     * ⛔ **HASHED AS IT IS PRODUCED, NEVER ASSEMBLED** (8362). This is the only
     * one of the four public methods here that a replay calls, and it used to run
     * {@see self::of()} — so **every** replay, of **any** range, held the
     * tenant's entire L1 and all six marts in PHP twice over, once as row objects
     * and once as JSON strings. `hash_update()` over the same generator produces
     * the same digest by construction, because it is the same byte stream from
     * the same producer.
     */
    public static function digest(int $businessId): string
    {
        return self::hashOf(self::bytes($businessId));
    }

    /**
     * The whole of L3, as the bytes its own gate compares.
     *
     * ⚠️ **NO TENANT ARGUMENT, AND THAT IS THE LAYER'S DEFINING PROPERTY RATHER
     * THAN AN OVERSIGHT.** `of()` takes a business because L1 and L2 are that
     * business's rows; L3 is nobody's, so the snapshot is of the table.
     *
     * ⚠️ **ORDERED `COLLATE "C"` ON BOTH TEXT COLUMNS**, on hazard 3 in this
     * class's docblock — even though both are constrained to short ASCII enum
     * values today, which is exactly the reasoning that stops being true when a
     * vocabulary grows.
     *
     * ⚠️ **UNBOUNDED IN THE SAME WAY `of()` IS, AND FOR THE SAME REASON IT IS
     * TOLERABLE**: its callers are assertions that need the bytes.
     * `NetworkBenchmarks` calls {@see self::networkDigest()}, which streams.
     */
    public static function network(): string
    {
        $bytes = '';

        foreach (self::networkBytes() as $chunk) {
            $bytes .= $chunk;
        }

        return $bytes;
    }

    /**
     * The same answer, as the digest `warehouse:benchmark` prints.
     */
    public static function networkDigest(): string
    {
        return self::hashOf(self::networkBytes());
    }

    /**
     * One tenant's derived layers, as a stream of byte chunks.
     *
     * ⚠️ **THE SECTION ORDER IS THE FILE FORMAT** and is the same list `of()`
     * concatenated before this was a generator.
     *
     * @return Generator<int, string>
     */
    private static function bytes(int $businessId): Generator
    {
        yield from self::section('l1_events', self::L1_COLUMNS, $businessId, 'received_at, event_id');

        yield from self::section(
            'l2_fact_source_daily',
            self::L2_COLUMNS,
            $businessId,
            'day, utm_source COLLATE "C", utm_medium COLLATE "C"',
        );

        yield from self::section(
            'l2_fact_session',
            self::L2_FACT_SESSION_COLUMNS,
            $businessId,
            // ⚠️ **ONE COLUMN, AND IT IS TOTAL RATHER THAN NEARLY SO** (8040).
            // `session_id` is unique per business by primary key now, so this
            // ordering has no ties to break — where `day, session_id` was
            // total only because `day` was in the key beside it.
            'session_id',
        );

        yield from self::section(
            'l2_fact_conversion',
            self::L2_FACT_CONVERSION_COLUMNS,
            $businessId,
            'day, conversion_id',
        );

        yield from self::section(
            'l2_fact_daily_tenant',
            self::L2_FACT_DAILY_TENANT_COLUMNS,
            $businessId,
            'day',
        );

        yield from self::section(
            'l2_fact_page_daily',
            self::L2_FACT_PAGE_DAILY_COLUMNS,
            $businessId,
            'day, page_path COLLATE "C"',
        );

        yield from self::section(
            'l2_fact_vital_daily',
            self::L2_FACT_VITAL_DAILY_COLUMNS,
            $businessId,
            'day, metric COLLATE "C", device_type COLLATE "C", bucket',
        );
    }

    /**
     * @return Generator<int, string>
     */
    private static function networkBytes(): Generator
    {
        yield from self::section(
            'l3_benchmark_cohort_daily',
            self::L3_BENCHMARK_COHORT_DAILY_COLUMNS,
            null,
            'day, vertical COLLATE "C", metric COLLATE "C"',
        );
    }

    /**
     * @param  iterable<int, string>  $chunks
     */
    private static function hashOf(iterable $chunks): string
    {
        $context = hash_init('sha256');

        foreach ($chunks as $chunk) {
            hash_update($context, $chunk);
        }

        return hash_final($context);
    }

    /**
     * One table's bytes, a row at a time.
     *
     * ⛔ **`DB::cursor()` RATHER THAN `DB::select()`, AND THE DIFFERENCE IS ONLY
     * PHP-SIDE** (8362). PostgreSQL has no unbuffered result set — libpq
     * retrieves the whole answer whatever PHP asks for — so what this removes is
     * the *four* PHP-heap copies that stood on top of it: the `stdClass` per row,
     * the encoded line per row, the array holding every line, and the string
     * `implode()` built from it. The wire buffer stays and is roughly the size of
     * the output; the multiplier is what went.
     *
     * ⚠️ **THE BYTES ARE UNCHANGED AND THAT IS ASSERTED RATHER THAN ARGUED.**
     * `implode("\n", [header, r1, r2]) . "\n"` is `header\nr1\nr2\n`, which is
     * what emitting the header, then `"\n"` before every row, then a final
     * `"\n"` produces — including the empty case, `header\n`.
     *
     * @param  array<string, string>  $columns
     * @return Generator<int, string>
     */
    private static function section(string $table, array $columns, ?int $businessId, string $order): Generator
    {
        // ⚠️ THE COUNT RIDES IN FRONT OF THE COLUMNS, NOT BEHIND THEM. It is
        // stripped by name below, so its position is free — and putting it first
        // keeps the column list on screen as the list this class declares.
        $select = ['count(*) OVER () AS "'.self::COUNT_ALIAS.'"'];

        foreach ($columns as $alias => $expression) {
            $expression = str_replace('%FORMAT%', L0Line::PG_TIMESTAMP_FORMAT, $expression);

            $select[] = $expression.' AS "'.$alias.'"';
        }

        // ⚠️ AN EXPLICIT TENANT PREDICATE ON A RAW QUERY. CLAUDE.md: "you still
        // never hand-write a raw query that bypasses a scope." Row-level security
        // is underneath this and would refuse another tenant's rows anyway, but
        // RLS "catches a forgotten filter, never a wrong one" — and a snapshot
        // that silently included every tenant would still be deterministic, so
        // no assertion in this gate would notice.
        //
        // ⛔ **AND A NULL `$businessId` MEANS "THIS TABLE HAS NO TENANT COLUMN",
        // WHICH IS TRUE OF EXACTLY ONE TABLE AND IS THE WHOLE POINT OF IT.** L3
        // carries no `business_id` by schema, so a predicate here could not be
        // written at all — `l3_benchmark_cohort_daily` has nothing to compare.
        // The nullable parameter is deliberately *not* a convenience: it is
        // reachable only from `networkBytes()`, which names the one table this is
        // legitimate for, and adding a second tenant-owned caller that passed
        // null would be a snapshot spanning every tenant, which is the failure
        // the paragraph above describes.
        $rows = DB::cursor(
            'SELECT '.implode(', ', $select)
            .' FROM '.$table
            .($businessId === null ? '' : ' WHERE business_id = ?')
            .' ORDER BY '.$order,
            $businessId === null ? [] : [$businessId],
        );

        $keys = array_keys($columns);
        $headed = false;

        foreach ($rows as $row) {
            /** @var array<string, mixed> $values */
            $values = get_object_vars($row);

            if (! $headed) {
                yield $table.' rows='.(int) $values[self::COUNT_ALIAS];

                $headed = true;
            }

            unset($values[self::COUNT_ALIAS]);

            CanonicalJson::assertShape($values, $keys, 'A '.$table.' snapshot row');

            yield "\n".CanonicalJson::encode($values);
        }

        // ⚠️ AN EMPTY TABLE NEVER REACHES THE LOOP, SO ITS HEADER IS WRITTEN
        // HERE — and `rows=0` is the one count a window function cannot report,
        // because there is no row for it to ride on. This is the anti-vacuity
        // line the class docblock is about, so it is stated rather than skipped.
        if (! $headed) {
            yield $table.' rows=0';
        }

        yield "\n";
    }
}
