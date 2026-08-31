<?php

declare(strict_types=1);

namespace App\Services\Gsc;

use App\Enums\VisibilityState;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

/**
 * One Search Analytics answer: the daily rows, and how much of it Google
 * considers settled.
 *
 * ## Data freshness — the load-bearing part of this class
 *
 * Read from https://developers.google.com/webmaster-tools/v1/searchanalytics/query
 * on 2026-08-05, and confirmed against the live discovery document (revision
 * 20260804):
 *
 *   - `dataState` takes `all`, `final` or `hourly_all`, **case-insensitive**, and
 *     is `final` when omitted. Quoted: *"If 'all' (case-insensitive), data will
 *     include fresh data. If 'final' (case-insensitive) or if this parameter is
 *     omitted, the returned data will include only finalized data."*
 *   - the response's `metadata.firstIncompleteDate` is *"populated only when the
 *     request's `dataState` is `all` and data is grouped by `date`, and the
 *     requested date range contains incomplete data points"*, and *"all values
 *     after the first_incomplete_date may still change noticeably"*.
 *   - Google's help documentation puts the ordinary lag at roughly two days, and
 *     finalisation of the tail can trail further. **Neither figure is hardcoded
 *     here**: the API states the boundary per response, and a constant would go
 *     stale silently the day Google changed it.
 *
 * ⚠️ **AND THE FIELD NAME IS SPELLED TWO DIFFERENT WAYS IN GOOGLE'S OWN DOCS.**
 * The HTML reference page writes `first_incomplete_date`; the discovery document
 * — the machine-readable contract, dated a day before this was written — declares
 * the schema property as `firstIncompleteDate`. Google's JSON serialisation
 * camel-cases proto field names, so `firstIncompleteDate` is what should arrive.
 * **Both are read**, in that order, because getting this wrong has no symptom:
 * the key resolves to null, every day is marked settled, and the product starts
 * reporting a number that is still moving as though it were final. That is
 * decision 684's shape a fourth time — Stripe's `current_period_end` moved to
 * `items.data[]` and *"the plausible line is the wrong one"*, yielding null with
 * no error, forever, on the column a date was read from.
 *
 * ## Why `dataState: all` at all, when `final` would be simpler
 *
 * Because `final` answers the wrong question. It silently truncates the window —
 * a 28-day request comes back with 25 or 26 days and no field saying so, so the
 * totals are quietly low and nothing distinguishes *"Google has not finished
 * counting"* from *"traffic fell"*. Decision 1084's whole argument is that those
 * two must not collapse. `all` returns the days **and** the boundary, which is
 * the only combination that lets `VisibilityTotals::$final` be true or false for
 * a stated reason.
 */
final readonly class SearchAnalyticsResult
{
    /**
     * @param  list<DailyMetrics>  $days
     */
    private function __construct(
        public array $days,
        /**
         * The first date whose numbers Google says may still move, or null when
         * every returned day is settled.
         */
        public ?CarbonImmutable $firstIncompleteDate,
        /** Rows that could not be read at all. Logged, never guessed at. */
        public int $dropped,
    ) {}

    public static function fromResponse(Response $response): self
    {
        $rows = $response->json('rows');

        $boundary = self::firstIncompleteDateIn($response);

        $days = [];
        $dropped = 0;

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                $dropped++;

                continue;
            }

            $day = DailyMetrics::fromApi($row);

            if ($day === null) {
                $dropped++;

                continue;
            }

            // `firstIncompleteDate` is inclusive: it names the first day that is
            // itself incomplete, and everything after it is too.
            $days[] = $boundary !== null && $day->date->greaterThanOrEqualTo($boundary)
                ? $day->notFinal()
                : $day;
        }

        return new self($days, $boundary, $dropped);
    }

    /**
     * True when Google returned nothing at all for the window.
     *
     * ⚠️ Distinguished from "returned rows that were all zero" on purpose. Google
     * omits a day entirely when there is nothing to report, so an empty response
     * is *no data*, while a returned row of zeroes is a measured zero. Both exist
     * and they are different claims — see {@see VisibilityState}.
     */
    public function isEmpty(): bool
    {
        return $this->days === [];
    }

    private static function firstIncompleteDateIn(Response $response): ?CarbonImmutable
    {
        // camelCase first: it is what the machine-readable contract declares and
        // what the wire has been observed to carry. snake_case second, because
        // the human-facing reference page spells it that way and being wrong
        // here is undetectable at runtime.
        $raw = $response->json('metadata.firstIncompleteDate')
            ?? $response->json('metadata.first_incomplete_date');

        if (! is_string($raw) || ! CarbonImmutable::hasFormat($raw, 'Y-m-d')) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $raw) ?: null;
    }
}
