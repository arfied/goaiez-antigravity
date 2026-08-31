<?php

declare(strict_types=1);

namespace App\Services\Gsc;

use App\Services\Visibility\VisibilityTotals;
use Carbon\CarbonImmutable;

/**
 * One day's row from a `dimensions: ["date"]` Search Analytics query.
 *
 * ⚠️ **EVERY METRIC IS A `double` ON THE WIRE, INCLUDING `clicks` AND
 * `impressions`.** The discovery document's `ApiDataRow` types all four as
 * `{"type": "number", "format": "double"}` — counts included. PHP's `json_decode`
 * gives an `int` for `10` and a `float` for `10.0`, and Google has been observed
 * to send both, so an `is_int()` guard rejects real rows and an `(int)` cast
 * without a numeric check silently turns a malformed value into `0`. Both
 * failures are invisible: the first loses days, the second invents zeroes, and
 * zeroes are the exact lie decision 1084 forbids.
 *
 * ⚠️ **`ctr` IS READ AND DISCARDED.** See {@see VisibilityTotals}
 * — it is exactly `clicks / impressions`, and keeping it would be a second source
 * of truth whose summed form disagrees with the two numbers printed beside it.
 *
 * `position` is Google's impression-weighted average position for the day. It is
 * **not a rank**, and decision 1085 keeps it off the normal surface for that
 * reason as much as for `29` §2's ranking rule.
 *
 * ⛔ **AN ABSENT OR MALFORMED `position` USED TO DEFAULT TO `0.0`, AND `0.0` IS
 * NOT A NUMBER THIS VENDOR CAN EVER LEGITIMATELY SEND.** Read live on
 * **2026-08-26** — `https://developers.google.com/webmaster-tools/v1/searchanalytics/query`
 * (the response-properties table: `rows[].position … double … Average position
 * in search results`, with no notes column and no field-mask parameter
 * anywhere in the request schema) and
 * `https://support.google.com/webmasters/answer/6155685` (*"Average position:
 * the average position of the topmost result from your site"*, i.e. **1 is the
 * best rank there is**). ⚠️ **This is Search Console's own vendor and its
 * omission rule is its own — it is not Places API (New) and the proto3
 * default-value-omission argument does not carry across by analogy.** Unlike
 * Places, this classic Discovery-document REST API has no field mask and no
 * per-field partial response for a row: the reference page's example shows all
 * four metrics on every row, with no documented case of one being dropped for
 * holding its default. And unlike a review count, **there is no legitimate
 * zero to corroborate against**: Google returns no row at all for a
 * date/dimension combination with nothing to report, so a row that exists at
 * all is a row with at least one impression, and the average position of at
 * least one impression is never less than 1. `0.0` is therefore not an
 * unlikely real value here the way `0` reviews is a real value for Places — it
 * is an **impossible** one, better than the best rank that exists. ⚠️ **And
 * `VisibilityTotals::sum()` divides by it**: `$weighted += $day['position'] *
 * $day['impressions']`, so a fabricated `0.0` on a day with real impressions
 * does not merely corrupt that one day, it drags the whole window's
 * impression-weighted average toward a rank better than any that could really
 * exist — the opposite direction from an undercount, and silent, because a
 * better-looking number raises no alarm. The remedy here is the same one this
 * file already applies to an unreadable date: **the day is dropped and counted
 * in {@see SearchAnalyticsResult::$dropped}** rather than
 * recorded with an invented figure, because for this vendor "the field is
 * missing" is evidence the response is untrustworthy rather than evidence the
 * value is zero.
 */
final readonly class DailyMetrics
{
    private function __construct(
        public CarbonImmutable $date,
        public int $clicks,
        public int $impressions,
        public float $position,
        /**
         * Whether Google considers this day settled.
         *
         * Set by {@see SearchAnalyticsResult} from the response's
         * `metadata.firstIncompleteDate`, never by this row on its own — the row
         * carries no freshness field and inferring one from the date would mean
         * hardcoding Google's lag, which is documented as a range rather than a
         * number.
         */
        public bool $final,
    ) {}

    /**
     * @param  array<array-key, mixed>  $row
     */
    public static function fromApi(array $row): ?self
    {
        $keys = $row['keys'] ?? null;

        if (! is_array($keys) || ! isset($keys[0]) || ! is_string($keys[0])) {
            return null;
        }

        $date = CarbonImmutable::hasFormat($keys[0], 'Y-m-d')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $keys[0])
            : null;

        if (! $date instanceof CarbonImmutable) {
            return null;
        }

        $position = $row['position'] ?? null;

        // ⛔ **DROPPED RATHER THAN DEFAULTED TO `0.0`** — see the class docblock.
        // This vendor never legitimately omits `position` from a row that
        // exists, so a missing or non-numeric value here is evidence the
        // response is malformed, not evidence the position is zero. The same
        // treatment the unreadable-date arm above already gets.
        if (! is_numeric($position)) {
            return null;
        }

        return new self(
            date: $date,
            clicks: self::count($row['clicks'] ?? null),
            impressions: self::count($row['impressions'] ?? null),
            position: (float) $position,
            // Assumed settled; SearchAnalyticsResult marks the tail otherwise.
            final: true,
        );
    }

    /**
     * The same row, re-stamped as unfinalised.
     *
     * Readonly, so a copy rather than a mutation — and a named method rather than
     * a `with()` because there is exactly one thing that may change and exactly
     * one place allowed to change it.
     */
    public function notFinal(): self
    {
        return new self($this->date, $this->clicks, $this->impressions, $this->position, false);
    }

    /**
     * ⛔ **CLAMPED AT ZERO, BECAUSE `unsignedInteger` IS DOCUMENTATION ON
     * POSTGRES AND NOT ENFORCEMENT.**
     *
     * `gsc_daily_snapshots.clicks` and `.impressions` are declared
     * `unsignedInteger()`, which Postgres emits as a plain `integer` — it has no
     * unsigned type at all — so a negative stores silently, the opposite of the
     * MySQL this schema was first written against. The `(int) round((float) …)`
     * below preserves a sign perfectly happily, and the docblock above already
     * says Google types every metric as a `double` on the wire: a negative is a
     * well-formed value of the declared type rather than a malformed one.
     *
     * ⚠️ **A CLAMP RATHER THAN A REFUSAL, AND THE WRITE SHAPE IS WHY.**
     * `VisibilityReadings::record()` upserts every day of a sync in one
     * statement, so throwing — or letting the CHECK behind this abort — would
     * discard an entire location's history because one day arrived wrong. The
     * CHECK `gsc_daily_snapshots_counts_are_not_negative` stands behind this for
     * the hand-written `UPDATE` that has no parse step.
     *
     * ⚠️ **AND A NEGATIVE HERE DOES NOT MERELY UNDERCOUNT.**
     * {@see VisibilityTotals} divides `position * impressions` by `impressions`,
     * so one negative day moves the impression-weighted average position **the
     * wrong way** — the figure decisions 1084/1085 are most careful about.
     */
    private static function count(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) round((float) $value)) : 0;
    }
}
