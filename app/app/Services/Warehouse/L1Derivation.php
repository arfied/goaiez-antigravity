<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\DeviceClass;
use App\Services\Config\DefaultsRegistry;
use JsonException;

/**
 * L0 → L1. A pure function, and everything below exists to keep it one.
 *
 * §5.1: *"L1 CONFORMED (Silver). Validated, typed, enriched, deduplicated."*
 * §5.3 is the target shape and §11 row 7 is the constraint: whatever this
 * produces, it must produce again, byte for byte, from the same L0 object, on a
 * different machine, in a year.
 *
 * ⛔ **THREE THINGS THIS MAY NEVER TOUCH**, because each one silently un-does
 * that:
 *
 *  1. **The clock.** No `now()`, no `today()`, no default that reads one. Every
 *     time on an L1 row comes out of the L0 line. §5.3's `ingested_at` is the
 *     specification's own version of this trap and decision 4862 rules on it.
 *  2. **Randomness.** No generated id anywhere. `event_id` is the client's,
 *     minted in `pixel.js`'s `record()`; the row's identity comes from the data.
 *  3. **The database, the network, or any config a person can edit.** A lookup
 *     against a table that has since changed makes yesterday's replay produce
 *     today's answer — geo/ASN enrichment (§11 step 8) is exactly this shape.
 *     ⚠️ **`ip_hash`, `browser` AND `os` ARE THE PROOF THIS RULE WORKS RATHER
 *     THAN AN EXCEPTION TO IT** (decision 5000s): they are read straight off the
 *     line, computed once at the collector and archived, never recomputed here.
 *     Geo/ASN stays undone — no GeoLite2 database exists in this environment,
 *     verified rather than assumed — and `App\Contracts\GeoIpLookup` is the
 *     seam a real implementation binds to; when it lands it has to be versioned
 *     in L0 exactly as `ip_hash` now is, not looked up at derivation time.
 *
 * ⚠️ **WHAT IS DELIBERATELY NOT DERIVED, SO A GREEN SUITE IS NOT READ AS A
 * FINISHED PIPELINE** (decision 4865, narrowed by 5000s). §5.3 has forty-odd
 * columns and this fills the subset the payload and the L0 line can answer.
 * Absent: every geo/ASN column, for the reason above; `identity_id`, because
 * §13's identity resolution is not built and `IDENTITY_RESOLUTION_ENABLED` does
 * not exist in this codebase at all. **A nullable column nothing writes is
 * decision 272's shape**, so a geo/ASN column is not in the table either — it
 * arrives with a real `GeoIpLookup` implementation, not before.
 */
final class L1Derivation
{
    /**
     * §12: `is_bot = score >= 60`. Verified against the raw specification rather
     * than remembered — CLAUDE.md's rule about vendor strings and thresholds.
     */
    public const int BOT_THRESHOLD = 60;

    /**
     * ⚠️ **OURS, NOT THE SPECIFICATION'S** (decision 4866). §10 asks for
     * "device-class bucketing" and names no widths, and `pixel.js` sends a
     * viewport rather than a class. Guessing a number and citing the document
     * for it is what CLAUDE.md's "verify against the raw artefact" rule exists to
     * stop, so these are written down here as our own choice, in CSS pixels, on
     * the ordinary Tailwind-ish boundaries. Changing them changes every past
     * replay's output, which is why they are a constant rather than config.
     *
     * ⚠️ **THE BOUNDARIES ARE HERE AND THE VOCABULARY IS
     * [[\App\Enums\DeviceClass]]** (decision 5601). The four strings this
     * method returns are stored in L1 and grouped on by four marts, so they had
     * been written out as literals in one method and matched as literals
     * everywhere else — the shape `WarehouseTest`'s conversion-list lint exists
     * to refuse one table over. Splitting them this way keeps the one thing
     * that is genuinely ours — the widths — where 4866 argued for it, and gives
     * the strings a single declaration a reader can bind against.
     */
    public const int PHONE_MAX_VIEWPORT = 767;

    public const int TABLET_MAX_VIEWPORT = 1023;

    /**
     * The earliest instant {@see self::timestamp()} can render — `0001-01-01T00:00:00Z`.
     *
     * ⚠️ **WRITTEN AS EPOCH SECONDS BECAUSE THAT IS WHAT `strtotime()` RETURNS**,
     * and comparing there rather than after `gmdate()` is what keeps the check
     * ahead of the rendering it is about. A constant rather than a computed
     * `strtotime('0001-01-01Z')` for the reason the viewport widths are one:
     * changing it changes every past replay's output.
     */
    public const int EARLIEST_RENDERABLE = -62_135_596_800;

