<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

/**
 * The L0 line format, written down in one place.
 *
 * Both the writer ([[L0Batch::lines()]]) and the reader
 * ([[\App\Services\Warehouse\Replayer]]) refer to these constants rather than to
 * literals of their own, so the format cannot drift apart at its two ends —
 * which is the specific way a "byte-identical" claim goes quietly false: the
 * writer gains a key, the reader ignores it, and every existing row rebuilds
 * correctly while every new one loses a field.
 */
final class L0Line
{
    /**
     * The version-1 key order — every line this application ever wrote before
     * §11 row 8's enrichment landed (decision 5000s). Kept, not deleted: a
     * schema version bump means *reading* every version forever, and this is
     * the version-1 reader's shape rather than a historical note. No version-1
     * object has ever actually been written — nothing wrote L0 in production
     * before this collector existed (decision 4861) — so this is the archive's
     * invariant honoured rather than a real legacy format being carried.
     *
     * @var list<string>
     */
    public const array KEYS_V1 = [
        'schema_version',
        'batch_id',
        'business_id',
        'data_class',
        'received_at',
        'receipt_seq',
        'receipt_id',
        'payload',
    ];

    /**
     * The version-2 key order. §11 row 8's four enrichment fields sit ahead of
     * `payload`, on the same reasoning `receipt_id` already does: they are
     * receipt metadata, ours, and go through [[CanonicalJson]] — `payload` is
     * the one opaque field in the line and stays last.
     *
     * @var list<string>
     */
    public const array KEYS_V2 = [
        'schema_version',
        'batch_id',
        'business_id',
        'data_class',
        'received_at',
        'receipt_seq',
        'receipt_id',
        'ip_hash',
        'browser',
        'browser_version',
        'os',
        'payload',
    ];

    /**
     * The canonical key order for one schema version.
     *
     * ⚠️ **THIS, NOT A BARE CONSTANT, IS WHAT `L0Batch::lines()` AND
     * `ObjectStoreL0Archive::read()` NOW CALL.** A line's own `schema_version`
     * decides its shape — read back off the line at read time, and taken from
     * `config('warehouse.schema_version')` at write time — rather than one
     * constant serving every version this archive will ever hold.
     *
     * @return list<string>
     */
    public static function keysFor(int $schemaVersion): array
    {
        return $schemaVersion >= 2 ? self::KEYS_V2 : self::KEYS_V1;
    }

    /**
     * ⚠️ **MILLISECONDS, UTC, AND A LITERAL `Z`.**
     *
     * §5.3 types the L1 time columns `DateTime64(3)`, so three fractional digits
     * is the precision the warehouse is specified at. Fixing it **here**, at the
     * archive boundary, rather than at the database is deliberate: a value
     * truncated once on the way in reads back the same forever, whereas a column
     * that truncates on write would produce a row that no longer matches the L0
     * object it came from — and a later widening of the column would change
     * every rebuild's bytes without touching a line of derivation code.
     *
     * `\Z` is a literal rather than `P` or `T`: an offset of `+00:00` and a `Z`
     * are the same instant and different bytes, and PHP will happily render
     * either depending on how the Carbon instance was constructed.
     */
    public const string TIMESTAMP_FORMAT = 'Y-m-d\TH:i:s.v\Z';

    /**
     * The Postgres rendering of the same instant, used by the snapshot.
     *
     * ⚠️ **`to_char()` RATHER THAN `::text`, AND THIS IS THE SUBTLEST HAZARD IN
     * THE WHOLE GATE.** `timestamptz::text` is rendered through **two session
     * settings** — `DateStyle` and `TimeZone` — so the identical stored value
     * prints as `2026-08-17 12:34:56.789+00`, `2026-08-17 05:34:56.789-07` or
     * `08/17/2026 12:34:56.789 UTC` depending on the connection that asked. A
     * replay run from a cron shell with a different `PGTZ` would then produce a
     * snapshot that differs from the original in every timestamp, and the
     * failure would look like a bug in the derivation. An explicit pattern
     * depends on neither setting.
     *
     * ⛔ **AND THE WAREHOUSE COLUMNS ARE `timestamp`, NOT `timestamptz`** — the
     * house convention `TimeTest` fails the build over — which removes the
     * `TimeZone` half at the source. ⚠️ **So `AT TIME ZONE 'UTC'` must NOT be
     * wrapped around them**: on a plain `timestamp` it *converts to*
     * `timestamptz` and **introduces** the dependency it appears to remove.
     */
    public const string PG_TIMESTAMP_FORMAT = 'YYYY-MM-DD"T"HH24:MI:SS.MS"Z"';
}