    /**
     * The latest instant {@see self::timestamp()} can render — `9999-12-31T23:59:59Z`.
     */
    public const int LATEST_RENDERABLE = 253_402_300_799;

    /**
     * Explode one L0 line into its L1 rows.
     *
     * ⚠️ **RETURNS ROWS IN PAYLOAD ORDER AND THAT ORDER IS PART OF THE
     * CONTRACT.** Nothing downstream sorts them back, and while the stored bytes
     * do not depend on insert order — there is no sequence on `l1_events` — a
     * derivation that reordered would break the one thing a human uses to read a
     * replay diff: the row that changed being next to the row before it.
     *
     * @param  array<string, mixed>  $line  a decoded L0 line, already shape-checked
     * @return list<array<string, mixed>>
     */
    public static function rows(array $line, string $l0Path): array
    {
        $botThreshold = app(DefaultsRegistry::class)->int('warehouse.bot_threshold');
        $payload = self::decodePayload($line['payload'] ?? null);

        if ($payload === null) {
            // ⛔ A REJECT, AND THE RECORD OF IT IS OWED. §11 row 6 asks for an
            // `ingest_rejects` table — "Rejects captured, never silent" — and
            // decision 4580 part 5 lists it among the unbuilt pieces. What
            // exists today is the count on `etl_runs.l0_rejected`, which tells
            // an operator that something was dropped and not which line.
            // Throwing instead would be worse: one malformed byte in a
            // seven-year archive would make every future replay of that range
            // fail forever, and L0 cannot be edited to fix it.
            return [];
        }

        $businessId = $line['business_id'];
        $dataClass = $line['data_class'];
        $receivedAt = $line['received_at'];
        $schemaVersion = $line['schema_version'];

        $events = $payload['events'] ?? null;

        if (! is_array($events)) {
            return [];
        }

        $device = is_array($payload['device'] ?? null) ? $payload['device'] : [];
        $utm = is_array($payload['utm'] ?? null) ? $payload['utm'] : [];

        $rows = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $eventId = self::string($event['event_id'] ?? null);

            // Without it there is no primary key and no dedupe, which is the one
            // field §11.5 makes a retried beacon harmless with.
            if ($eventId === null) {
                continue;
            }

            $occurredAt = self::timestamp($event['occurred_at'] ?? null);

            $rows[] = [
                'event_id' => $eventId,
                'business_id' => $businessId,
                'data_class' => $dataClass,
                'event_type' => self::string($event['type'] ?? null) ?? 'unknown',
                'consent_state' => self::string($event['consent_state'] ?? null) ?? 'unknown',
                'anonymous_id' => self::uuid($event['anonymous_id'] ?? null),
                'session_id' => self::uuid($event['session_id'] ?? null),

                // ⚠️ FALLS BACK TO THE RECEIPT TIME RATHER THAN TO THE CLOCK. A
                // client with a wrong or missing date still has a real receipt
                // time, and it is already archived — so the column stays NOT
                // NULL without anything reading a clock at derivation time.
                'occurred_at' => $occurredAt ?? $receivedAt,
                'received_at' => $receivedAt,

                'is_bot' => self::botScore($device) >= $botThreshold,
                'bot_probability' => self::botScore($device),

                'page_path' => self::truncate(self::string($event['page_path'] ?? null) ?? '/', 512),
                'page_host' => self::truncate(self::host($event['page_url'] ?? null) ?? '', 255),
                'referrer_host' => self::nullIfEmpty(self::truncate(self::string($payload['referrer_host'] ?? null) ?? '', 255)),

                'utm_source' => self::nullIfEmpty(self::truncate(self::string($utm['source'] ?? null) ?? '', 128)),
                'utm_medium' => self::nullIfEmpty(self::truncate(self::string($utm['medium'] ?? null) ?? '', 128)),
                'utm_campaign' => self::nullIfEmpty(self::truncate(self::string($utm['campaign'] ?? null) ?? '', 255)),

                'device_type' => self::deviceType($device),

                'properties' => CanonicalJson::encode(
                    self::canonicalise(is_array($event['properties'] ?? null) ? $event['properties'] : []),
                ),

                'l0_path' => self::truncate($l0Path, 512),
                'schema_version' => $schemaVersion,

                // §11 row 8 (decision 5000s). ⚠️ READ OFF THE LINE, NEVER
                // COMPUTED HERE — the three things this function may never touch
                // (the clock, randomness, a lookup) apply to enrichment exactly
                // as they apply to geo/ASN, which is why it was hashed and
                // classified at the collector and archived rather than derived.
                // A version-1 line (`$schemaVersion < 2`) carries none of these
                // keys at all, so the row gets `null` — the archive's own
                // invariant honoured rather than a guess about a line that was
                // never actually written this way.
                'ip_hash' => $schemaVersion >= 2 ? self::string($line['ip_hash'] ?? null) : null,
                'browser' => $schemaVersion >= 2 ? (self::string($line['browser'] ?? null) ?? 'unknown') : null,
                'browser_version' => $schemaVersion >= 2 ? self::string($line['browser_version'] ?? null) : null,
                'os' => $schemaVersion >= 2 ? (self::string($line['os'] ?? null) ?? 'unknown') : null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodePayload(mixed $raw): ?array
    {
        if (! is_string($raw)) {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Make an arbitrary client structure safe for [[CanonicalJson]].
     *
     * ⛔ **A FLOAT BECOMES ITS EXACT DECIMAL STRING, AND THIS IS A RULING RATHER
     * THAN A TIDY-UP** (decision 4867). `pixel.js` genuinely sends floats —
     * `record('vital', { metric: 'CLS', value: Math.round(cls * 1000) / 1000 })`
     * and `navigator.deviceMemory`, which is `0.25` on a small phone. Re-encoding
     * one through `json_encode()` renders it through **`serialize_precision`**,
     * which is an ini setting: `-1` writes `0.001` and `17` writes
     * `0.001000000000000000020816681711721685`, for the same double. A replay on
     * a differently configured box would then differ in every vitals row while
     * every value compared equal, which is the exact failure this gate exists to
     * catch and the exact one it would be blind to.
     *
     * `%.17G` is `sprintf`'s own formatting and reads no ini setting; seventeen
     * significant digits is what round-trips an IEEE-754 double, so nothing is
     * lost. What is given up is the JSON *type* — a consumer reads `"0.001"`
     * rather than `0.001`.
     *
     * ⚠️ **THE BETTER FIX IS UPSTREAM AND IS NOT THIS LANE'S TO MAKE**: the pixel
     * should send an integer in the smallest unit, which is what CLAUDE.md
     * already requires of money ("store integer cents everywhere, `17999`, not
     * `179.99`"). `CLS × 1000` is already computed as one before being divided
     * back into a float. Reported rather than changed, because the pixel's 14 KB
     * gate and its lints are another lane's and a drive-by edit to a file under a
     * build-failing budget is how two lanes conflict.
     *
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private static function canonicalise(array $value): array
    {
        $out = [];

        foreach ($value as $key => $item) {
            if (is_float($item)) {
                $out[$key] = sprintf('%.17G', $item);

                continue;
            }

            if (is_array($item)) {
                $out[$key] = self::canonicalise($item);

                continue;
            }

            if (is_object($item) || is_resource($item)) {
                continue;
            }

            $out[$key] = $item;
        }

        return $out;
    }

    /**
     * The two rows of §12's table the payload can answer on its own.
     *
     * ⚠️ **SEVEN OF THE NINE ARE NOT IMPLEMENTED AND THAT IS STATED RATHER THAN
     * ROUNDED OFF.** Datacenter ASN, bot UA substring and UA/client-hint
     * mismatch need what the collector sees, not what the client sends;
     * "no mousemove AND no keydown before conversion", "click <100 ms after
     * load", the paid-click pattern and the geo/timezone gap need either
     * cross-event state or geo. So a score from this function is a **floor**,
     * never the §12 score, and `is_bot` is correspondingly under-inclusive.
     * ⛔ **Do not read a green replay suite as bot filtering that works** — this
     * is the same warning `STAGE-0-GAPS.md` records against the PHI category
     * match: "do not read the green tests as coverage."
     *
     * @param  array<string, mixed>  $device
     */
    private static function botScore(array $device): int
    {
        $score = 0;

        if (($device['webdriver'] ?? null) === true) {
            $score += 30;
        }

        // "Implausible hardwareConcurrency for device", 15. A browser reports at
        // most a small number of logical cores; a headless farm reports the
        // host's. 32 is the boundary and it is ours, on the same terms as the
        // viewport widths above.
        $cores = $device['cores'] ?? null;

        if (is_int($cores) && $cores > 32) {
            $score += 15;
        }

        return min($score, 100);
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private static function deviceType(array $device): string
    {
        $width = $device['viewport_w'] ?? null;

        if (! is_int($width) || $width <= 0) {
            return DeviceClass::Unknown->value;
        }

        if ($width <= self::PHONE_MAX_VIEWPORT) {
            return DeviceClass::Phone->value;
        }

        return ($width <= self::TABLET_MAX_VIEWPORT ? DeviceClass::Tablet : DeviceClass::Desktop)->value;
    }

    private static function host(mixed $url): ?string
    {
        $url = self::string($url);

        if ($url === null) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : null;
    }

    /**
     * An ISO-8601 client timestamp, re-rendered in the archive's own format.
     *
     * ⚠️ **RE-RENDERED RATHER THAN PASSED THROUGH.** `pixel.js` sends
     * `new Date().toISOString()`, which is already `…Z` with milliseconds — but a
     * forged or future client could send `+00:00`, a different precision, or a
     * local offset, and all of those are the same instant written differently.
     * Storing what arrived would make the column's bytes a property of the
     * client rather than of the event. Parsed strictly: anything that is not a
     * recognisable instant is null, and the caller falls back to the receipt
     * time.
     *
     * ⛔ **AND AN INSTANT THIS FUNCTION CANNOT RENDER IS NOT A RECOGNISABLE ONE
     * EITHER — ADDED 2026-08-23 (8223), AFTER IT KILLED A RANGE FOR EVER.**
     * `strtotime()` accepts ISO-8601's extended year form, so
     * `+400000-01-01T00:00:00.000Z` parses happily and `gmdate('Y-m-d…')` renders
     * it as a **six-digit** year — past PostgreSQL's own `timestamp` ceiling of
     * 294276 AD — and the `l1_events` INSERT raises `SQLSTATE[22008] timestamp
     * out of range`. A leading-minus year is worse and quieter: `-4000-01-01`
     * renders back as `-4000-01-01T00:00:00.000Z`, which PostgreSQL reads as a
     * **time zone displacement** and refuses with `SQLSTATE[22009]`. **Both were
     * reproduced through the real collector route**, and both are permanent: the
     * L0 object is archived before the L1 insert is attempted
     * ([[\App\Jobs\ArchivePixelBatchJob]] stores, then derives), so the
     * poisoned line is in an immutable archive and every later
     * `warehouse:replay` of that day raises identically — decision 5031's failure
     * mode reached one layer earlier than any mart.
     *
     * ⚠️ **THE BOUND IS THE FORMAT'S OWN DOMAIN AND DELIBERATELY NOT A GUESS
     * ABOUT PLAUSIBLE CLOCKS.** `Y` promises four digits; years 1 through 9999
     * are exactly the instants this function can write down and read back, so
     * outside them it is already returning a string it did not promise. Choosing
     * a narrower window — "no earlier than the pixel shipped", "no later than
     * next year" — would be a product judgement with no artefact behind it, and
     * would have to be relative to something, which is the one thing a pure
     * derivation may not read.
     *
     * ⛔ **IT IS NOT A MAGNITUDE BOUND AND DOES NOT MAKE ONE UNNECESSARY.** A
     * session may still legitimately-as-far-as-this-function-knows run from year
     * 1 to year 9999, which is 3.15e11 seconds and overflows
     * `l2_fact_session.duration_s`. That is clamped where it is derived, in
     * [[Replayer]]; representability and magnitude are two different questions
     * and neither answer covers the other.
     */
    private static function timestamp(mixed $value): ?string
    {
        $value = self::string($value);

        if ($value === null) {
            return null;
        }

        $parsed = strtotime($value);

        if ($parsed === false) {
            return null;
        }

        if ($parsed < self::EARLIEST_RENDERABLE || $parsed > self::LATEST_RENDERABLE) {
            return null;
        }

        // Milliseconds survive the round trip only if they are read off the
        // string: strtotime() discards them.
        $milliseconds = 0;

        if (preg_match('/\.(\d{1,3})/', $value, $match) === 1) {
            $milliseconds = (int) str_pad($match[1], 3, '0');
        }

        return gmdate('Y-m-d\TH:i:s', $parsed).'.'.str_pad((string) $milliseconds, 3, '0', STR_PAD_LEFT).'Z';
    }

    private static function uuid(mixed $value): ?string
    {
        $value = self::string($value);

        if ($value === null) {
            return null;
        }

        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1
            ? strtolower($value)
            : null;
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * ⚠️ `mb_substr`, NOT `substr`. Cutting a multi-byte character in half
     * produces a string Postgres rejects as invalid UTF-8 — and the failure
     * arrives at insert time, on one row, in a replay of a range that used to
     * work.
     */
    private static function truncate(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }
}
